<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use craft\base\Component;
use lindemannrock\base\helpers\CacheHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Clears plugin-owned QR and device-detection caches from local storage.
 *
 * @since 5.25.0
 */
class LocalCacheService extends Component
{
    private const QR_CACHE_KEY_TYPE = 'qr';
    private const QR_CACHE_DIRECTORY = 'qr';
    private const DEVICE_CACHE_KEY_TYPE = 'device';
    private const DEVICE_CACHE_DIRECTORY = 'device';

    /**
     * Clear cached QR code entries from the configured local cache backend.
     */
    public function clearQrCache(): int
    {
        return $this->clearCacheType(self::QR_CACHE_KEY_TYPE, self::QR_CACHE_DIRECTORY);
    }

    /**
     * Clear cached device-detection entries from the configured local cache backend.
     */
    public function clearDeviceCache(): int
    {
        return $this->clearCacheType(self::DEVICE_CACHE_KEY_TYPE, self::DEVICE_CACHE_DIRECTORY);
    }

    /**
     * Clear all plugin-owned local cache entries.
     */
    public function clearAllCaches(): int
    {
        return $this->clearQrCache() + $this->clearDeviceCache();
    }

    /**
     * Clear one local cache namespace from the active cache backend.
     */
    private function clearCacheType(string $redisKeyType, string $fileDirectory): int
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        if ($settings->cacheStorageMethod === 'redis') {
            return CacheHelper::clearTrackedRedisKeys(ShortLinkManager::$plugin->id, $redisKeyType);
        }

        return CacheHelper::clearCacheFiles(PluginHelper::getCachePath(ShortLinkManager::$plugin, $fileDirectory));
    }
}
