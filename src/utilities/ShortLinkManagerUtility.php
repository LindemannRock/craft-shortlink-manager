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
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * ShortLink Manager Utility
 */
class ShortLinkManagerUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->pluginName ?? 'ShortLink Manager';
        return $pluginName;
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
        return '@app/icons/tool.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $pluginName = $settings->pluginName ?? 'ShortLink Manager';

        // Get system stats using direct queries
        $totalLinks = (new \craft\db\Query())
            ->from('{{%shortlinkmanager_links}}')
            ->count();

        // Get active links count (use element query)
        $activeLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
            ->status('enabled')
            ->count();

        // Get analytics data
        $analyticsData = [];
        $totalClicks = 0;
        $qrScans = 0;
        $directClicks = 0;

        if ($settings->enableAnalytics) {
            $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary('last7days');
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

        // Get cache file counts
        $qrCacheFiles = 0;
        $deviceCacheFiles = 0;

        if ($settings->enableQrCodeCache) {
            $qrPath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';
            if (is_dir($qrPath)) {
                $qrCacheFiles = count(glob($qrPath . '*.cache'));
            }
        }

        if ($settings->cacheDeviceDetection) {
            $devicePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/devices/';
            if (is_dir($devicePath)) {
                $deviceCacheFiles = count(glob($devicePath . '*.cache'));
            }
        }

        return Craft::$app->getView()->renderTemplate('shortlink-manager/utilities/index', [
            'pluginName' => $pluginName,
            'settings' => $settings,
            'totalLinks' => $totalLinks,
            'activeLinks' => $activeLinks,
            'totalClicks' => $totalClicks,
            'qrScans' => $qrScans,
            'directClicks' => $directClicks,
            'qrCacheFiles' => $qrCacheFiles,
            'deviceCacheFiles' => $deviceCacheFiles,
        ]);
    }
}

