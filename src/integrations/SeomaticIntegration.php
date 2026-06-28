<?php
/**
 * Shortlink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\integrations;

use Craft;
use craft\helpers\App;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\integrations\seomatic\SeoShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use nystudio107\seomatic\Seomatic;
use nystudio107\seomatic\variables\SeomaticVariable;
use yii\base\Event;

/**
 * SEOmatic Integration
 *
 * Integrates Shortlink Manager with SEOmatic's tracking scripts
 * Pushes click events to Google Tag Manager data layer and Google Analytics
 *
 * @since 5.0.0
 */
class SeomaticIntegration extends BaseIntegration
{
    /**
     * @var array Events queued for next page render
     */
    private array $queuedEvents = [];

    /**
     * @var bool Whether event listeners have been registered
     */
    private bool $listenersRegistered = false;

    public function __construct()
    {
        $this->handle = 'seomatic';
        $this->name = 'SEOmatic';

        // Set logging handle for LoggingTrait
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Check if SEOmatic plugin is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isPluginInstalled('seomatic');
    }

    /**
     * Push an event to SEOmatic's data layer
     *
     * @param string $eventType
     * @param array $data
     * @return bool
     */
    public function pushEvent(string $eventType, array $data): bool
    {
        // Pre-flight checks
        if (!$this->isAvailable()) {
            $this->logDebug('SEOmatic plugin not available');
            return false;
        }

        if (!$this->isEnabled()) {
            $this->logDebug('SEOmatic integration not enabled');
            return false;
        }

        if (!$this->shouldTrackEvent($eventType)) {
            $this->logDebug("Event type '{$eventType}' not configured for tracking");
            return false;
        }

        if (!$this->validateEventData($eventType, $data)) {
            return false;
        }

        try {
            // Format event data
            $formattedData = $this->formatEventData($eventType, $data);

            // Register event listener if not already done
            $this->registerEventListener();

            // Queue the event
            $this->queuedEvents[] = $formattedData;

            // Try to inject immediately if scripts are available
            $this->injectDataLayerEvent($formattedData);

            $this->logInfo("Event '{$eventType}' queued successfully", [
                'event' => $formattedData['event'],
                'code' => $data['code'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logError('Failed to push event', [
                'eventType' => $eventType,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Prime SEOmatic's content metadata for a rendered ShortLink redirect page.
     */
    public function prepareMetadataForShortLink(ShortLink $shortLink): bool
    {
        if (!$this->isAvailable() || !$this->isEnabled()) {
            return false;
        }

        if (!class_exists(Seomatic::class) || !isset(Seomatic::$plugin)) {
            return false;
        }

        try {
            if (Seomatic::$seomaticVariable === null) {
                Seomatic::$seomaticVariable = new SeomaticVariable();
            }

            Seomatic::$plugin->seoElements->getAllSeoElementTypes();

            Seomatic::setMatchedElement($shortLink);
            Seomatic::$plugin->metaBundles->getMetaBundleBySourceId(
                SeoShortLink::getMetaBundleType(),
                SeoShortLink::SOURCE_ID,
                $shortLink->siteId
            );
            Seomatic::$plugin->metaContainers->loadMetaContainers('', $shortLink->siteId, $shortLink);

            return true;
        } catch (\Throwable $e) {
            $this->logError('Failed to prepare SEOmatic metadata for ShortLink', [
                'error' => $e->getMessage(),
                'code' => $shortLink->code,
                'siteId' => $shortLink->siteId,
            ]);

            return false;
        }
    }

    /**
     * Inject data layer event into SEOmatic scripts
     *
     * @param array $eventData
     * @return bool
     */
    private function injectDataLayerEvent(array $eventData): bool
    {
        if (!class_exists(Seomatic::class)) {
            return false;
        }

        try {
            // Access SEOmatic's script service
            $scriptService = Seomatic::$plugin->script ?? null;
            if (!$scriptService) {
                $this->logDebug('SEOmatic script service not available');
                return false;
            }

            // Try to inject into Google Tag Manager
            $gtmScript = $scriptService->get('googleTagManager');
            if ($gtmScript && $gtmScript->include) {
                // Initialize dataLayer if not exists
                if (!is_array($gtmScript->dataLayer)) {
                    $gtmScript->dataLayer = [];
                }

                // Add event to data layer
                $gtmScript->dataLayer[] = $eventData;

                $this->logDebug('Event injected into GTM data layer', [
                    'event' => $eventData['event'],
                ]);
                return true;
            }

            // Try to inject into gtag.js (Google Analytics)
            $gtagScript = $scriptService->get('gtag');
            if ($gtagScript && $gtagScript->include) {
                // Initialize dataLayer if not exists
                if (!is_array($gtagScript->dataLayer)) {
                    $gtagScript->dataLayer = [];
                }

                // Add event to data layer
                $gtagScript->dataLayer[] = $eventData;

                $this->logDebug('Event injected into gtag data layer', [
                    'event' => $eventData['event'],
                ]);
                return true;
            }

            $this->logDebug('No active tracking scripts found in SEOmatic');
            return false;
        } catch (\Throwable $e) {
            $this->logError('Failed to inject data layer event', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Register event listener for dynamic meta injection
     * This ensures events are available when pages are rendered
     */
    private function registerEventListener(): void
    {
        if ($this->listenersRegistered) {
            return;
        }

        $dynamicMetaClass = 'nystudio107\seomatic\helpers\DynamicMeta';
        if (!class_exists($dynamicMetaClass)) {
            return;
        }

        Event::on(
            $dynamicMetaClass,
            'addDynamicMeta',
            function() {
                $this->onAddDynamicMeta();
            }
        );

        $this->listenersRegistered = true;
        $this->logDebug('Registered SEOmatic event listeners');
    }

    /**
     * Handle SEOmatic's AddDynamicMeta event
     * Inject queued events into the data layer
     */
    private function onAddDynamicMeta(): void
    {
        if (empty($this->queuedEvents)) {
            return;
        }

        try {
            foreach ($this->queuedEvents as $eventData) {
                $this->injectDataLayerEvent($eventData);
            }

            $this->logDebug('Injected queued events', ['count' => count($this->queuedEvents)]);
        } catch (\Throwable $e) {
            $this->logError('Error in AddDynamicMeta handler', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Get integration status and configuration details
     * Checks ALL sites for tracking scripts
     *
     * @return array
     */
    public function getStatus(): array
    {
        $status = [
            'available' => $this->isAvailable(),
            'enabled' => $this->isEnabled(),
            'scripts' => [],
            'configuration' => [],
        ];

        if (!$this->isAvailable()) {
            return $status;
        }

        try {
            // Get all sites
            $sites = Craft::$app->sites->getAllSites();
            $scriptsFound = [];

            // Check each site for tracking scripts
            if (class_exists(Seomatic::class) && isset(Seomatic::$plugin)) {
                $currentSite = Craft::$app->sites->getCurrentSite();

                try {
                    foreach ($sites as $site) {
                        // Temporarily switch to this site
                        Craft::$app->sites->setCurrentSite($site);

                        // Load SEOmatic meta containers for this specific site
                        try {
                            if (isset(Seomatic::$plugin->metaContainers)) {
                                Seomatic::$plugin->metaContainers->loadMetaContainers('', $site->id);
                            }
                        } catch (\Throwable $e) {
                            // Silently continue if we can't load meta containers for this site
                        }

                        $scriptService = Seomatic::$plugin->script ?? null;
                        if (!$scriptService) {
                            continue;
                        }

                        // Check all known tracking scripts for this site
                        foreach ($this->_getScriptDefinitions() as $def) {
                            $this->_checkScript($scriptService, $site, $def, $scriptsFound);
                        }
                    }
                } finally {
                    Craft::$app->sites->setCurrentSite($currentSite);
                }
            }

            $status['scripts'] = $scriptsFound;

            // Get configuration from settings
            $settings = \lindemannrock\shortlinkmanager\ShortLinkManager::getInstance()->getSettings();
            $status['configuration'] = [
                'eventPrefix' => $settings->seomaticEventPrefix ?? 'shortlink_manager',
                'trackingEvents' => $settings->seomaticTrackingEvents ?? [],
            ];
        } catch (\Throwable $e) {
            $this->logError('Error getting SEOmatic status', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $status;
    }

    /**
     * Get list of available tracking scripts
     *
     * @return array
     */
    public function getAvailableScripts(): array
    {
        $status = $this->getStatus();
        return $status['scripts'] ?? [];
    }

    /**
     * Check if GTM is active
     *
     * @return bool
     */
    public function hasGoogleTagManager(): bool
    {
        $scripts = $this->getAvailableScripts();
        return isset($scripts['googleTagManager']) && $scripts['googleTagManager']['active'];
    }

    /**
     * Check if Google Analytics is active
     *
     * @return bool
     */
    public function hasGoogleAnalytics(): bool
    {
        $scripts = $this->getAvailableScripts();
        return isset($scripts['gtag']) && $scripts['gtag']['active'];
    }

    /**
     * Get tracking script definitions
     *
     * Each definition maps a SEOmatic script handle to:
     * - key: The key used in the scriptsFound array
     * - name: Display name for the script
     * - idKeys: Ordered list of var keys to check for the script's ID value
     *
     * @return array<array{handle: string, key: string, name: string, idKeys: string[]}>
     */
    private function _getScriptDefinitions(): array
    {
        return [
            ['handle' => 'googleTagManager', 'key' => 'googleTagManager', 'name' => 'Google Tag Manager', 'idKeys' => ['googleTagManagerId', 'googleTagManagerContainerId']],
            ['handle' => 'gtag', 'key' => 'gtag', 'name' => 'Google Analytics 4', 'idKeys' => ['googleAnalyticsId']],
        ];
    }

    /**
     * Check a single SEOmatic tracking script and add to results if configured
     *
     * @param mixed $scriptService SEOmatic script service
     * @param \craft\models\Site $site Current site being checked
     * @param array{handle: string, key: string, name: string, idKeys: string[]} $def Script definition
     * @param array &$scriptsFound Accumulated results (modified by reference)
     */
    private function _checkScript(mixed $scriptService, \craft\models\Site $site, array $def, array &$scriptsFound): void
    {
        $script = $scriptService->get($def['handle']);
        if (!$script || !$script->include) {
            return;
        }

        // Try each ID key in order until we find a value
        $id = null;
        foreach ($def['idKeys'] as $idKey) {
            $id = $script->vars[$idKey]['value'] ?? null;
            if ($id !== null) {
                break;
            }
        }

        // Resolve environment variables
        if (is_string($id)) {
            if (str_contains($id, '$')) {
                $id = App::env($id);
            }
            if (is_string($id)) {
                $id = trim($id);
            }
        }

        if (empty($id)) {
            return;
        }

        $key = $def['key'];
        if (!isset($scriptsFound[$key])) {
            $scriptsFound[$key] = [
                'active' => true,
                'name' => $def['name'],
                'sites' => [],
            ];
        }
        $scriptsFound[$key]['sites'][] = [
            'handle' => $site->handle,
            'name' => $site->name,
            'id' => $id,
        ];
    }
}
