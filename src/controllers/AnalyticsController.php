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
     * Get link analytics data via AJAX
     *
     * @return Response
     */
    public function actionGetLinkAnalytics(): Response
    {
        $this->requireLogin();
        $this->requireAcceptsJson();

        $linkId = Craft::$app->getRequest()->getParam('linkId');
        $range = Craft::$app->getRequest()->getParam('range', 'last7days');

        if (!$linkId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Link ID is required'
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
                    'error' => 'Short link not found'
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
                'html' => $html
            ]);
        } catch (\Exception $e) {
            $this->logError('Failed to get link analytics data', ['error' => $e->getMessage()]);
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
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
            $settings = ShortLinkManager::$plugin->getSettings();
            $filenamePart = strtolower(str_replace(' ', '-', $settings->getPluralLowerDisplayName()));
            $baseFilename = $filenamePart . '-analytics';
            if ($linkId) {
                $shortLink = \lindemannrock\shortlinkmanager\elements\ShortLink::find()
                    ->id($linkId)
                    ->one();
                if ($shortLink) {
                    // Clean the code for filename
                    $cleanCode = preg_replace('/[^a-zA-Z0-9-_]/', '', $shortLink->code);
                    $singularPart = strtolower(str_replace(' ', '-', $settings->getLowerDisplayName()));
                    $baseFilename = $singularPart . '-' . $cleanCode . '-analytics';
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
