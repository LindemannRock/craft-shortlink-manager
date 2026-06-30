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
use craft\db\Query;
use craft\models\Site;
use lindemannrock\base\helpers\CacheHelper;
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
        $siteSelection = self::siteSelection();
        $selectedSiteIds = $siteSelection['siteIds'];

        // Get system stats only if user can view links
        $totalLinks = 0;
        $activeLinks = 0;
        $pendingLinks = 0;
        $expiredLinks = 0;
        $disabledLinks = 0;

        if ($user->getIdentity() && $user->checkPermission('shortLinkManager:manageLinks')) {
            $linkStats = self::linkStatusCounts($selectedSiteIds);
            $totalLinks = $linkStats['totalLinks'];
            $activeLinks = $linkStats['activeLinks'];
            $pendingLinks = $linkStats['pendingLinks'];
            $expiredLinks = $linkStats['expiredLinks'];
            $disabledLinks = $linkStats['disabledLinks'];
        }

        // Get analytics data only if user can view analytics
        $totalClicks = 0;
        $qrScans = 0;
        $directClicks = 0;

        if ($settings->enableAnalytics && $user->getIdentity() && $user->checkPermission('shortLinkManager:viewAnalytics')) {
            $analyticsStats = self::analyticsStats($selectedSiteIds);
            $totalClicks = $analyticsStats['totalClicks'];
            $qrScans = $analyticsStats['qrScans'];
            $directClicks = $analyticsStats['directClicks'];
        }

        // Get cache counts only if user can clear cache
        $qrCacheFiles = 0;
        $deviceCacheFiles = 0;

        if ($user->getIdentity() && $user->checkPermission('shortLinkManager:clearCache') && $settings->cacheStorageMethod === 'file') {
            if ($settings->enableQrCodeCache) {
                $qrCacheFiles = CacheHelper::countCacheFiles(PluginHelper::getCachePath(ShortLinkManager::$plugin, 'qr'));
            }

            if ($settings->cacheDeviceDetection) {
                $deviceCacheFiles = CacheHelper::countCacheFiles(PluginHelper::getCachePath(ShortLinkManager::$plugin, 'device'));
            }
        }

        return Craft::$app->getView()->renderTemplate('shortlink-manager/utilities/index', [
            'pluginName' => $pluginName,
            'settings' => $settings,
            'linksName' => $settings->getPluralLowerDisplayName(),
            'servdStaticCacheAvailable' => ShortLinkManager::$plugin->servdStaticCache->isAvailable(),
            'selectedSiteHandle' => $siteSelection['selectedSiteHandle'],
            'selectedSiteLabel' => $siteSelection['selectedSiteLabel'],
            'siteOptions' => $siteSelection['siteOptions'],
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

    /**
     * @param list<int> $siteIds
     * @return array{totalLinks: int, activeLinks: int, pendingLinks: int, expiredLinks: int, disabledLinks: int}
     */
    private static function linkStatusCounts(array $siteIds): array
    {
        return [
            'totalLinks' => (int) \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($siteIds)
                ->status(null)
                ->count(),
            'activeLinks' => (int) \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($siteIds)
                ->status('enabled')
                ->count(),
            'pendingLinks' => (int) \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($siteIds)
                ->status('pending')
                ->count(),
            'expiredLinks' => (int) \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($siteIds)
                ->status('expired')
                ->count(),
            'disabledLinks' => (int) \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->siteId($siteIds)
                ->status('disabled')
                ->count(),
        ];
    }

    /**
     * @param list<int> $siteIds
     * @return array{totalClicks: int, qrScans: int, directClicks: int}
     */
    private static function analyticsStats(array $siteIds): array
    {
        $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary('last7days', null, $siteIds);
        $totalClicks = $analyticsData['totalClicks'] ?? 0;
        $qrScans = 0;
        $directClicks = 0;

        $sourceRowsQuery = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->select(['metadata'])
            ->where(['siteId' => $siteIds]);
        ShortLinkManager::$plugin->analytics->applyDateRangeFilter($sourceRowsQuery, 'last7days');

        foreach ($sourceRowsQuery->all() as $click) {
            $source = 'direct';
            if (!empty($click['metadata'])) {
                $metadata = json_decode($click['metadata'], true);
                if (is_array($metadata)) {
                    $source = $metadata['source'] ?? 'direct';
                }
            }
            if ($source === 'qr') {
                $qrScans++;
            } else {
                $directClicks++;
            }
        }

        return [
            'totalClicks' => (int) $totalClicks,
            'qrScans' => $qrScans,
            'directClicks' => $directClicks,
        ];
    }

    /**
     * Resolve the utility overview site selector from the `site` query param.
     *
     * Missing, empty, `all`, invalid, and disabled handles all map to the
     * aggregate enabled-site scope.
     *
     * @return array{selectedSiteHandle: string, selectedSiteLabel: string, siteOptions: array<string, string>, siteIds: list<int>}
     */
    private static function siteSelection(): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $enabledSiteIds = $settings->getEnabledSiteIds();
        $siteOptions = [
            'all' => Craft::t('lindemannrock-base', 'All Sites'),
        ];
        $sitesByHandle = [];

        foreach ($enabledSiteIds as $siteId) {
            $site = Craft::$app->getSites()->getSiteById((int) $siteId);
            if (!$site instanceof Site) {
                continue;
            }

            $siteOptions[$site->handle] = $site->name ?: $site->handle;
            $sitesByHandle[$site->handle] = $site;
        }

        $siteIds = array_map(
            static fn(Site $site): int => (int) $site->id,
            array_values($sitesByHandle),
        );

        $selectedSiteHandle = 'all';
        $selectedSiteLabel = $siteOptions['all'];
        $requestedSite = self::requestedSiteHandle();

        if ($requestedSite !== '' && $requestedSite !== 'all' && isset($sitesByHandle[$requestedSite])) {
            $site = $sitesByHandle[$requestedSite];
            $selectedSiteHandle = $site->handle;
            $selectedSiteLabel = $site->name ?: $site->handle;
            $siteIds = [(int) $site->id];
        }

        return [
            'selectedSiteHandle' => $selectedSiteHandle,
            'selectedSiteLabel' => $selectedSiteLabel,
            'siteOptions' => $siteOptions,
            'siteIds' => $siteIds,
        ];
    }

    private static function requestedSiteHandle(): string
    {
        $request = Craft::$app->getRequest();
        $site = method_exists($request, 'getQueryParam')
            ? $request->getQueryParam('site', 'all')
            : $request->getParam('site', 'all');

        return trim((string) $site);
    }
}
