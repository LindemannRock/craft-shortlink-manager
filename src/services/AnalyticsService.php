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
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\AnalyticsRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Request;

/**
 * Analytics Service
 *
 * @since 5.0.0
 */
class AnalyticsService extends Component
{
    use LoggingTrait;
    use GeoLookupTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Track a click
     *
     * @param ShortLink $shortLink
     * @param Request $request
     * @param string $source Source of the click (qr, direct, etc.)
     * @return void
     * @since 5.0.0
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
        $record->destinationUrl = $shortLink->destinationUrl; // Capture destination at click time

        // Get IP address
        $ip = $request->getUserIP();

        // Step 1: Anonymize IP if enabled (subnet masking BEFORE hashing)
        if ($settings->anonymizeIpAddress && $ip) {
            $ip = $this->_anonymizeIp($ip);
        }

        // Step 2: Hash IP with salt for storage
        if ($ip) {
            try {
                $record->ip = $this->_hashIpWithSalt($ip);
            } catch (\Exception $e) {
                $this->logError('Failed to hash IP address', ['error' => $e->getMessage()]);
                $record->ip = null;
                $ip = null; // Prevent geo lookup with raw IP
            }
        } else {
            $record->ip = null;
        }

        // Step 3: Get geo location (uses anonymized or full IP, skipped if hash failed)
        if ($settings->enableGeoDetection && $ip) {
            $this->getGeoData($record, $ip);
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
     * @since 5.0.0
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
        $localDate = DateFormatHelper::localDateExpression('dateCreated');
        $clicksByDate = (new Query())
            ->select(['date' => $localDate, 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->groupBy($localDate)
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
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getTopLinks(int $limit = 10, string $dateRange = 'last7days', int|array|null $siteId = null): array
    {
        $contentSiteId = is_int($siteId) ? $siteId : Craft::$app->getSites()->getPrimarySite()->id;

        $query = (new Query())
            ->select(['l.id', 'l.code', 'l.slug', 'c.destinationUrl', 'c.siteId', 'COUNT(a.id) as clicks', 'MAX(a.dateCreated) as lastClick'])
            ->from('{{%shortlinkmanager}} l')
            ->leftJoin('{{%shortlinkmanager_analytics}} a', 'a.linkId = l.id')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = l.id AND c.siteId = :contentSiteId', [':contentSiteId' => $contentSiteId])
            ->groupBy('l.id, c.destinationUrl, c.siteId')
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit);

        // Apply date range filter to analytics table
        $this->applyDateRangeFilter($query, $dateRange, 'a.dateCreated');

        // Filter by site if specified
        if ($siteId) {
            $query->andWhere(['a.siteId' => $siteId]);
        }

        $results = $query->all();

        // Add site name through Site model to parse env vars
        foreach ($results as &$result) {
            $site = !empty($result['siteId']) ? Craft::$app->getSites()->getSiteById($result['siteId']) : null;
            $result['siteName'] = $site ? $site->name : '-';

            // Pre-format lastClick for display with timezone
            // Database stores in UTC, so create DateTime with UTC timezone first
            if (!empty($result['lastClick'])) {
                $utcDate = new \DateTime($result['lastClick'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $result['lastClick'] = $utcDate;
                $result['lastClickFormatted'] = Craft::$app->getFormatter()->asDatetime($utcDate, 'short');
            }
        }

        return $results;
    }

    /**
     * Get device breakdown
     *
     * @param int $shortLinkId
     * @return array
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
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
        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $totalClicks = (int) $query->count();
        $uniqueVisitors = (int) (clone $query)->select('COUNT(DISTINCT ip)')->scalar();

        // Get active links count (use element query to check enabled status properly)
        $activeLinksQuery = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
            ->status('enabled');
        if ($siteId) {
            $activeLinksQuery->siteId($siteId);
        }
        $activeLinks = $activeLinksQuery->count();

        // Get total links
        $totalLinksQuery = (new Query())
            ->from('{{%shortlinkmanager}}');
        $totalLinks = $totalLinksQuery->count();

        // Get count of links that have been clicked in this period
        $shortLinksQuery = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->select('COUNT(DISTINCT linkId)');

        $this->applyDateRangeFilter($shortLinksQuery, $dateRange);
        if ($siteId) {
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
            'topCountries' => $this->getTopCountries(null, $dateRange, 10, $siteId),
            'topCities' => $this->getTopCities(null, $dateRange, 15, $siteId),
            'recentClicks' => $this->getAllRecentClicks($dateRange, 20, $siteId),
        ];
    }

    /**
     * Apply date range filter to query
     *
     * @param Query $query
     * @param string $dateRange
     * @param string $column
     * @return void
     * @since 5.0.0
     */
    public function applyDateRangeFilter(Query $query, string $dateRange, string $column = 'dateCreated'): void
    {
        DateRangeHelper::applyToQuery($query, $dateRange, $column);
    }

    /**
     * Get analytics for a specific link
     *
     * @param int $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getLinkAnalytics(int $shortLinkId, string $dateRange = 'last7days', int|array|null $siteId = null): array
    {
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId]);

        // Filter by site(s) if specified
        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

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
        // Use analytics destinationUrl (captured at click time), fallback to current for old records
        $recentClicksQuery = (new Query())
            ->select([
                'a.id', 'a.linkId', 'a.siteId', 'a.ip', 'a.userAgent', 'a.referer', 'a.metadata',
                'a.deviceType', 'a.deviceBrand', 'a.deviceModel', 'a.browser', 'a.browserVersion',
                'a.browserEngine', 'a.osName', 'a.osVersion', 'a.clientType', 'a.isRobot',
                'a.isMobileApp', 'a.botName', 'a.country', 'a.city', 'a.language', 'a.region',
                'a.latitude', 'a.longitude', 'a.dateCreated', 'a.dateUpdated',
                'l.code as linkCode', 'l.slug',
                'COALESCE(a.destinationUrl, c.destinationUrl) as destinationUrl',
            ])
            ->from('{{%shortlinkmanager_analytics}} a')
            ->innerJoin('{{%shortlinkmanager}} l', 'l.id = a.linkId')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = a.linkId AND c.siteId = a.siteId')
            ->where(['a.linkId' => $shortLinkId])
            ->orderBy(['a.dateCreated' => SORT_DESC])
            ->limit(20);

        if ($siteId) {
            $recentClicksQuery->andWhere(['a.siteId' => $siteId]);
        }

        $this->applyDateRangeFilter($recentClicksQuery, $dateRange, 'a.dateCreated');

        $recentClicks = $recentClicksQuery->all();

        // Convert dates from UTC to user's timezone
        foreach ($recentClicks as &$click) {
            if (!empty($click['dateCreated'])) {
                $utcDate = new \DateTime($click['dateCreated'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $click['dateCreated'] = $utcDate;
            }
        }

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
        $bounds = DateRangeHelper::getBounds($dateRange);
        $start = $bounds['start'] ?? null;
        $end = $bounds['end'] ?? null;

        if (!$start && !$end) {
            return 36500;
        }

        $end = $end ?? new \DateTime('now', new \DateTimeZone('UTC'));

        if (!$start) {
            return 30;
        }

        $days = (int)$start->diff($end)->format('%a');
        return max(1, $days);
    }

    /**
     * Get all recent clicks
     *
     * @param string $dateRange
     * @param int $limit
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getAllRecentClicks(string $dateRange = 'last7days', int $limit = 20, int|array|null $siteId = null): array
    {
        // Use analytics destinationUrl (captured at click time), fallback to current for old records
        $query = (new Query())
            ->select([
                'a.id', 'a.linkId', 'a.siteId', 'a.ip', 'a.userAgent', 'a.referer', 'a.metadata',
                'a.deviceType', 'a.deviceBrand', 'a.deviceModel', 'a.browser', 'a.browserVersion',
                'a.browserEngine', 'a.osName', 'a.osVersion', 'a.clientType', 'a.isRobot',
                'a.isMobileApp', 'a.botName', 'a.country', 'a.city', 'a.language', 'a.region',
                'a.latitude', 'a.longitude', 'a.dateCreated', 'a.dateUpdated',
                'l.code as linkCode', 'l.slug',
                'COALESCE(a.destinationUrl, c.destinationUrl) as destinationUrl',
            ])
            ->from('{{%shortlinkmanager_analytics}} a')
            ->innerJoin('{{%shortlinkmanager}} l', 'l.id = a.linkId')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = a.linkId AND c.siteId = a.siteId')
            ->orderBy(['a.dateCreated' => SORT_DESC])
            ->limit($limit);

        $this->applyDateRangeFilter($query, $dateRange, 'a.dateCreated');

        // Filter by site if specified
        if ($siteId) {
            $query->andWhere(['a.siteId' => $siteId]);
        }

        $results = $query->all();

        // Parse metadata and add site name
        foreach ($results as &$result) {
            if (!empty($result['metadata'])) {
                $metadata = json_decode($result['metadata'], true);
                $result['source'] = $metadata['source'] ?? 'direct';
            } else {
                $result['source'] = 'direct';
            }

            // Get site name through Site model to parse env vars
            $site = !empty($result['siteId']) ? Craft::$app->getSites()->getSiteById($result['siteId']) : null;
            $result['siteName'] = $site ? $site->name : '-';

            // Pre-format dateCreated for display with timezone
            // Database stores in UTC, so create DateTime with UTC timezone first
            if (!empty($result['dateCreated'])) {
                $utcDate = new \DateTime($result['dateCreated'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $result['dateCreated'] = $utcDate;
                $result['dateCreatedFormatted'] = Craft::$app->getFormatter()->asDatetime($utcDate, 'short');
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
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getTopCountries(?int $shortLinkId, string $dateRange, int $limit = 10, int|array|null $siteId = null): array
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

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();
        $total = array_sum(array_column($results, 'clicks'));

        // Add percentages and country names
        foreach ($results as &$result) {
            $result['percentage'] = $total > 0 ? round(($result['clicks'] / $total) * 100, 1) : 0;
            $result['name'] = GeoHelper::getCountryName($result['country'] ?? '');
        }

        return $results;
    }

    /**
     * Get top cities
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int $limit
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getTopCities(?int $shortLinkId, string $dateRange, int $limit = 15, int|array|null $siteId = null): array
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

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();
        $total = array_sum(array_column($results, 'clicks'));

        // Add percentages and country names
        foreach ($results as &$result) {
            $result['percentage'] = $total > 0 ? round(($result['clicks'] / $total) * 100, 1) : 0;
            $result['countryName'] = GeoHelper::getCountryName($result['country'] ?? '');
        }

        return $results;
    }

    /**
     * Get device brand breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getDeviceBrandBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
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

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
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
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getOsBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
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

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
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
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getBrowserBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
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

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
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
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getDeviceTypeBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
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

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
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
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getClicksData(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        $bounds = DateRangeHelper::getBounds($dateRange);
        $startDate = $bounds['start'] ?? null;
        $endDate = $bounds['end'] ?? null;

        $localDate = DateFormatHelper::localDateExpression('dateCreated');
        $query = (new Query())
            ->select(['date' => $localDate, 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->groupBy($localDate)
            ->orderBy(['date' => SORT_ASC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();

        if (empty($results)) {
            return [
                'labels' => [],
                'values' => [],
            ];
        }

        $tz = new \DateTimeZone(Craft::$app->getTimeZone());

        if (!$startDate) {
            $startDate = new \DateTime($results[0]['date'], new \DateTimeZone('UTC'));
        }
        $startDate->setTimezone($tz)->setTime(0, 0, 0);

        $endDateIsExclusive = $endDate !== null;
        if (!$endDate) {
            $endDate = new \DateTime('now', new \DateTimeZone('UTC'));
        }
        $endDate->setTimezone($tz)->setTime(0, 0, 0);

        $rangeEnd = clone $endDate;
        if ($endDateIsExclusive) {
            $rangeEnd->modify('-1 day');
        }

        $resultsByDate = [];
        foreach ($results as $row) {
            $rowDateObj = new \DateTime($row['date'], new \DateTimeZone('UTC'));
            $rowDateObj->setTimezone($tz);
            $resultsByDate[$rowDateObj->format('Y-m-d')] = (int)$row['clicks'];
        }

        $labels = [];
        $values = [];
        $date = clone $startDate;
        while ($date <= $rangeEnd) {
            $dateStr = $date->format('Y-m-d');
            $labels[] = $dateStr;
            $values[] = $resultsByDate[$dateStr] ?? 0;
            $date->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get hourly analytics
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.0.0
     */
    public function getHourlyAnalytics(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        $localHour = DateFormatHelper::localHourExpression('dateCreated');
        $query = (new Query())
            ->select(['hour' => $localHour, 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->groupBy($localHour)
            ->orderBy(['hour' => SORT_ASC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
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
     * @since 5.0.0
     */
    public function getLocationFromIp(string $ip): ?array
    {
        // Handle private/local IPs with default location for development
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $this->getDefaultLocation();
        }

        // Use centralized geo lookup from base plugin
        $geoData = $this->lookupGeoIp($ip, $this->getGeoConfig());

        if ($geoData === null) {
            return null;
        }

        // Normalize response to match expected format (lat/lon keys, include timezone)
        return [
            'countryCode' => $geoData['countryCode'] ?? null,
            'country' => $geoData['country'] ?? null,
            'city' => $geoData['city'] ?? null,
            'region' => $geoData['region'] ?? null,
            'timezone' => $geoData['timezone'] ?? null,
            'lat' => $geoData['latitude'] ?? null,
            'lon' => $geoData['longitude'] ?? null,
        ];
    }

    /**
     * Get geo config from plugin settings
     *
     * @return array<string, mixed>
     */
    protected function getGeoConfig(): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        return [
            'provider' => $settings->geoProvider ?? 'ip-api.com',
            'apiKey' => $settings->geoApiKey ?? null,
        ];
    }

    /**
     * Get default location for private/local IPs
     *
     * @return array<string, mixed>|null
     */
    private function getDefaultLocation(): ?array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $defaultCountry = $settings->defaultCountry ?: (getenv('SHORTLINK_MANAGER_DEFAULT_COUNTRY') ?: 'AE');
        $defaultCity = $settings->defaultCity ?: (getenv('SHORTLINK_MANAGER_DEFAULT_CITY') ?: 'Dubai');

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
                'saltValue' => $salt ?? 'NULL',
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
     * Get analytics data formatted for export
     *
     * Returns an array of data that can be used with ExportHelper.
     *
     * @param int|null $shortLinkId Optional link ID to filter by
     * @param string $dateRange Date range to filter
     * @param int|int[]|null $siteId Optional site ID to filter by
     * @return array Array of formatted export data
     * @since 5.5.0
     */
    public function getExportData(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        // Use analytics destinationUrl (captured at click time), fallback to current for old records
        $query = (new Query())
            ->from('{{%shortlinkmanager_analytics}} a')
            ->leftJoin('{{%shortlinkmanager_content}} c', 'c.shortLinkId = a.linkId AND c.siteId = a.siteId')
            ->select([
                'a.dateCreated',
                'a.linkId',
                'a.siteId',
                'a.metadata',
                'a.deviceType',
                'a.deviceBrand',
                'a.deviceModel',
                'a.osName',
                'a.osVersion',
                'a.browser',
                'a.browserVersion',
                'a.country',
                'a.city',
                'a.language',
                'a.referer as referrer',
                'a.userAgent',
                'COALESCE(a.destinationUrl, c.destinationUrl) as destinationUrl',
            ])
            ->orderBy(['a.dateCreated' => SORT_DESC]);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $dateRange, 'a.dateCreated');

        // Filter by link if specified
        if ($shortLinkId) {
            $query->andWhere(['a.linkId' => $shortLinkId]);
        }

        // Filter by site if specified
        if ($siteId) {
            $query->andWhere(['a.siteId' => $siteId]);
        }

        $results = $query->all();

        // Get settings
        $settings = ShortLinkManager::$plugin->getSettings();
        $geoEnabled = $settings->enableGeoDetection ?? true;

        // Pre-fetch all referenced ShortLinks in one query to avoid N+1
        $linkIds = array_unique(array_column($results, 'linkId'));
        $shortLinksMap = [];
        if (!empty($linkIds)) {
            foreach (ShortLink::find()->id($linkIds)->status(null)->all() as $link) {
                $shortLinksMap[$link->id] = $link;
            }
        }

        // Format data for export
        $exportData = [];
        foreach ($results as $row) {
            $shortLink = $shortLinksMap[$row['linkId']] ?? null;

            if (!$shortLink) {
                continue;
            }

            // Get the actual status
            $status = $shortLink->getStatus();
            $statusLabel = match ($status) {
                ShortLink::STATUS_ENABLED => 'Active',
                ShortLink::STATUS_DISABLED => 'Disabled',
                ShortLink::STATUS_PENDING => 'Pending',
                ShortLink::STATUS_EXPIRED => 'Expired',
                default => 'Unknown'
            };

            // Get site name
            $siteName = '';
            $shortLinkUrl = '';
            if (!empty($row['siteId'])) {
                $site = Craft::$app->getSites()->getSiteById($row['siteId']);
                $siteName = $site ? $site->name : '';
                $shortLinkUrl = \craft\helpers\UrlHelper::siteUrl("{$settings->slugPrefix}/{$shortLink->code}", null, null, $row['siteId']);
            }

            // Parse source from metadata JSON
            $source = 'Direct';
            if (!empty($row['metadata'])) {
                $metadata = json_decode($row['metadata'], true);
                $sourceValue = $metadata['source'] ?? 'direct';
                $source = $sourceValue === 'qr' ? 'QR' : 'Direct';
            }

            $record = [
                'dateCreated' => $row['dateCreated'],
                'code' => $shortLink->code,
                'status' => $statusLabel,
                'shortLinkUrl' => $shortLinkUrl,
                'siteName' => $siteName,
                'source' => $source,
                'destinationUrl' => $row['destinationUrl'] ?? '',
                'referrer' => $row['referrer'] ?? '',
                'deviceType' => $row['deviceType'] ?? '',
                'deviceBrand' => $row['deviceBrand'] ?? '',
                'deviceModel' => $row['deviceModel'] ?? '',
                'osName' => $row['osName'] ?? '',
                'osVersion' => $row['osVersion'] ?? '',
                'browser' => $row['browser'] ?? '',
                'browserVersion' => $row['browserVersion'] ?? '',
                'language' => $row['language'] ?? '',
                'userAgent' => $row['userAgent'] ?? '',
            ];

            // Add geo fields if enabled
            if ($geoEnabled) {
                $record['country'] = GeoHelper::getCountryName($row['country'] ?? '');
                $record['city'] = $row['city'] ?? '';
            }

            $exportData[] = $record;
        }

        return $exportData;
    }
}
