<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\utilities;

use Craft;
use craft\base\Utility;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * ShortLink Manager Utility
 *
 * @since 5.0.0
 */
class ShortLinkManagerUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getFullName();
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'shortlink-manager';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@lindemannrock/shortlinkmanager/icon-mask.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $pluginName = $settings->getFullName();
        $user = Craft::$app->getUser();

        // Get system stats only if user can view links
        $totalLinks = 0;
        $activeLinks = 0;
        $pendingLinks = 0;
        $expiredLinks = 0;
        $disabledLinks = 0;

        if ($user->getIdentity() && $user->checkPermission('shortLinkManager:manageLinks')) {
            $allowedSiteIds = array_map(fn($s) => $s->id, ShortLinkManager::$plugin->getEnabledSites());

            $totalLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($allowedSiteIds)
                ->status(null)
                ->count();

            $activeLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($allowedSiteIds)
                ->status('enabled')
                ->count();

            $pendingLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($allowedSiteIds)
                ->status('pending')
                ->count();

            $expiredLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($allowedSiteIds)
                ->status('expired')
                ->count();

            $disabledLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($allowedSiteIds)
                ->status('disabled')
                ->count();
        }

        // Get analytics data only if user can view analytics
        $totalClicks = 0;
        $qrScans = 0;
        $directClicks = 0;

        if ($settings->enableAnalytics && $user->getIdentity() && $user->checkPermission('shortLinkManager:viewAnalytics')) {
            $allowedSiteIds = array_map(fn($s) => $s->id, ShortLinkManager::$plugin->getEnabledSites());
            $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary('last7days', null, $allowedSiteIds);
            $totalClicks = $analyticsData['totalClicks'] ?? 0;

            // Count QR scans vs direct clicks from recent clicks
            $recentClicks = $analyticsData['recentClicks'] ?? [];
            foreach ($recentClicks as $click) {
                $source = 'direct';
                if (!empty($click['metadata'])) {
                    $metadata = json_decode($click['metadata'], true);
                    $source = $metadata['source'] ?? 'direct';
                }
                if ($source === 'qr') {
                    $qrScans++;
                } else {
                    $directClicks++;
                }
            }
        }

        // Get cache counts only if user can clear cache
        $qrCacheFiles = 0;
        $deviceCacheFiles = 0;

        if ($user->getIdentity() && $user->checkPermission('shortLinkManager:clearCache') && $settings->cacheStorageMethod === 'file') {
            if ($settings->enableQrCodeCache) {
                $qrPath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'qr');
                if (is_dir($qrPath)) {
                    $qrCacheFiles = count(glob($qrPath . '*.cache'));
                }
            }

            if ($settings->cacheDeviceDetection) {
                $devicePath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'device');
                if (is_dir($devicePath)) {
                    $deviceCacheFiles = count(glob($devicePath . '*.cache'));
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('shortlink-manager/utilities/index', [
            'pluginName' => $pluginName,
            'settings' => $settings,
            'totalLinks' => $totalLinks,
            'activeLinks' => $activeLinks,
            'pendingLinks' => $pendingLinks,
            'expiredLinks' => $expiredLinks,
            'disabledLinks' => $disabledLinks,
            'totalClicks' => $totalClicks,
            'qrScans' => $qrScans,
            'directClicks' => $directClicks,
            'qrCacheFiles' => $qrCacheFiles,
            'deviceCacheFiles' => $deviceCacheFiles,
        ]);
    }
}
