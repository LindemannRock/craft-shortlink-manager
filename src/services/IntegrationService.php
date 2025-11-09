<?php

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use lindemannrock\shortlinkmanager\integrations\IntegrationInterface;
use lindemannrock\shortlinkmanager\integrations\SeomaticIntegration;
use lindemannrock\shortlinkmanager\integrations\RedirectManagerIntegration;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Integration Service
 *
 * Central manager for all third-party analytics integrations
 * Handles loading, initialization, and routing of events to enabled integrations
 *
 * @since 1.1.0
 */
class IntegrationService extends Component
{
    use LoggingTrait;

    /**
     * @var IntegrationInterface[] Registered integrations
     */
    private array $integrations = [];

    /**
     * @var bool Whether integrations have been initialized
     */
    private bool $initialized = false;

    /**
     * Initialize all available integrations
     */
    public function init(): void
    {
        parent::init();
        $this->loadIntegrations();
    }

    /**
     * Load and register all available integrations
     */
    private function loadIntegrations(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            // Register SEOmatic integration (event-based analytics)
            $this->registerIntegration(new SeomaticIntegration());

            // Register Redirect Manager integration (utility-based redirect creation)
            $this->registerIntegration(new RedirectManagerIntegration());

            // Future integrations can be added here:
            // $this->registerIntegration(new MatomoIntegration());
            // $this->registerIntegration(new PlausibleIntegration());

            $this->initialized = true;

            $this->logDebug('Integrations loaded', [
                'count' => count($this->integrations),
                'available' => $this->getAvailableIntegrations(),
            ]);

        } catch (\Throwable $e) {
            $this->logError('Failed to load integrations', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Register an integration
     *
     * @param IntegrationInterface $integration
     */
    private function registerIntegration(IntegrationInterface $integration): void
    {
        $handle = $integration->getHandle();
        $this->integrations[$handle] = $integration;

        $this->logDebug('Registered integration', [
            'name' => $integration->getName(),
            'handle' => $handle,
            'available' => $integration->isAvailable(),
            'enabled' => $integration->isEnabled(),
        ]);
    }

    /**
     * Push an event to all enabled integrations
     *
     * @param string $eventType Event type (e.g., 'redirect', 'qr_scan')
     * @param array $data Event data
     * @return array Results from each integration ['handle' => bool]
     */
    public function pushEvent(string $eventType, array $data): array
    {
        if (!$this->initialized) {
            $this->loadIntegrations();
        }

        $results = [];

        foreach ($this->integrations as $handle => $integration) {
            try {
                // Only push to available and enabled integrations
                if (!$integration->isAvailable() || !$integration->isEnabled()) {
                    continue;
                }

                $success = $integration->pushEvent($eventType, $data);
                $results[$handle] = $success;

            } catch (\Throwable $e) {
                $this->logError('Integration failed', [
                    'handle' => $handle,
                    'eventType' => $eventType,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $results[$handle] = false;
            }
        }

        return $results;
    }

    /**
     * Get a specific integration by handle
     *
     * @param string $handle
     * @return IntegrationInterface|null
     */
    public function getIntegration(string $handle): ?IntegrationInterface
    {
        if (!$this->initialized) {
            $this->loadIntegrations();
        }

        return $this->integrations[$handle] ?? null;
    }

    /**
     * Get all registered integrations
     *
     * @return IntegrationInterface[]
     */
    public function getAllIntegrations(): array
    {
        if (!$this->initialized) {
            $this->loadIntegrations();
        }

        return $this->integrations;
    }

    /**
     * Get list of available integration handles
     *
     * @return array
     */
    public function getAvailableIntegrations(): array
    {
        if (!$this->initialized) {
            $this->loadIntegrations();
        }

        $available = [];
        foreach ($this->integrations as $handle => $integration) {
            if ($integration->isAvailable()) {
                $available[] = $handle;
            }
        }

        return $available;
    }

    /**
     * Get list of enabled integration handles
     *
     * @return array
     */
    public function getEnabledIntegrations(): array
    {
        if (!$this->initialized) {
            $this->loadIntegrations();
        }

        $enabled = [];
        foreach ($this->integrations as $handle => $integration) {
            if ($integration->isAvailable() && $integration->isEnabled()) {
                $enabled[] = $handle;
            }
        }

        return $enabled;
    }

    /**
     * Get status of all integrations
     *
     * @return array
     */
    public function getAllIntegrationStatuses(): array
    {
        if (!$this->initialized) {
            $this->loadIntegrations();
        }

        $statuses = [];
        foreach ($this->integrations as $handle => $integration) {
            $statuses[$handle] = $integration->getStatus();
        }

        return $statuses;
    }

    /**
     * Check if any integrations are enabled
     *
     * @return bool
     */
    public function hasEnabledIntegrations(): bool
    {
        return !empty($this->getEnabledIntegrations());
    }

    /**
     * Test an integration connection
     *
     * @param string $handle
     * @return array Test results
     */
    public function testIntegration(string $handle): array
    {
        $integration = $this->getIntegration($handle);

        if (!$integration) {
            return [
                'success' => false,
                'message' => 'Integration not found',
            ];
        }

        if (!$integration->isAvailable()) {
            return [
                'success' => false,
                'message' => 'Integration plugin not installed or enabled',
            ];
        }

        if (!$integration->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Integration not enabled in settings',
            ];
        }

        // Try a test event
        try {
            $testData = [
                'code' => 'TEST',
                'title' => 'Test Event',
                'destinationUrl' => 'https://example.com',
                'source' => 'test',
            ];

            $result = $integration->pushEvent('redirect', $testData);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Integration test successful',
                    'status' => $integration->getStatus(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Integration test failed',
                    'status' => $integration->getStatus(),
                ];
            }

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Render SEOmatic tracking script
     *
     * @param \lindemannrock\shortlinkmanager\elements\ShortLink $link
     * @param string $eventType Event type: 'qr_scan' or 'redirect'
     * @return \Twig\Markup|null HTML script tag or null if SEOmatic is not enabled
     */
    public function renderSeomaticTracking($link, string $eventType = 'redirect'): ?\Twig\Markup
    {
        // Check if SEOmatic integration is enabled
        $seomatic = $this->getIntegration('seomatic');
        if (!$seomatic || !$seomatic->isAvailable() || !$seomatic->isEnabled()) {
            return null;
        }

        // Load the template partial and render it
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(\craft\web\View::TEMPLATE_MODE_CP);

            $html = $view->renderTemplate('shortlink-manager/_integrations/seomatic', [
                'link' => $link,
                'eventType' => $eventType,
            ]);

            $view->setTemplateMode($oldMode);

            // Return as Twig Markup so it's automatically treated as safe HTML
            return \Twig\Markup::class ? new \Twig\Markup($html, 'UTF-8') : $html;

        } catch (\Throwable $e) {
            $view->setTemplateMode($oldMode);
            $this->logError('Failed to render SEOmatic tracking', [
                'error' => $e->getMessage(),
                'eventType' => $eventType,
                'code' => $link->code ?? null,
            ]);
            return null;
        }
    }

}
