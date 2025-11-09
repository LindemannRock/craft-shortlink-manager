<?php
/**
 * Shortlink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\integrations;

/**
 * Integration Interface
 *
 * Contract for all third-party analytics integrations
 *
 * @since 1.1.0
 */
interface IntegrationInterface
{
    /**
     * Check if the integration's plugin/service is available
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Check if the integration is enabled in settings
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get the integration name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the integration handle (unique identifier)
     *
     * @return string
     */
    public function getHandle(): string;

    /**
     * Push an event to the third-party service
     *
     * @param string $eventType Event type (e.g., 'redirect', 'qr_scan')
     * @param array $data Event data
     * @return bool Success status
     */
    public function pushEvent(string $eventType, array $data): bool;

    /**
     * Get configuration details about the integration
     *
     * @return array Status, active scripts, configuration info
     */
    public function getStatus(): array;

    /**
     * Validate event data structure
     *
     * @param string $eventType
     * @param array $data
     * @return bool
     */
    public function validateEventData(string $eventType, array $data): bool;
}
