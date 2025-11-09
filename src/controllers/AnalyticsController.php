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
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Response;

/**
 * Analytics Controller
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
        $this->setLoggingHandle('shortlink-manager');
    }

    /**
     * Analytics dashboard
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('shortLinkManager:viewAnalytics');

        // Get date range from query params or use default
        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', 'last7days');

        // Get analytics summary
        $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($dateRange);

        return $this->renderTemplate('shortlink-manager/analytics/index', [
            'analyticsData' => $analyticsData,
            'dateRange' => $dateRange,
            'settings' => ShortLinkManager::$plugin->getSettings(),
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
        $dateRange = $request->getBodyParam('dateRange', 'last7days');
        $type = $request->getBodyParam('type', 'summary');
        $linkId = $request->getBodyParam('linkId');

        $data = [];

        try {
            switch ($type) {
                case 'summary':
                    $data = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($dateRange, $linkId);
                    break;

                case 'clicks':
                    $data = ShortLinkManager::$plugin->analytics->getClicksData($linkId, $dateRange);
                    break;

                case 'devices':
                    $data = ShortLinkManager::$plugin->analytics->getDeviceTypeBreakdown($linkId, $dateRange);
                    break;

                case 'device-brands':
                    $data = ShortLinkManager::$plugin->analytics->getDeviceBrandBreakdown($linkId, $dateRange);
                    break;

                case 'os-breakdown':
                    $data = ShortLinkManager::$plugin->analytics->getOsBreakdown($linkId, $dateRange);
                    break;

                case 'browsers':
                    $data = ShortLinkManager::$plugin->analytics->getBrowserBreakdown($linkId, $dateRange);
                    break;

                case 'hourly':
                    $data = ShortLinkManager::$plugin->analytics->getHourlyAnalytics($linkId, $dateRange);
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
     * Export analytics data
     *
     * @return Response
     */
    public function actionExport(): Response
    {
        $this->requirePermission('shortLinkManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $dateRange = $request->getQueryParam('dateRange', 'last7days');
        $format = $request->getQueryParam('format', 'csv');
        $linkId = $request->getQueryParam('linkId');

        try {
            $csvData = ShortLinkManager::$plugin->analytics->exportAnalytics(
                $linkId ? (int)$linkId : null,
                $dateRange,
                $format
            );

            // Generate filename
            $baseFilename = 'shortlink-manager-analytics';
            if ($linkId) {
                $shortLink = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                    ->id($linkId)
                    ->one();
                if ($shortLink) {
                    // Clean the code for filename
                    $cleanCode = preg_replace('/[^a-zA-Z0-9-_]/', '', $shortLink->code);
                    $baseFilename = 'shortlink-' . $cleanCode . '-analytics';
                }
            }

            $filename = $baseFilename . '-' . $dateRange . '-' . date('Y-m-d') . '.' . $format;

            return Craft::$app->getResponse()->sendContentAsFile(
                $csvData,
                $filename,
                [
                    'mimeType' => $format === 'csv' ? 'text/csv' : 'application/json',
                ]
            );
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError($e->getMessage());
            return $this->redirect('shortlink-manager/analytics');
        }
    }
}
