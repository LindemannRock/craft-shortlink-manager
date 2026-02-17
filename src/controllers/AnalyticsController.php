<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Response;

/**
 * Analytics Controller
 *
 * @since 5.0.0
 */
class AnalyticsController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Analytics dashboard
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('shortLinkManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $dateRange = $request->getQueryParam('dateRange', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));
        $siteId = $request->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $resolvedSiteId = $this->_resolveSiteId($siteId);

        // Get settings
        $settings = ShortLinkManager::$plugin->getSettings();

        // Get analytics summary (scoped to user's allowed sites)
        $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($dateRange, null, $resolvedSiteId);

        // Get enabled sites for site selector (respects enabledSites + user permissions)
        $sites = ShortLinkManager::$plugin->getEnabledSites();

        return $this->renderTemplate('shortlink-manager/analytics/index', [
            'analyticsData' => $analyticsData,
            'dateRange' => $dateRange,
            'siteId' => $siteId,
            'sites' => $sites,
            'settings' => $settings,
            'pluginHandle' => ShortLinkManager::$plugin->id,
        ]);
    }

    /**
     * Get analytics data via AJAX
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionGetData(): Response
    {
        $this->requirePermission('shortLinkManager:viewAnalytics');
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $dateRange = $request->getBodyParam('dateRange', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));
        $type = $request->getBodyParam('type', 'summary');

        $validTypes = ['summary', 'clicks', 'devices', 'device-brands', 'os-breakdown', 'browsers', 'hourly', 'top-countries', 'top-cities', 'recent-clicks'];
        if (!in_array($type, $validTypes, true)) {
            throw new \yii\web\BadRequestHttpException('Invalid data type.');
        }

        $linkId = $request->getBodyParam('linkId');
        $siteId = $request->getBodyParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $resolvedSiteId = $this->_resolveSiteId($siteId);

        $data = [];

        try {
            switch ($type) {
                case 'summary':
                    $data = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($dateRange, $linkId, $resolvedSiteId);
                    break;

                case 'clicks':
                    $data = ShortLinkManager::$plugin->analytics->getClicksData($linkId, $dateRange, $resolvedSiteId);
                    break;

                case 'devices':
                    $data = ShortLinkManager::$plugin->analytics->getDeviceTypeBreakdown($linkId, $dateRange, $resolvedSiteId);
                    break;

                case 'device-brands':
                    $data = ShortLinkManager::$plugin->analytics->getDeviceBrandBreakdown($linkId, $dateRange, $resolvedSiteId);
                    break;

                case 'os-breakdown':
                    $data = ShortLinkManager::$plugin->analytics->getOsBreakdown($linkId, $dateRange, $resolvedSiteId);
                    break;

                case 'browsers':
                    $data = ShortLinkManager::$plugin->analytics->getBrowserBreakdown($linkId, $dateRange, $resolvedSiteId);
                    break;

                case 'hourly':
                    $data = ShortLinkManager::$plugin->analytics->getHourlyAnalytics($linkId, $dateRange, $resolvedSiteId);
                    break;

                case 'top-countries':
                    $data = ShortLinkManager::$plugin->analytics->getTopCountries(null, $dateRange, 10, $resolvedSiteId);
                    break;

                case 'top-cities':
                    $data = ShortLinkManager::$plugin->analytics->getTopCities(null, $dateRange, 15, $resolvedSiteId);
                    break;

                case 'recent-clicks':
                    $data = $this->_formatRecentClicks(
                        ShortLinkManager::$plugin->analytics->getAllRecentClicks($dateRange, 20, $resolvedSiteId)
                    );
                    break;
            }

            return $this->asJson([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            return $this->asJson([
                'success' => false,
                'error' => Craft::$app->getConfig()->getGeneral()->devMode
                    ? $e->getMessage()
                    : Craft::t('shortlink-manager', 'An unexpected error occurred.'),
            ]);
        }
    }

    /**
     * Export analytics data
     *
     * Supports CSV, JSON, and Excel formats using ExportHelper.
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionExport(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:exportAnalytics');

        $request = Craft::$app->getRequest();
        // Accept both 'range' and 'dateRange' parameter names
        $dateRange = $request->getBodyParam('range') ?? $request->getBodyParam('dateRange', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));
        $format = $request->getBodyParam('format', 'csv');
        $linkId = $request->getBodyParam('linkId');
        $siteId = $request->getBodyParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $resolvedSiteId = $this->_resolveSiteId($siteId);

        if (!ExportHelper::isFormatEnabled($format, ShortLinkManager::$plugin->id)) {
            throw new \yii\web\BadRequestHttpException("Export format '{$format}' is not enabled.");
        }

        // Get export data (scoped to user's allowed sites)
        $exportData = ShortLinkManager::$plugin->analytics->getExportData(
            $linkId ? (int)$linkId : null,
            $dateRange,
            $resolvedSiteId
        );

        // Check for empty data
        if (empty($exportData)) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'No analytics data to export.'));
            if ($linkId) {
                return $this->redirect('shortlink-manager/shortlinks/' . $linkId . '?range=' . $dateRange);
            }
            return $this->redirect('shortlink-manager/analytics?dateRange=' . $dateRange);
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $geoEnabled = $settings->enableGeoDetection ?? true;

        // Build filename parts
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;
        $filenameParts = ['analytics'];

        // Add link code to filename if specific link
        if ($linkId) {
            $shortLink = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->id($linkId)
                ->one();
            if ($shortLink) {
                $cleanCode = preg_replace('/[^a-zA-Z0-9-_]/', '', $shortLink->code);
                $filenameParts[] = $cleanCode;
            }
        }

        // Add site to filename if filtered
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $filenameParts[] = strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace(' ', '-', $site->name)));
            }
        }

        $filenameParts[] = $dateRangeLabel;

        // Build headers for CSV/Excel
        $headers = [
            'dateCreated' => Craft::t('shortlink-manager', 'Date/Time'),
            'code' => Craft::t('shortlink-manager', 'Code'),
            'status' => Craft::t('shortlink-manager', 'Status'),
            'shortLinkUrl' => Craft::t('shortlink-manager', 'Short Link URL'),
            'siteName' => Craft::t('shortlink-manager', 'Site'),
            'source' => Craft::t('shortlink-manager', 'Source'),
            'destinationUrl' => Craft::t('shortlink-manager', 'Destination URL'),
            'referrer' => Craft::t('shortlink-manager', 'Referrer'),
            'deviceType' => Craft::t('shortlink-manager', 'Device Type'),
            'deviceBrand' => Craft::t('shortlink-manager', 'Device Brand'),
            'deviceModel' => Craft::t('shortlink-manager', 'Device Model'),
            'osName' => Craft::t('shortlink-manager', 'OS'),
            'osVersion' => Craft::t('shortlink-manager', 'OS Version'),
            'browser' => Craft::t('shortlink-manager', 'Browser'),
            'browserVersion' => Craft::t('shortlink-manager', 'Browser Version'),
            'language' => Craft::t('shortlink-manager', 'Language'),
            'userAgent' => Craft::t('shortlink-manager', 'User Agent'),
        ];

        // Add geo headers if enabled
        if ($geoEnabled) {
            $headers['country'] = Craft::t('shortlink-manager', 'Country');
            $headers['city'] = Craft::t('shortlink-manager', 'City');
        }

        // Date columns for formatting
        $dateColumns = ['dateCreated'];

        // Export based on format
        $extension = $format === 'excel' ? 'xlsx' : $format;
        $filename = ExportHelper::filename($settings, $filenameParts, $extension);

        return match ($format) {
            'json' => ExportHelper::toJson($exportData, $filename, $dateColumns),
            'excel' => ExportHelper::toExcel($exportData, $headers, $filename, $dateColumns, [
                'sheetTitle' => Craft::t('shortlink-manager', 'Analytics'),
            ]),
            default => ExportHelper::toCsv($exportData, $headers, $filename, $dateColumns),
        };
    }

    /**
     * Get site IDs the current user is allowed to view analytics for
     *
     * Returns the intersection of plugin-enabled sites and user-editable sites.
     *
     * @return int[]
     */
    private function _getAllowedSiteIds(): array
    {
        return array_map(
            fn($site) => $site->id,
            ShortLinkManager::$plugin->getEnabledSites()
        );
    }

    /**
     * Resolve site ID parameter for analytics queries
     *
     * If a specific site ID is provided and the user has access, returns that int.
     * Otherwise returns the array of all allowed site IDs to scope the query.
     *
     * @param int|null $siteId
     * @return int|int[]
     */
    private function _resolveSiteId(?int $siteId): int|array
    {
        $allowedSiteIds = $this->_getAllowedSiteIds();

        if ($siteId !== null && in_array($siteId, $allowedSiteIds)) {
            return $siteId;
        }

        return $allowedSiteIds;
    }

    /**
     * Format recent clicks for AJAX response
     *
     * Converts DateTime objects to formatted strings and resolves site names
     * so the JS layer receives ready-to-render data.
     *
     * @param array $clicks
     * @return array
     */
    private function _formatRecentClicks(array $clicks): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $geoEnabled = $settings->enableGeoDetection ?? true;

        $formatted = [];
        foreach ($clicks as $click) {
            $date = $click['dateCreated'] ?? null;

            $row = [
                'dateFormatted' => $date instanceof \DateTime
                    ? DateFormatHelper::formatDate($date, 'short', true, false)
                    : null,
                'timeFormatted' => $date instanceof \DateTime
                    ? DateFormatHelper::formatTime($date, 'short', null, false)
                    : null,
                'linkId' => $click['linkId'] ?? null,
                'linkCode' => $click['linkCode'] ?? null,
                'siteName' => $click['siteName'] ?? '-',
                'source' => $click['source'] ?? 'direct',
                'destinationUrl' => $click['destinationUrl'] ?? null,
                'deviceType' => $click['deviceType'] ?? null,
                'browser' => $click['browser'] ?? null,
                'osName' => $click['osName'] ?? null,
            ];

            if ($geoEnabled) {
                $city = $click['city'] ?? null;
                $country = $click['country'] ?? null;
                if ($city && $country) {
                    $row['location'] = $city . ', ' . $country;
                } elseif ($country) {
                    $row['location'] = $country;
                } else {
                    $row['location'] = null;
                }
            }

            $formatted[] = $row;
        }

        return [
            'clicks' => $formatted,
            'geoEnabled' => $geoEnabled,
        ];
    }
}
