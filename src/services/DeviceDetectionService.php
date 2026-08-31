<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use craft\base\Component;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\traits\DeviceDetectionTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Device Detection Service
 *
 * Uses Matomo DeviceDetector library for accurate device, browser, and OS detection
 *
 * @since 5.14.0
 */
class DeviceDetectionService extends Component
{
    use LoggingTrait;
    use DeviceDetectionTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Detect device information from user agent
     *
     * @param string|null $userAgent
     * @return array Device information array
     */
    public function detectDevice(?string $userAgent = null): array
    {
        return $this->detectDeviceInfo($userAgent);
    }

    /**
     * Check if device is mobile (phone or tablet)
     *
     * @param array $deviceInfo
     * @return bool
     */
    public function isMobileDevice(array $deviceInfo): bool
    {
        return in_array($deviceInfo['deviceType'] ?? '', ['mobile', 'tablet', 'smartphone', 'phablet']);
    }

    /**
     * Check if device is a tablet
     *
     * @param array $deviceInfo
     * @return bool
     */
    public function isTablet(array $deviceInfo): bool
    {
        return ($deviceInfo['deviceType'] ?? '') === 'tablet';
    }

    /**
     * Check if device is desktop
     *
     * @param array $deviceInfo
     * @return bool
     */
    public function isDesktop(array $deviceInfo): bool
    {
        return ($deviceInfo['deviceType'] ?? 'desktop') === 'desktop';
    }

    /**
     * Clear cached device detection results and the request-local detector.
     *
     * @since 5.28.4
     */
    public function clearCache(?DisposableCacheStorageDecision $decision = null): int
    {
        try {
            return ShortLinkManager::$plugin->cacheStorage->clearFamily(CacheStorageService::FAMILY_DEVICE, $decision);
        } finally {
            $this->deviceDetection = null;
        }
    }

    /**
     * @inheritdoc
     */
    protected function getDeviceDetectionConfig(): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $decision = ShortLinkManager::$plugin->cacheStorage->getStorageDecision();

        return [
            'cacheEnabled' => (bool) $settings->cacheDeviceDetection && !$decision->isDisabled(),
            'cacheStorageMethod' => $decision->usesApplicationCache() ? 'craft' : 'file',
            'cacheDuration' => (int) $settings->deviceDetectionCacheDuration,
            'pluginHandle' => ShortLinkManager::$plugin->id,
            'cachePath' => $decision->usesFileCache()
                ? PluginHelper::getCachePath(ShortLinkManager::$plugin, 'device')
                : null,
            'cacheKeyPrefix' => PluginHelper::getCacheKeyPrefix(ShortLinkManager::$plugin->id, 'device'),
            'includeLanguage' => true,
            'includePlatform' => false,
        ];
    }
}
