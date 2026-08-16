<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use craft\base\Component;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Clears plugin-owned QR and device-detection caches from local storage.
 *
 * @since 5.25.0
 */
class LocalCacheService extends Component
{
    /**
     * Clear cached QR code entries from the configured local cache backend.
     */
    public function clearQrCache(?DisposableCacheStorageDecision $decision = null): int
    {
        return ShortLinkManager::$plugin->cacheStorage->clearFamily(CacheStorageService::FAMILY_QR, $decision);
    }

    /**
     * Clear cached device-detection entries from the configured local cache backend.
     */
    public function clearDeviceCache(?DisposableCacheStorageDecision $decision = null): int
    {
        return ShortLinkManager::$plugin->deviceDetection->clearCache($decision);
    }

    /**
     * Clear all plugin-owned local cache entries.
     */
    public function clearAllCaches(?DisposableCacheStorageDecision $decision = null): int
    {
        return $this->clearQrCache($decision) + $this->clearDeviceCache($decision);
    }
}
