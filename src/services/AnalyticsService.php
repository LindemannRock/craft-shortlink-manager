<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\AnalyticsRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\Request;

/**
 * Analytics Service
 */
class AnalyticsService extends Component
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('shortlink-manager');
    }

    /**
     * Track a click
     *
     * @param ShortLink $shortLink
     * @param Request $request
     * @param string $source Source of the click (qr, direct, etc.)
     * @return void
     */
    public function trackClick(ShortLink $shortLink, Request $request, string $source = 'direct'): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        if (!$settings->enableAnalytics) {
            return;
        }

        $record = new AnalyticsRecord();
        $record->linkId = $shortLink->id;
        $record->siteId = $shortLink->siteId;

        // Get IP address
        $ip = $request->getUserIP();

        // Step 1: Anonymize IP if enabled (subnet masking BEFORE hashing)
        if ($settings->anonymizeIpAddress && $ip) {
            $ip = $this->_anonymizeIp($ip);
        }

        // Step 2: Get geo location (uses anonymized or full IP)
        if ($settings->enableGeoDetection && $ip) {
            $this->getGeoData($record, $ip);
        }

        // Step 3: Hash IP with salt for storage
        if ($ip) {
            try {
                $record->ip = $this->_hashIpWithSalt($ip);
            } catch (\Exception $e) {
                $this->logError('Failed to hash IP address', ['error' => $e->getMessage()]);
                $record->ip = null;  // Continue without IP
            }
        } else {
            $record->ip = null;
        }

        // Get user agent
        $record->userAgent = $request->getUserAgent();

        // Get referrer
        $record->referer = $request->getReferrer();

        // Detect device/browser info using Matomo DeviceDetector
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice($record->userAgent);

        // Get language from device detection (includes fallback logic)
        $record->language = $deviceInfo['language'] ?? null;

        // Populate record with device detection data
        $record->deviceType = $deviceInfo['deviceType'];
        $record->deviceBrand = $deviceInfo['deviceBrand'];
        $record->deviceModel = $deviceInfo['deviceModel'];
        $record->browser = $deviceInfo['browser'];
        $record->browserVersion = $deviceInfo['browserVersion'];
        $record->browserEngine = $deviceInfo['browserEngine'];
        $record->osName = $deviceInfo['osName'];
        $record->osVersion = $deviceInfo['osVersion'];
        $record->clientType = $deviceInfo['clientType'];
        $record->isRobot = $deviceInfo['isRobot'];
        $record->isMobileApp = $deviceInfo['isMobileApp'];
        $record->botName = $deviceInfo['botName'];

        // Store source in metadata (like Smart Links does)
        $metadata = [
            'source' => $source,
        ];
        $record->metadata = json_encode($metadata);

        $record->save();
    }

    /**
     * Get click statistics for a link
     *
     * @param int $shortLinkId
     * @param array $filters
     * @return array
     */
    public function getClickStats(int $shortLinkId, array $filters = []): array
    {
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId]);

        // Apply filters
        if (isset($filters['days'])) {
            $date = new \DateTime();
            $date->modify('-' . $filters['days'] . ' days');
            $query->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($date)]);
        }

        if (isset($filters['startDate'])) {
            $query->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($filters['startDate'])]);
        }

        if (isset($filters['endDate'])) {
            $query->andWhere(['<=', 'dateCreated', Db::prepareDateForDb($filters['endDate'])]);
        }

        // Get total clicks
        $totalClicks = $query->count();

        // Get clicks over time
        $clicksByDate = (new Query())
            ->select(['DATE(dateCreated) as date', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->groupBy('DATE(dateCreated)')
            ->orderBy(['date' => SORT_ASC])
            ->all();

        // Get device breakdown
        $deviceBreakdown = (new Query())
            ->select(['deviceType', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['deviceType' => null]])
            ->groupBy('deviceType')
            ->orderBy(['count' => SORT_DESC])
            ->all();

        // Get browser breakdown
        $browserBreakdown = (new Query())
            ->select(['browser', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['browser' => null]])
            ->groupBy('browser')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->all();

        // Get referrer breakdown
        $referrerBreakdown = (new Query())
            ->select(['referer', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['referer' => null]])
            ->groupBy('referer')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->all();

        // Get geo breakdown if enabled
        $geoBreakdown = [];
        if (ShortLinkManager::$plugin->getSettings()->enableGeoDetection) {
            $geoBreakdown = (new Query())
                ->select(['country', 'COUNT(*) as count'])
                ->from('{{%shortlinkmanager_analytics}}')
                ->where(['linkId' => $shortLinkId])
                ->andWhere(['not', ['country' => null]])
                ->groupBy('country')
                ->orderBy(['count' => SORT_DESC])
                ->limit(20)
                ->all();
        }

        return [
            'totalClicks' => $totalClicks,
            'clicksByDate' => $clicksByDate,
            'deviceBreakdown' => $deviceBreakdown,
            'browserBreakdown' => $browserBreakdown,
            'referrerBreakdown' => $referrerBreakdown,
            'geoBreakdown' => $geoBreakdown,
        ];
    }

    /**
     * Get top performing links
     *
     * @param int $limit
     * @param array $filters
     * @return array
     */
    public function getTopLinks(int $limit = 10, string $dateRange = 'last7days'): array
    {
        $query = (new Query())
            ->select(['l.id', 'l.code', 'l.slug', 'c.destinationUrl', 'COUNT(a.id) as clicks', 'MAX(a.dateCreated) as lastClick'])
            ->from('{{%shortlinkmanager}} l')
            ->leftJoin('{{%shortlinkmanager_analytics}} a', 'a.linkId = l.id')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = l.id AND c.siteId = 1')
            ->groupBy('l.id, c.destinationUrl')
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit);

        // Apply date range filter to analytics table
        $this->applyDateRangeFilter($query, $dateRange, 'a.dateCreated');

        return $query->all();
    }

    /**
     * Get device breakdown
     *
     * @param int $shortLinkId
     * @return array
     */
    public function getDeviceBreakdown(int $shortLinkId): array
    {
        return (new Query())
            ->select(['deviceType', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['deviceType' => null]])
            ->groupBy('deviceType')
            ->orderBy(['count' => SORT_DESC])
            ->all();
    }

    /**
     * Get geo breakdown
     *
     * @param int $shortLinkId
     * @return array
     */
    public function getGeoBreakdown(int $shortLinkId): array
    {
        return (new Query())
            ->select(['country', 'city', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['country' => null]])
            ->groupBy(['country', 'city'])
            ->orderBy(['count' => SORT_DESC])
            ->limit(50)
            ->all();
    }

    /**
     * Get referrer breakdown
     *
     * @param int $shortLinkId
     * @return array
     */
    public function getReferrerBreakdown(int $shortLinkId): array
    {
        return (new Query())
            ->select(['referer', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['referer' => null]])
            ->groupBy('referer')
            ->orderBy(['count' => SORT_DESC])
            ->limit(20)
            ->all();
    }

    /**
     * Clean up old analytics
     *
     * @return int Number of deleted records
     */
    public function cleanupOldAnalytics(): int
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        if ($settings->analyticsRetention <= 0) {
            return 0;
        }

        $date = new \DateTime();
        $date->modify('-' . $settings->analyticsRetention . ' days');

        $deleted = Craft::$app->getDb()->createCommand()
            ->delete('{{%shortlinkmanager_analytics}}', ['<', 'dateCreated', Db::prepareDateForDb($date)])
            ->execute();

        $this->logInfo('Cleaned up old analytics', ['deleted' => $deleted]);

        return $deleted;
    }

    /**
     * Get analytics summary
     *
     * @param string $dateRange
     * @param int|null $shortLinkId
     * @return array
     */
    public function getAnalyticsSummary(string $dateRange = 'last7days', ?int $shortLinkId = null): array
    {
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}}');

        // Apply date range filter
        $this->applyDateRangeFilter($query, $dateRange);

        // Filter by link if specified
        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $totalClicks = (int) $query->count();
        $uniqueVisitors = (int) $query->select('COUNT(DISTINCT ip)')->scalar();

        // Get active links count (use element query to check enabled status properly)
        $activeLinks = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
            ->status('enabled')
            ->count();

        // Get total links
        $totalLinks = (new Query())
            ->from('{{%shortlinkmanager}}')
            ->count();

        // Get count of links that have been clicked in this period
        $shortLinksQuery = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->select('COUNT(DISTINCT linkId)');

        $this->applyDateRangeFilter($shortLinksQuery, $dateRange);
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
            'topLinks' => $this->getTopLinks(20, $dateRange),
            'topCountries' => $this->getTopCountries(null, $dateRange),
            'topCities' => $this->getTopCities(null, $dateRange),
            'recentClicks' => $this->getAllRecentClicks($dateRange, 20),
        ];
    }

    /**
     * Apply date range filter to query
     *
     * @param Query $query
     * @param string $dateRange
     * @param string $column
     * @return void
     */
    public function applyDateRangeFilter(Query $query, string $dateRange, string $column = 'dateCreated'): void
    {
        $now = new \DateTime();

        switch ($dateRange) {
            case 'today':
                $start = (clone $now)->setTime(0, 0, 0);
                $query->andWhere(['>=', $column, Db::prepareDateForDb($start)]);
                break;

            case 'yesterday':
                $start = (clone $now)->modify('-1 day')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(0, 0, 0);
                $query->andWhere(['>=', $column, Db::prepareDateForDb($start)])
                      ->andWhere(['<', $column, Db::prepareDateForDb($end)]);
                break;

            case 'last7days':
                $start = (clone $now)->modify('-7 days');
                $query->andWhere(['>=', $column, Db::prepareDateForDb($start)]);
                break;

            case 'last30days':
                $start = (clone $now)->modify('-30 days');
                $query->andWhere(['>=', $column, Db::prepareDateForDb($start)]);
                break;

            case 'last90days':
                $start = (clone $now)->modify('-90 days');
                $query->andWhere(['>=', $column, Db::prepareDateForDb($start)]);
                break;

            case 'all':
                // No filter
                break;
        }
    }

    /**
     * Get analytics for a specific link
     *
     * @param int $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getLinkAnalytics(int $shortLinkId, string $dateRange = 'last7days'): array
    {
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId]);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $dateRange);

        // Get total and unique clicks
        $totalClicks = (int) $query->count();
        $uniqueClicks = (int) (clone $query)->select('COUNT(DISTINCT ip)')->scalar();

        // Calculate average clicks per day
        $daysCount = $this->_getDaysCount($dateRange);
        $averageClicksPerDay = $daysCount > 0 ? round($totalClicks / $daysCount, 2) : 0;

        // Get device breakdown
        $deviceResults = (clone $query)
            ->select(['deviceType', 'COUNT(*) as count'])
            ->groupBy('deviceType')
            ->all();

        $deviceBreakdown = [];
        foreach ($deviceResults as $row) {
            if (!empty($row['deviceType'])) {
                $deviceBreakdown[$row['deviceType']] = (int) $row['count'];
            }
        }

        // Get browser breakdown
        $browserResults = (clone $query)
            ->select(['browser', 'COUNT(*) as count'])
            ->groupBy('browser')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->all();

        $browserBreakdown = [];
        foreach ($browserResults as $row) {
            if (!empty($row['browser'])) {
                $browserBreakdown[$row['browser']] = (int) $row['count'];
            }
        }

        // Get OS breakdown
        $osResults = (clone $query)
            ->select(['osName', 'COUNT(*) as count'])
            ->groupBy('osName')
            ->all();

        $osBreakdown = [];
        foreach ($osResults as $row) {
            if (!empty($row['osName'])) {
                $osBreakdown[$row['osName']] = (int) $row['count'];
            }
        }

        // Get recent clicks for this link
        $recentClicks = (clone $query)
            ->select(['a.*', 'l.code as linkCode', 'l.slug', 'c.destinationUrl'])
            ->from('{{%shortlinkmanager_analytics}} a')
            ->innerJoin('{{%shortlinkmanager}} l', 'l.id = a.linkId')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = a.linkId AND c.siteId = a.siteId')
            ->where(['a.linkId' => $shortLinkId])
            ->orderBy(['a.dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        $this->applyDateRangeFilter($query, $dateRange, 'a.dateCreated');

        return [
            'totalClicks' => $totalClicks,
            'uniqueClicks' => $uniqueClicks,
            'averageClicksPerDay' => $averageClicksPerDay,
            'deviceBreakdown' => $deviceBreakdown,
            'browserBreakdown' => $browserBreakdown,
            'osBreakdown' => $osBreakdown,
            'recentClicks' => $recentClicks,
        ];
    }

    /**
     * Get number of days for a date range
     *
     * @param string $dateRange
     * @return int
     */
    private function _getDaysCount(string $dateRange): int
    {
        return match($dateRange) {
            'today' => 1,
            'yesterday' => 1,
            'last7days' => 7,
            'last30days' => 30,
            'last90days' => 90,
            default => 30,
        };
    }

    /**
     * Get all recent clicks
     *
     * @param string $dateRange
     * @param int $limit
     * @return array
     */
    public function getAllRecentClicks(string $dateRange = 'last7days', int $limit = 20): array
    {
        $query = (new Query())
            ->select(['a.*', 'l.code as linkCode', 'l.slug', 'c.destinationUrl'])
            ->from('{{%shortlinkmanager_analytics}} a')
            ->innerJoin('{{%shortlinkmanager}} l', 'l.id = a.linkId')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = a.linkId AND c.siteId = a.siteId')
            ->orderBy(['a.dateCreated' => SORT_DESC])
            ->limit($limit);

        $this->applyDateRangeFilter($query, $dateRange, 'a.dateCreated');

        $results = $query->all();

        // Parse metadata to extract source (qr, direct, etc.)
        foreach ($results as &$result) {
            if (!empty($result['metadata'])) {
                $metadata = json_decode($result['metadata'], true);
                $result['source'] = $metadata['source'] ?? 'direct';
            } else {
                $result['source'] = 'direct';
            }
        }

        return $results;
    }

    /**
     * Get top countries
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int $limit
     * @return array
     */
    public function getTopCountries(?int $shortLinkId, string $dateRange, int $limit = 10): array
    {
        $query = (new Query())
            ->select(['country', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['country' => null]])
            ->groupBy('country')
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();
        $total = array_sum(array_column($results, 'clicks'));

        // Add percentages and country names
        foreach ($results as &$result) {
            $result['percentage'] = $total > 0 ? round(($result['clicks'] / $total) * 100, 1) : 0;
            $result['name'] = $result['country']; // You could map country codes to names here
        }

        return $results;
    }

    /**
     * Get top cities
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int $limit
     * @return array
     */
    public function getTopCities(?int $shortLinkId, string $dateRange, int $limit = 15): array
    {
        $query = (new Query())
            ->select(['city', 'country', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['city' => null]])
            ->groupBy(['city', 'country'])
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();
        $total = array_sum(array_column($results, 'clicks'));

        // Add percentages and country names
        foreach ($results as &$result) {
            $result['percentage'] = $total > 0 ? round(($result['clicks'] / $total) * 100, 1) : 0;
            $result['countryName'] = $result['country']; // You could map country codes to names here
        }

        return $results;
    }

    /**
     * Get device brand breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getDeviceBrandBreakdown(?int $shortLinkId, string $dateRange): array
    {
        $query = (new Query())
            ->select(['deviceBrand', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['deviceBrand' => null]])
            ->groupBy('deviceBrand')
            ->orderBy(['clicks' => SORT_DESC])
            ->limit(10);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();

        return [
            'labels' => array_column($results, 'deviceBrand'),
            'values' => array_map('intval', array_column($results, 'clicks')),
        ];
    }

    /**
     * Get OS breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getOsBreakdown(?int $shortLinkId, string $dateRange): array
    {
        $query = (new Query())
            ->select(['osName', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['osName' => null]])
            ->groupBy('osName')
            ->orderBy(['clicks' => SORT_DESC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();
        $total = array_sum(array_column($results, 'clicks'));

        $percentages = [];
        foreach ($results as $result) {
            $percentages[] = $total > 0 ? round(($result['clicks'] / $total) * 100, 1) : 0;
        }

        return [
            'labels' => array_column($results, 'osName'),
            'values' => array_map('intval', array_column($results, 'clicks')),
            'percentages' => $percentages,
        ];
    }

    /**
     * Get browser breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getBrowserBreakdown(?int $shortLinkId, string $dateRange): array
    {
        $query = (new Query())
            ->select(['browser', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['browser' => null]])
            ->groupBy('browser')
            ->orderBy(['clicks' => SORT_DESC])
            ->limit(10);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();
        $total = array_sum(array_column($results, 'clicks'));

        $percentages = [];
        foreach ($results as $result) {
            $percentages[] = $total > 0 ? round(($result['clicks'] / $total) * 100, 1) : 0;
        }

        return [
            'labels' => array_column($results, 'browser'),
            'values' => array_map('intval', array_column($results, 'clicks')),
            'percentages' => $percentages,
        ];
    }

    /**
     * Get device type breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getDeviceTypeBreakdown(?int $shortLinkId, string $dateRange): array
    {
        $query = (new Query())
            ->select(['deviceType', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['deviceType' => null]])
            ->groupBy('deviceType')
            ->orderBy(['clicks' => SORT_DESC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();

        return [
            'labels' => array_map(function($type) {
                return ucfirst($type);
            }, array_column($results, 'deviceType')),
            'values' => array_map('intval', array_column($results, 'clicks')),
        ];
    }

    /**
     * Get clicks data for chart
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getClicksData(?int $shortLinkId, string $dateRange): array
    {
        $query = (new Query())
            ->select(['DATE(dateCreated) as date', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->groupBy('DATE(dateCreated)')
            ->orderBy(['date' => SORT_ASC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();

        return [
            'labels' => array_column($results, 'date'),
            'values' => array_map('intval', array_column($results, 'clicks')),
        ];
    }

    /**
     * Get hourly analytics
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @return array
     */
    public function getHourlyAnalytics(?int $shortLinkId, string $dateRange): array
    {
        $query = (new Query())
            ->select(['HOUR(dateCreated) as hour', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->groupBy('hour')
            ->orderBy(['hour' => SORT_ASC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();

        // Fill in missing hours with 0
        $hourlyData = array_fill(0, 24, 0);
        foreach ($results as $result) {
            $hourlyData[(int)$result['hour']] = (int)$result['clicks'];
        }

        // Find peak hour
        $peakHour = array_search(max($hourlyData), $hourlyData);
        $peakHourFormatted = sprintf('%02d:00', $peakHour);

        return [
            'data' => $hourlyData,
            'peakHour' => $peakHour,
            'peakHourFormatted' => $peakHourFormatted,
        ];
    }

    /**
     * Get location data from IP address
     *
     * @param string $ip
     * @return array|null
     */
    public function getLocationFromIp(string $ip): ?array
    {
        try {
            // Skip local/private IPs - return default location data for local development
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                // Check for environment variable to override default location
                // Defaults to Dubai, UAE if not set
                $defaultCountry = getenv('SHORTLINK_MANAGER_DEFAULT_COUNTRY') ?: 'AE';
                $defaultCity = getenv('SHORTLINK_MANAGER_DEFAULT_CITY') ?: 'Dubai';

                // Predefined locations for common cities worldwide
                $locations = [
                    'US' => [
                        'New York' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'New York', 'region' => 'New York', 'timezone' => 'America/New_York', 'lat' => 40.7128, 'lon' => -74.0060],
                        'Los Angeles' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'Los Angeles', 'region' => 'California', 'timezone' => 'America/Los_Angeles', 'lat' => 34.0522, 'lon' => -118.2437],
                        'Chicago' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'Chicago', 'region' => 'Illinois', 'timezone' => 'America/Chicago', 'lat' => 41.8781, 'lon' => -87.6298],
                        'San Francisco' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'San Francisco', 'region' => 'California', 'timezone' => 'America/Los_Angeles', 'lat' => 37.7749, 'lon' => -122.4194],
                    ],
                    'GB' => [
                        'London' => ['countryCode' => 'GB', 'country' => 'United Kingdom', 'city' => 'London', 'region' => 'England', 'timezone' => 'Europe/London', 'lat' => 51.5074, 'lon' => -0.1278],
                        'Manchester' => ['countryCode' => 'GB', 'country' => 'United Kingdom', 'city' => 'Manchester', 'region' => 'England', 'timezone' => 'Europe/London', 'lat' => 53.4808, 'lon' => -2.2426],
                    ],
                    'AE' => [
                        'Dubai' => ['countryCode' => 'AE', 'country' => 'United Arab Emirates', 'city' => 'Dubai', 'region' => 'Dubai', 'timezone' => 'Asia/Dubai', 'lat' => 25.2048, 'lon' => 55.2708],
                        'Abu Dhabi' => ['countryCode' => 'AE', 'country' => 'United Arab Emirates', 'city' => 'Abu Dhabi', 'region' => 'Abu Dhabi', 'timezone' => 'Asia/Dubai', 'lat' => 24.4539, 'lon' => 54.3773],
                    ],
                    'SA' => [
                        'Riyadh' => ['countryCode' => 'SA', 'country' => 'Saudi Arabia', 'city' => 'Riyadh', 'region' => 'Riyadh Province', 'timezone' => 'Asia/Riyadh', 'lat' => 24.7136, 'lon' => 46.6753],
                        'Jeddah' => ['countryCode' => 'SA', 'country' => 'Saudi Arabia', 'city' => 'Jeddah', 'region' => 'Makkah Province', 'timezone' => 'Asia/Riyadh', 'lat' => 21.5433, 'lon' => 39.1728],
                    ],
                    'DE' => [
                        'Berlin' => ['countryCode' => 'DE', 'country' => 'Germany', 'city' => 'Berlin', 'region' => 'Berlin', 'timezone' => 'Europe/Berlin', 'lat' => 52.5200, 'lon' => 13.4050],
                        'Munich' => ['countryCode' => 'DE', 'country' => 'Germany', 'city' => 'Munich', 'region' => 'Bavaria', 'timezone' => 'Europe/Berlin', 'lat' => 48.1351, 'lon' => 11.5820],
                    ],
                    'FR' => [
                        'Paris' => ['countryCode' => 'FR', 'country' => 'France', 'city' => 'Paris', 'region' => 'Île-de-France', 'timezone' => 'Europe/Paris', 'lat' => 48.8566, 'lon' => 2.3522],
                    ],
                    'CA' => [
                        'Toronto' => ['countryCode' => 'CA', 'country' => 'Canada', 'city' => 'Toronto', 'region' => 'Ontario', 'timezone' => 'America/Toronto', 'lat' => 43.6532, 'lon' => -79.3832],
                        'Vancouver' => ['countryCode' => 'CA', 'country' => 'Canada', 'city' => 'Vancouver', 'region' => 'British Columbia', 'timezone' => 'America/Vancouver', 'lat' => 49.2827, 'lon' => -123.1207],
                    ],
                    'AU' => [
                        'Sydney' => ['countryCode' => 'AU', 'country' => 'Australia', 'city' => 'Sydney', 'region' => 'New South Wales', 'timezone' => 'Australia/Sydney', 'lat' => -33.8688, 'lon' => 151.2093],
                        'Melbourne' => ['countryCode' => 'AU', 'country' => 'Australia', 'city' => 'Melbourne', 'region' => 'Victoria', 'timezone' => 'Australia/Melbourne', 'lat' => -37.8136, 'lon' => 144.9631],
                    ],
                    'JP' => [
                        'Tokyo' => ['countryCode' => 'JP', 'country' => 'Japan', 'city' => 'Tokyo', 'region' => 'Tokyo', 'timezone' => 'Asia/Tokyo', 'lat' => 35.6762, 'lon' => 139.6503],
                    ],
                    'SG' => [
                        'Singapore' => ['countryCode' => 'SG', 'country' => 'Singapore', 'city' => 'Singapore', 'region' => 'Singapore', 'timezone' => 'Asia/Singapore', 'lat' => 1.3521, 'lon' => 103.8198],
                    ],
                    'IN' => [
                        'Mumbai' => ['countryCode' => 'IN', 'country' => 'India', 'city' => 'Mumbai', 'region' => 'Maharashtra', 'timezone' => 'Asia/Kolkata', 'lat' => 19.0760, 'lon' => 72.8777],
                        'Delhi' => ['countryCode' => 'IN', 'country' => 'India', 'city' => 'Delhi', 'region' => 'Delhi', 'timezone' => 'Asia/Kolkata', 'lat' => 28.7041, 'lon' => 77.1025],
                    ],
                ];

                // Return the configured location if it exists
                if (isset($locations[$defaultCountry][$defaultCity])) {
                    return $locations[$defaultCountry][$defaultCity];
                }

                // If configuration not found, return null
                return null;
            }

            // Use ip-api.com (free, no API key required, 45 requests per minute)
            $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode,country,city,regionName,region,lat,lon,timezone";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'countryCode' => $data['countryCode'] ?? null,
                        'country' => $data['country'] ?? null,
                        'city' => $data['city'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null,
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logWarning('Failed to get location from IP', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Hash IP address with salt for privacy
     *
     * Uses SHA256 with a secret salt to hash IPs. This prevents rainbow table attacks
     * while still allowing unique visitor tracking (same IP = same hash).
     *
     * @param string $ip The IP address to hash
     * @return string Hashed IP address (64 characters)
     * @throws \Exception If salt is not configured
     */
    private function _hashIpWithSalt(string $ip): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $salt = $settings->ipHashSalt;

        if (!$salt || $salt === '$SHORTLINK_MANAGER_IP_SALT' || trim($salt) === '') {
            $this->logError('IP hash salt not configured - analytics tracking disabled', [
                'ip' => 'hidden',
                'saltValue' => $salt ?? 'NULL'
            ]);
            throw new \Exception('IP hash salt not configured. Run: php craft shortlink-manager/security/generate-salt');
        }

        return hash('sha256', $ip . $salt);
    }

    /**
     * Anonymize IP address (subnet masking)
     *
     * Masks IP addresses to reduce precision while maintaining subnet info for geo-location.
     * IPv4: Masks last octet (192.168.1.123 → 192.168.1.0)
     * IPv6: Masks last 80 bits (keeps first 48 bits)
     *
     * @param string $ip The IP address to anonymize
     * @return string Anonymized IP address
     */
    private function _anonymizeIp(string $ip): string
    {
        // IPv4: Mask last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        // IPv6: Mask last 80 bits (keep first 48 bits)
        elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binary = inet_pton($ip);
            $anonymized = substr($binary, 0, 6) . str_repeat("\0", 10);
            return inet_ntop($anonymized);
        }

        return $ip;
    }

    /**
     * Get geo data for IP
     *
     * @param AnalyticsRecord $record
     * @param string $ip
     * @return void
     */
    private function getGeoData(AnalyticsRecord $record, string $ip): void
    {
        $location = $this->getLocationFromIp($ip);

        if ($location) {
            $record->country = $location['countryCode'];
            $record->city = $location['city'];
            $record->region = $location['region'];
            $record->latitude = $location['lat'];
            $record->longitude = $location['lon'];
        }
    }

    /**
     * Export analytics data to CSV
     *
     * @param int|null $shortLinkId Optional link ID to filter by
     * @param string $dateRange Date range to filter
     * @param string $format Export format (only 'csv' supported)
     * @return string CSV content
     */
    public function exportAnalytics(?int $shortLinkId, string $dateRange, string $format): string
    {
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->select([
                'dateCreated',
                'linkId',
                'siteId',
                'deviceType',
                'deviceBrand',
                'deviceModel',
                'osName',
                'osVersion',
                'browser',
                'browserVersion',
                'country',
                'city',
                'language',
                'referer as referrer',
                'ip',
                'userAgent'
            ])
            ->orderBy(['dateCreated' => SORT_DESC]);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $dateRange);

        // Filter by link if specified
        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        $results = $query->all();

        // Check if geo detection is enabled
        $settings = ShortLinkManager::$plugin->getSettings();
        $geoEnabled = $settings->enableGeoDetection ?? true;

        // CSV format only - conditionally include geo columns
        if ($geoEnabled) {
            $csv = "Date,Time,ShortLink Code,ShortLink Status,ShortLink URL,Site,Destination URL,Referrer,User Device Type,User Device Brand,User Device Model,User OS,User OS Version,User Browser,User Browser Version,User Country,User City,User Language,User Agent\n";
        } else {
            $csv = "Date,Time,ShortLink Code,ShortLink Status,ShortLink URL,Site,Destination URL,Referrer,User Device Type,User Device Brand,User Device Model,User OS,User OS Version,User Browser,User Browser Version,User Language,User Agent\n";
        }

        foreach ($results as $row) {
            // Get the link with correct site
            $shortLink = ShortLink::find()
                ->id($row['linkId'])
                ->siteId($row['siteId'])
                ->status(null)
                ->one();

            if (!$shortLink) {
                continue;
            }

            // Get the actual status
            $status = $shortLink->getStatus();
            $shortLinkCode = $shortLink->code;
            $shortLinkStatus = match($status) {
                ShortLink::STATUS_ENABLED => 'Active',
                ShortLink::STATUS_DISABLED => 'Disabled',
                ShortLink::STATUS_PENDING => 'Pending',
                ShortLink::STATUS_EXPIRED => 'Expired',
                default => 'Unknown'
            };

            $shortLinkUrl = '';
            $destinationUrl = $shortLink->destinationUrl ?? '';

            // Get site name and build the short link URL
            $siteName = '';
            if (!empty($row['siteId'])) {
                $site = Craft::$app->getSites()->getSiteById($row['siteId']);
                $siteName = $site ? $site->name : '';
                if ($shortLink) {
                    // Generate the URL for the specific site
                    $shortLinkUrl = \craft\helpers\UrlHelper::siteUrl("go/{$shortLink->code}", null, null, $row['siteId']);
                }
            }

            $date = \craft\helpers\DateTimeHelper::toDateTime($row['dateCreated']);
            $dateStr = $date ? $date->format('Y-m-d') : '';
            $timeStr = $date ? $date->format('H:i:s') : '';

            // Keep the actual referrer URL
            $referrerDisplay = $row['referrer'] ?? '';

            if ($geoEnabled) {
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $dateStr,
                    $timeStr,
                    $shortLinkCode,
                    $shortLinkStatus,
                    $shortLinkUrl,
                    $siteName,
                    $destinationUrl,
                    $referrerDisplay,
                    $row['deviceType'] ?? '',
                    $row['deviceBrand'] ?? '',
                    $row['deviceModel'] ?? '',
                    $row['osName'] ?? '',
                    $row['osVersion'] ?? '',
                    $row['browser'] ?? '',
                    $row['browserVersion'] ?? '',
                    $this->_getCountryName($row['country'] ?? ''),
                    $row['city'] ?? '',
                    $row['language'] ?? '',
                    $row['userAgent'] ?? ''
                );
            } else {
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $dateStr,
                    $timeStr,
                    $shortLinkCode,
                    $shortLinkStatus,
                    $shortLinkUrl,
                    $siteName,
                    $destinationUrl,
                    $referrerDisplay,
                    $row['deviceType'] ?? '',
                    $row['deviceBrand'] ?? '',
                    $row['deviceModel'] ?? '',
                    $row['osName'] ?? '',
                    $row['osVersion'] ?? '',
                    $row['browser'] ?? '',
                    $row['browserVersion'] ?? '',
                    $row['language'] ?? '',
                    $row['userAgent'] ?? ''
                );
            }
        }

        return $csv;
    }

    /**
     * Get country name from country code
     *
     * @param string $countryCode
     * @return string
     */
    private function _getCountryName(string $countryCode): string
    {
        if (empty($countryCode)) {
            return '';
        }

        $countries = [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
            'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
            'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
            'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
            'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
            'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
            'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
            'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
            'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic',
            'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
            'CG' => 'Congo', 'CD' => 'Congo (DRC)', 'CR' => 'Costa Rica', 'HR' => 'Croatia',
            'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark',
            'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador',
            'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea',
            'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FJ' => 'Fiji', 'FI' => 'Finland',
            'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia',
            'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GD' => 'Grenada',
            'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
            'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary',
            'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran',
            'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy',
            'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
            'KE' => 'Kenya', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos',
            'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia',
            'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
            'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia',
            'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MR' => 'Mauritania',
            'MU' => 'Mauritius', 'MX' => 'Mexico', 'MD' => 'Moldova', 'MC' => 'Monaco',
            'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique',
            'MM' => 'Myanmar', 'NA' => 'Namibia', 'NP' => 'Nepal', 'NL' => 'Netherlands',
            'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
            'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PA' => 'Panama',
            'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
            'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar',
            'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda', 'SA' => 'Saudi Arabia',
            'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
            'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SO' => 'Somalia',
            'ZA' => 'South Africa', 'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
            'SD' => 'Sudan', 'SR' => 'Suriname', 'SZ' => 'Swaziland', 'SE' => 'Sweden',
            'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TG' => 'Togo', 'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan',
            'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
            'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu',
            'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        ];

        return $countries[$countryCode] ?? $countryCode;
    }
}
