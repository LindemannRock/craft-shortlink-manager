<?php
/**
 * Shortlink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\integrations;

use Craft;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Base Integration
 *
 * Abstract base class for all third-party integrations
 * Provides common functionality and helpers
 *
 * @since 1.1.0
 */
abstract class BaseIntegration implements IntegrationInterface
{
    use LoggingTrait;

    /**
     * @var string Integration handle
     */
    protected string $handle;

    /**
     * @var string Integration name
     */
    protected string $name;

    /**
     * @var array Required event data fields by event type
     */
    protected array $requiredFields = [
        'redirect' => ['code', 'title', 'destinationUrl', 'source'],
        'qr_scan' => ['code', 'title'],
    ];

    /**
     * Get the integration handle
     *
     * @return string
     */
    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * Get the integration name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if the integration is enabled in settings
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $settings = ShortLinkManager::getInstance()->getSettings();

        // Check if analytics is globally enabled
        if (!$settings->enableAnalytics) {
            return false;
        }

        // Check if this specific integration is enabled
        $enabledIntegrations = $settings->enabledIntegrations ?? [];
        return in_array($this->handle, $enabledIntegrations, true);
    }

    /**
     * Validate event data structure
     *
     * @param string $eventType
     * @param array $data
     * @return bool
     */
    public function validateEventData(string $eventType, array $data): bool
    {
        // Check if event type is supported
        if (!isset($this->requiredFields[$eventType])) {
            $this->logWarning("Unknown event type: {$eventType}");
            return false;
        }

        // Check required fields
        $missingFields = [];
        foreach ($this->requiredFields[$eventType] as $field) {
            if (!isset($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $this->logWarning("Missing required fields for {$eventType}: " . implode(', ', $missingFields));
            return false;
        }

        return true;
    }

    /**
     * Format event data for the integration
     *
     * @param string $eventType
     * @param array $data
     * @return array Formatted data
     */
    protected function formatEventData(string $eventType, array $data): array
    {
        $settings = ShortLinkManager::getInstance()->getSettings();
        $eventPrefix = $settings->seomaticEventPrefix ?? 'shortlink_manager';

        // Build base event structure
        $formattedData = [
            'event' => "{$eventPrefix}_{$eventType}",
            'shortlink' => []
        ];

        // Map common fields
        $fieldMapping = [
            'code' => 'code',
            'title' => 'title',
            'destinationUrl' => 'destination_url',
            'source' => 'source',
        ];

        foreach ($fieldMapping as $source => $target) {
            if (isset($data[$source])) {
                $formattedData['shortlink'][$target] = $data[$source];
            }
        }

        // Add device info if available
        if (isset($data['deviceInfo'])) {
            $device = $data['deviceInfo'];
            $formattedData['shortlink']['device_type'] = $device->deviceType ?? null;
            $formattedData['shortlink']['os'] = $device->osName ?? null;
            $formattedData['shortlink']['os_version'] = $device->osVersion ?? null;
            $formattedData['shortlink']['browser'] = $device->browser ?? null;
            $formattedData['shortlink']['browser_version'] = $device->browserVersion ?? null;
            $formattedData['shortlink']['is_mobile'] = $device->isMobile ?? null;
            $formattedData['shortlink']['is_tablet'] = $device->isTablet ?? null;
        }

        // Add geographic data if available
        if (isset($data['country'])) {
            $formattedData['shortlink']['country'] = $data['country'];
        }
        if (isset($data['city'])) {
            $formattedData['shortlink']['city'] = $data['city'];
        }

        // Clean up null values
        $formattedData['shortlink'] = array_filter(
            $formattedData['shortlink'],
            fn($value) => $value !== null
        );

        return $formattedData;
    }

    /**
     * Check if specific event type should be tracked
     *
     * @param string $eventType
     * @return bool
     */
    protected function shouldTrackEvent(string $eventType): bool
    {
        $settings = ShortLinkManager::getInstance()->getSettings();
        $trackingEvents = $settings->seomaticTrackingEvents ?? [];

        return in_array($eventType, $trackingEvents, true);
    }

    /**
     * Safe plugin check helper
     *
     * @param string $pluginHandle
     * @return bool
     */
    protected function isPluginInstalled(string $pluginHandle): bool
    {
        return Craft::$app->plugins->isPluginEnabled($pluginHandle);
    }

    // Abstract methods that must be implemented by child classes

    abstract public function isAvailable(): bool;
    abstract public function pushEvent(string $eventType, array $data): bool;
    abstract public function getStatus(): array;
}
