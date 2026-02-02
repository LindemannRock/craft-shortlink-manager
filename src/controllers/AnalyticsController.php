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
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('shortLinkManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $dateRange = $request->getQueryParam('dateRange', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));
        $siteId = $request->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;

        // Get settings
        $settings = ShortLinkManager::$plugin->getSettings();

        // Get analytics summary
        $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($dateRange, null, $siteId);

        // Get enabled sites for site selector (respects enabledSites setting)
        $enabledSiteIds = $settings->getEnabledSiteIds();
        $allSites = Craft::$app->getSites()->getAllSites();
        $sites = array_filter($allSites, fn($site) => in_array($site->id, $enabledSiteIds));

        return $this->renderTemplate('shortlink-manager/analytics/index', [
            'analyticsData' => $analyticsData,
            'dateRange' => $dateRange,
            'siteId' => $siteId,
            'sites' => $sites,
            'settings' => $settings,
        ]);
    }

    /**
     * Get analytics data via AJAX
     *
     * @return Response
     */
    public function actionGetData(): Response
    {
        $this->requirePermission('shortLinkManager:viewAnalytics');
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $dateRange = $request->getBodyParam('dateRange', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));
        $type = $request->getBodyParam('type', 'summary');
        $linkId = $request->getBodyParam('linkId');
        $siteId = $request->getBodyParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;

        $data = [];

        try {
            switch ($type) {
                case 'summary':
                    $data = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($dateRange, $linkId, $siteId);
                    break;

                case 'clicks':
                    $data = ShortLinkManager::$plugin->analytics->getClicksData($linkId, $dateRange, $siteId);
                    break;

                case 'devices':
                    $data = ShortLinkManager::$plugin->analytics->getDeviceTypeBreakdown($linkId, $dateRange, $siteId);
                    break;

                case 'device-brands':
                    $data = ShortLinkManager::$plugin->analytics->getDeviceBrandBreakdown($linkId, $dateRange, $siteId);
                    break;

                case 'os-breakdown':
                    $data = ShortLinkManager::$plugin->analytics->getOsBreakdown($linkId, $dateRange, $siteId);
                    break;

                case 'browsers':
                    $data = ShortLinkManager::$plugin->analytics->getBrowserBreakdown($linkId, $dateRange, $siteId);
                    break;

                case 'hourly':
                    $data = ShortLinkManager::$plugin->analytics->getHourlyAnalytics($linkId, $dateRange, $siteId);
                    break;

                default:
                    return $this->asJson([
                        'success' => false,
                        'error' => 'Invalid data type requested',
                    ]);
            }

            return $this->asJson([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get link analytics data via AJAX
     *
     * @return Response
     */
    public function actionGetLinkAnalytics(): Response
    {
        $this->requireLogin();
        $this->requirePermission('shortLinkManager:viewAnalytics');
        $this->requireAcceptsJson();

        $linkId = Craft::$app->getRequest()->getParam('linkId');
        $range = Craft::$app->getRequest()->getParam('range', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));

        if (!$linkId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Link ID is required',
            ]);
        }

        try {
            // Get the short link
            $shortLink = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->id($linkId)
                ->status(null)
                ->one();

            if (!$shortLink) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Short link not found',
                ]);
            }

            // Set the range parameter in the request so the template can access it
            $_GET['range'] = $range;
            Craft::$app->getRequest()->setQueryParams(array_merge(Craft::$app->getRequest()->getQueryParams(), ['range' => $range]));

            // Render only the content part for AJAX
            $html = Craft::$app->getView()->renderTemplate('shortlink-manager/shortlinks/_partials/analytics-content', [
                'shortLink' => $shortLink,
                'dateRange' => $range,
            ]);

            return $this->asJson([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Exception $e) {
            $this->logError('Failed to get link analytics data', ['error' => $e->getMessage()]);
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
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
        $this->requirePermission('shortLinkManager:exportAnalytics');

        $request = Craft::$app->getRequest();
        $dateRange = $request->getQueryParam('dateRange', DateRangeHelper::getDefaultDateRange(ShortLinkManager::$plugin->id));
        $format = $request->getQueryParam('format', 'csv');
        $linkId = $request->getQueryParam('linkId');
        $siteId = $request->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;

        // Get export data
        $exportData = ShortLinkManager::$plugin->analytics->getExportData(
            $linkId ? (int)$linkId : null,
            $dateRange,
            $siteId
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
        $filenameParts = ['analytics', $dateRangeLabel];

        // Add link code to filename if specific link
        if ($linkId) {
            $shortLink = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                ->id($linkId)
                ->one();
            if ($shortLink) {
                $cleanCode = preg_replace('/[^a-zA-Z0-9-_]/', '', $shortLink->code);
                array_unshift($filenameParts, $cleanCode);
            }
        }

        // Add site to filename if filtered
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $filenameParts[] = strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace(' ', '-', $site->name)));
            }
        }

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
}
