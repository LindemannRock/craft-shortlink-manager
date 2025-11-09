<?php
/**
 * Shortlink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\integrations;

use Craft;
use yii\base\Event;

/**
 * SEOmatic Integration
 *
 * Integrates Shortlink Manager with SEOmatic's tracking scripts
 * Pushes click events to Google Tag Manager data layer and Google Analytics
 *
 * @since 1.1.0
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

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->handle = 'seomatic';
        $this->name = 'SEOmatic';

        // Set logging handle for LoggingTrait
        $this->setLoggingHandle('shortlink-manager');
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
     * Inject data layer event into SEOmatic scripts
     *
     * @param array $eventData
     * @return bool
     */
    private function injectDataLayerEvent(array $eventData): bool
    {
        $seomaticClass = 'nystudio107\seomatic\Seomatic';
        if (!class_exists($seomaticClass)) {
            return false;
        }

        try {
            // Access SEOmatic's script service
            $scriptService = $seomaticClass::$plugin->script ?? null;
            if (!$scriptService) {
                $this->logDebug('SEOmatic script service not available');
                return false;
            }

            // Try to inject into Google Tag Manager
            $gtmScript = $scriptService->get('googleTagManager');
            if ($gtmScript && isset($gtmScript->include) && $gtmScript->include) {
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
            if ($gtagScript && isset($gtagScript->include) && $gtagScript->include) {
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
            function () {
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
            $seomaticClass = 'nystudio107\seomatic\Seomatic';
            if (class_exists($seomaticClass)) {
                $currentSiteId = Craft::$app->sites->getCurrentSite()->id;

                foreach ($sites as $site) {
                    // Temporarily switch to this site
                    Craft::$app->sites->setCurrentSite($site);

                    // Load SEOmatic meta containers for this specific site
                    try {
                        if (isset($seomaticClass::$plugin->metaContainers)) {
                            $seomaticClass::$plugin->metaContainers->loadMetaContainers('', $site->id);
                        }
                    } catch (\Throwable $e) {
                        // Silently continue if we can't load meta containers for this site
                    }

                    $scriptService = $seomaticClass::$plugin->script ?? null;
                    if (!$scriptService) {
                        continue;
                    }

                    // Check Google Tag Manager
                    $gtmScript = $scriptService->get('googleTagManager');
                    if ($gtmScript && isset($gtmScript->include) && $gtmScript->include) {
                        $gtmId = $gtmScript->vars['googleTagManagerId']['value'] ??
                                $gtmScript->vars['googleTagManagerContainerId']['value'] ??
                                null;

                        if (is_string($gtmId)) {
                            $gtmId = \Craft::parseEnv($gtmId);
                            $gtmId = trim($gtmId);
                        }

                        if (!empty($gtmId)) {
                            if (!isset($scriptsFound['googleTagManager'])) {
                                $scriptsFound['googleTagManager'] = [
                                    'active' => true,
                                    'name' => 'Google Tag Manager',
                                    'sites' => [],
                                ];
                            }
                            $scriptsFound['googleTagManager']['sites'][] = [
                                'handle' => $site->handle,
                                'name' => $site->name,
                                'id' => $gtmId,
                            ];
                        }
                    }

                    // Check Google Analytics (gtag.js)
                    $gtagScript = $scriptService->get('gtag');
                    if ($gtagScript && isset($gtagScript->include) && $gtagScript->include) {
                        $measurementId = $gtagScript->vars['googleAnalyticsId']['value'] ?? null;

                        if (is_string($measurementId)) {
                            $measurementId = \Craft::parseEnv($measurementId);
                            $measurementId = trim($measurementId);
                        }

                        if (!empty($measurementId)) {
                            if (!isset($scriptsFound['gtag'])) {
                                $scriptsFound['gtag'] = [
                                    'active' => true,
                                    'name' => 'Google Analytics 4',
                                    'sites' => [],
                                ];
                            }
                            $scriptsFound['gtag']['sites'][] = [
                                'handle' => $site->handle,
                                'name' => $site->name,
                                'id' => $measurementId,
                            ];
                        }
                    }

                    // Check Facebook Pixel
                    $fbScript = $scriptService->get('facebookPixel');
                    if ($fbScript && isset($fbScript->include) && $fbScript->include) {
                        $fbId = $fbScript->vars['facebookPixelId']['value'] ?? null;

                        if (!empty($fbId)) {
                            if (!isset($scriptsFound['facebookPixel'])) {
                                $scriptsFound['facebookPixel'] = [
                                    'active' => true,
                                    'name' => 'Facebook Pixel',
                                    'sites' => [],
                                ];
                            }
                            $scriptsFound['facebookPixel']['sites'][] = [
                                'handle' => $site->handle,
                                'name' => $site->name,
                                'id' => $fbId,
                            ];
                        }
                    }

                    // Check LinkedIn Insight
                    $linkedInScript = $scriptService->get('linkedInInsight');
                    if ($linkedInScript && isset($linkedInScript->include) && $linkedInScript->include) {
                        $partnerId = $linkedInScript->vars['dataPartnerId']['value'] ?? null;

                        if (is_string($partnerId)) {
                            $partnerId = \Craft::parseEnv($partnerId);
                            $partnerId = trim($partnerId);
                        }

                        if (!empty($partnerId)) {
                            if (!isset($scriptsFound['linkedInInsight'])) {
                                $scriptsFound['linkedInInsight'] = [
                                    'active' => true,
                                    'name' => 'LinkedIn Insight Tag',
                                    'sites' => [],
                                ];
                            }
                            $scriptsFound['linkedInInsight']['sites'][] = [
                                'handle' => $site->handle,
                                'name' => $site->name,
                                'id' => $partnerId,
                            ];
                        }
                    }
                }

                // Restore original site
                Craft::$app->sites->setCurrentSite(Craft::$app->sites->getSiteById($currentSiteId));
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
}
