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
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Analytics Export Service
 *
 * Export data formatting and analytics maintenance (cleanup).
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.7.0
 */
class AnalyticsExportService
{
    use AnalyticsQueryTrait;
    use LoggingTrait;

    public function __construct()
    {
        $this->setLoggingHandle('shortlink-manager');
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

        // Pre-fetch all sites keyed by ID (cached by Craft's Sites service)
        $sitesById = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $sitesById[$site->id] = $site;
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
                $site = $sitesById[$row['siteId']] ?? null;
                $siteName = $site ? $site->name : '';
                $usePrefix = (bool) ($settings->usePrefix ?? true);
                $slugPrefix = trim((string) ($settings->slugPrefix ?? 's'), '/');
                $slugPrefix = $slugPrefix !== '' ? $slugPrefix : 's';
                $path = $usePrefix ? "{$slugPrefix}/{$shortLink->code}" : $shortLink->code;
                $shortLinkUrl = $settings->buildPublicUrl($path, (int) $row['siteId']);
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
}
