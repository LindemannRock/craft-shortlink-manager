<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services\analytics;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Analytics Query Insights Service
 *
 * Link-level analytics, click stats, recent clicks, top links, charts, and hourly data.
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.7.0
 */
class AnalyticsQueryInsightsService
{
    use AnalyticsQueryTrait;

    /**
     * Get click statistics for a link
     *
     * @param int $shortLinkId
     * @param array $filters
     * @return array
     * @since 5.7.0
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
     * Get analytics for a specific link
     *
     * @param int $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.7.0
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
        $daysCount = DateRangeHelper::getDaysCount($dateRange);
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
     * Get all recent clicks
     *
     * @param string $dateRange
     * @param int $limit
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.7.0
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
     * Get top performing links
     *
     * @param int $limit
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.7.0
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
     * Get clicks data for chart
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     * @since 5.7.0
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
            $resultsByDate[$rowDateObj->format('Y-m-d')] = (int) $row['clicks'];
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
     * @since 5.7.0
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
            $hourlyData[(int) $result['hour']] = (int) $result['clicks'];
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
}
