<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use craft\base\Component;
use craft\db\Query;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\services\analytics\AnalyticsBreakdownService;
use lindemannrock\shortlinkmanager\services\analytics\AnalyticsExportService;
use lindemannrock\shortlinkmanager\services\analytics\AnalyticsQueryInsightsService;
use lindemannrock\shortlinkmanager\services\analytics\AnalyticsTrackingService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Request;

/**
 * Analytics Service
 *
 * Facade that delegates to focused sub-services for analytics functionality.
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.7.0
 */
class AnalyticsService extends Component
{
    use LoggingTrait;
    use GeoLookupTrait;

    private AnalyticsTrackingService $_tracking;
    private AnalyticsQueryInsightsService $_queryInsights;
    private AnalyticsBreakdownService $_breakdown;
    private AnalyticsExportService $_export;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);

        $this->_tracking = new AnalyticsTrackingService();
        $this->_queryInsights = new AnalyticsQueryInsightsService();
        $this->_breakdown = new AnalyticsBreakdownService();
        $this->_export = new AnalyticsExportService();
    }

    // =========================================================================
    // TRACKING
    // =========================================================================

    public function trackClick(ShortLink $shortLink, Request $request, string $source = 'direct'): void
    {
        $this->_tracking->trackClick($shortLink, $request, $source);
    }

    // =========================================================================
    // QUERY INSIGHTS
    // =========================================================================

    public function getClickStats(int $shortLinkId, array $filters = []): array
    {
        return $this->_queryInsights->getClickStats($shortLinkId, $filters);
    }

    public function getLinkAnalytics(int $shortLinkId, string $dateRange = 'last7days', int|array|null $siteId = null): array
    {
        return $this->_queryInsights->getLinkAnalytics($shortLinkId, $dateRange, $siteId);
    }

    public function getAllRecentClicks(string $dateRange = 'last7days', int $limit = 20, int|array|null $siteId = null): array
    {
        return $this->_queryInsights->getAllRecentClicks($dateRange, $limit, $siteId);
    }

    public function getTopLinks(int $limit = 10, string $dateRange = 'last7days', int|array|null $siteId = null): array
    {
        return $this->_queryInsights->getTopLinks($limit, $dateRange, $siteId);
    }

    public function getClicksData(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_queryInsights->getClicksData($shortLinkId, $dateRange, $siteId);
    }

    public function getHourlyAnalytics(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_queryInsights->getHourlyAnalytics($shortLinkId, $dateRange, $siteId);
    }

    // =========================================================================
    // BREAKDOWNS
    // =========================================================================

    public function getDeviceBreakdown(int $shortLinkId): array
    {
        return $this->_breakdown->getDeviceBreakdown($shortLinkId);
    }

    public function getDeviceTypeBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getDeviceTypeBreakdown($shortLinkId, $dateRange, $siteId);
    }

    public function getDeviceBrandBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getDeviceBrandBreakdown($shortLinkId, $dateRange, $siteId);
    }

    public function getTrafficTypeBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getTrafficTypeBreakdown($shortLinkId, $dateRange, $siteId);
    }

    public function getTopAgents(?int $shortLinkId, string $dateRange, int $limit = 10, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getTopAgents($shortLinkId, $dateRange, $limit, $siteId);
    }

    public function getBrowserBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getBrowserBreakdown($shortLinkId, $dateRange, $siteId);
    }

    public function getOsBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getOsBreakdown($shortLinkId, $dateRange, $siteId);
    }

    public function getGeoBreakdown(int $shortLinkId): array
    {
        return $this->_breakdown->getGeoBreakdown($shortLinkId);
    }

    public function getReferrerBreakdown(int $shortLinkId): array
    {
        return $this->_breakdown->getReferrerBreakdown($shortLinkId);
    }

    public function getTopCountries(?int $shortLinkId, string $dateRange, int $limit = 10, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getTopCountries($shortLinkId, $dateRange, $limit, $siteId);
    }

    public function getTopCities(?int $shortLinkId, string $dateRange, int $limit = 15, int|array|null $siteId = null): array
    {
        return $this->_breakdown->getTopCities($shortLinkId, $dateRange, $limit, $siteId);
    }

    public function getLocationFromIp(string $ip): ?array
    {
        return $this->_breakdown->getLocationFromIp($ip);
    }

    // =========================================================================
    // EXPORT & MAINTENANCE
    // =========================================================================

    /**
     * Get analytics summary
     *
     * Orchestrates data from multiple sub-services into a complete summary.
     *
     * @param string $dateRange
     * @param int|null $shortLinkId
     * @param int|int[]|null $siteId
     * @return array
     */
    public function getAnalyticsSummary(string $dateRange = 'last7days', ?int $shortLinkId = null, int|array|null $siteId = null): array
    {
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}}');

        // Apply date range filter
        $this->applyDateRangeFilter($query, $dateRange);

        // Filter by link if specified
        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        // Filter by site if specified
        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $totalClicks = (int) $query->count();
        $uniqueVisitors = (int) (clone $query)->select('COUNT(DISTINCT ip)')->scalar();

        // Get active links count (use element query to check enabled status properly)
        if ($siteId === []) {
            $activeLinks = 0;
        } else {
            $activeLinksQuery = ShortLink::find()
                ->status('enabled');
            if ($siteId !== null) {
                $activeLinksQuery->siteId($siteId);
            }
            $activeLinks = $activeLinksQuery->count();
        }

        // Get total links
        $totalLinksQuery = (new Query())
            ->from('{{%shortlinkmanager}}');
        $totalLinks = $totalLinksQuery->count();

        // Get count of links that have been clicked in this period
        $shortLinksQuery = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->select('COUNT(DISTINCT linkId)');

        $this->applyDateRangeFilter($shortLinksQuery, $dateRange);
        if ($siteId !== null) {
            $shortLinksQuery->andWhere(['siteId' => $siteId]);
        }
        $shortLinksWithClicks = (int) $shortLinksQuery->scalar();

        // Calculate percentage
        $shortLinksUsedPercentage = $activeLinks > 0 ? min(100, round(($shortLinksWithClicks / $activeLinks) * 100, 0)) : 0;

        return [
            'totalClicks' => $totalClicks,
            'uniqueVisitors' => $uniqueVisitors,
            'activeLinks' => $activeLinks,
            'totalLinks' => $totalLinks,
            'linksUsed' => $shortLinksWithClicks,
            'linksUsedPercentage' => $shortLinksUsedPercentage,
            'topLinks' => $this->getTopLinks(20, $dateRange, $siteId),
        ];
    }

    public function getExportData(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        return $this->_export->getExportData($shortLinkId, $dateRange, $siteId);
    }

    public function cleanupOldAnalytics(): int
    {
        return $this->_export->cleanupOldAnalytics();
    }

    // =========================================================================
    // SHARED UTILITIES
    // =========================================================================

    /**
     * Apply date range filter to query
     *
     * @param Query $query
     * @param string $dateRange
     * @param string $column
     */
    public function applyDateRangeFilter(Query $query, string $dateRange, string $column = 'dateCreated'): void
    {
        DateRangeHelper::applyToQuery($query, $dateRange, $column);
    }

    /**
     * Get geo lookup configuration from plugin settings
     *
     * @return array<string, mixed>
     */
    protected function getGeoConfig(): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        return [
            'provider' => $settings->geoProvider ?? 'ip-api.com',
            'apiKey' => $settings->geoApiKey ?? null,
            'logCategory' => ShortLinkManager::$plugin->id,
        ];
    }
}
