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
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Analytics Breakdown Service
 *
 * Device, browser, OS, geographic, and referrer breakdowns.
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.13.0
 */
class AnalyticsBreakdownService
{
    use AnalyticsQueryTrait;
    use GeoLookupTrait;

    /**
     * @var string[]|null
     */
    private ?array $_analyticsColumns = null;

    /**
     * Get device breakdown for a specific link
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
     * Get device type breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
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

        if ($siteId !== null) {
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
     * Get device brand breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
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

        if ($siteId !== null) {
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
     * Get traffic type breakdown.
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
     */
    public function getTrafficTypeBreakdown(?int $shortLinkId, string $dateRange, int|array|null $siteId = null): array
    {
        if (!$this->_hasAnalyticsColumn('trafficType')) {
            return [
                'labels' => [],
                'types' => [],
                'values' => [],
            ];
        }

        $query = (new Query())
            ->select(['trafficType', 'COUNT(*) as clicks'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->groupBy('trafficType')
            ->orderBy(['clicks' => SORT_DESC]);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        $results = $query->all();

        return [
            'labels' => array_map(static fn($type) => ucfirst((string)($type ?: 'human')), array_column($results, 'trafficType')),
            'types' => array_map(static fn($type) => (string)($type ?: 'human'), array_column($results, 'trafficType')),
            'values' => array_map('intval', array_column($results, 'clicks')),
        ];
    }

    /**
     * Get top non-human agents.
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int $limit
     * @param int|int[]|null $siteId
     * @return array
     */
    public function getTopAgents(?int $shortLinkId, string $dateRange, int $limit = 10, int|array|null $siteId = null): array
    {
        if (!$this->_hasAnalyticsColumn('trafficType') || !$this->_hasAnalyticsColumn('botCategory') || !$this->_hasAnalyticsColumn('botProducerName')) {
            return [];
        }

        $query = (new Query())
            ->select([
                'botName',
                'trafficType',
                'botCategory',
                'botProducerName',
                'COUNT(*) as clicks',
            ])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where([
                'or',
                ['trafficType' => ['system', 'bot']],
                ['not', ['botName' => null]],
            ])
            ->groupBy(['botName', 'trafficType', 'botCategory', 'botProducerName'])
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit);

        if ($shortLinkId) {
            $query->andWhere(['linkId' => $shortLinkId]);
        }

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $this->applyDateRangeFilter($query, $dateRange);

        return $query->all();
    }

    private function _hasAnalyticsColumn(string $column): bool
    {
        return in_array($column, $this->_analyticsColumns(), true);
    }

    /**
     * @return string[]
     */
    private function _analyticsColumns(): array
    {
        if ($this->_analyticsColumns !== null) {
            return $this->_analyticsColumns;
        }

        return $this->_analyticsColumns = \Craft::$app->getDb()->getTableSchema('{{%shortlinkmanager_analytics}}')?->columnNames ?? [];
    }

    /**
     * Get browser breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
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

        if ($siteId !== null) {
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
     * Get OS breakdown
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int|int[]|null $siteId
     * @return array
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

        if ($siteId !== null) {
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
     * Get geo breakdown for a specific link
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
     * Get referrer breakdown for a specific link
     *
     * @param int $shortLinkId
     * @return array
     */
    public function getReferrerBreakdown(int $shortLinkId): array
    {
        return (new Query())
            ->select(['referrer', 'COUNT(*) as count'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $shortLinkId])
            ->andWhere(['not', ['referrer' => null]])
            ->groupBy('referrer')
            ->orderBy(['count' => SORT_DESC])
            ->limit(20)
            ->all();
    }

    /**
     * Get top countries
     *
     * @param int|null $shortLinkId
     * @param string $dateRange
     * @param int $limit
     * @param int|int[]|null $siteId
     * @return array
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

        if ($siteId !== null) {
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

        if ($siteId !== null) {
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
     * Get location data from IP address
     *
     * @param string $ip
     * @return array|null
     */
    public function getLocationFromIp(string $ip): ?array
    {
        $geoData = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ? $this->getDefaultLocation()
            : $this->lookupGeoIp($ip, $this->getGeoConfig());

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

    /**
     * Get default location for private/local IPs
     *
     * @return array<string, mixed>|null
     */
    private function getDefaultLocation(): ?array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $defaultCountry = $settings->defaultCountry;
        $defaultCity = $settings->defaultCity;

        if (!$defaultCountry || !$defaultCity) {
            return null;
        }

        $location = GeoHelper::getDefaultLocation($defaultCountry, $defaultCity);

        if ($location !== null) {
            return $location;
        }

        Craft::warning('Configured default analytics location was not found; leaving local/private IP geo fields empty. | ' . json_encode([
            'configuredCountry' => $defaultCountry,
            'configuredCity' => $defaultCity,
        ]), ShortLinkManager::$plugin->id);

        return null;
    }
}
