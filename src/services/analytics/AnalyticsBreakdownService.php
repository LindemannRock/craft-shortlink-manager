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
use craft\helpers\App;
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

        if ($siteId) {
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

        if ($siteId) {
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
     * Get location data from IP address
     *
     * @param string $ip
     * @return array|null
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
        $defaultCountry = $settings->defaultCountry ?: App::env('SHORTLINK_MANAGER_DEFAULT_COUNTRY');
        $defaultCity = $settings->defaultCity ?: App::env('SHORTLINK_MANAGER_DEFAULT_CITY');

        if (!$defaultCountry || !$defaultCity) {
            return null;
        }

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

        Craft::warning('Configured default analytics location was not found; leaving local/private IP geo fields empty. | ' . json_encode([
            'configuredCountry' => $defaultCountry,
            'configuredCity' => $defaultCity,
        ]), ShortLinkManager::$plugin->id);

        return null;
    }
}
