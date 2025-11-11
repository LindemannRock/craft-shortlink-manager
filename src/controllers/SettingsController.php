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
use lindemannrock\shortlinkmanager\models\Settings;
use yii\web\Response;

/**
 * Settings Controller
 */
class SettingsController extends Controller
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
     * Settings index - redirect to general
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->redirect('shortlink-manager/settings/general');
    }

    /**
     * General settings
     *
     * @return Response
     */
    public function actionGeneral(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $settings = ShortLinkManager::$plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/general', [
            'settings' => $settings,
        ]);
    }

    /**
     * Behavior settings
     *
     * @return Response
     */
    public function actionBehavior(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $settings = ShortLinkManager::$plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/behavior', [
            'settings' => $settings,
        ]);
    }

    /**
     * QR Code settings
     *
     * @return Response
     */
    public function actionQrCode(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $plugin = ShortLinkManager::$plugin;
        $settings = $plugin->getSettings();

        // Defensive check
        if (!$settings) {
            throw new \Exception('Failed to load settings');
        }

        return $this->renderTemplate('shortlink-manager/settings/qr-code', [
            'settings' => $settings,
            'plugin' => $plugin,
        ]);
    }

    /**
     * Analytics settings
     *
     * @return Response
     */
    public function actionAnalytics(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $settings = ShortLinkManager::$plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/analytics', [
            'settings' => $settings,
        ]);
    }

    /**
     * Integrations settings
     *
     * @return Response
     */
    public function actionIntegrations(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $settings = ShortLinkManager::$plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/integrations', [
            'settings' => $settings,
        ]);
    }

    /**
     * Interface settings
     *
     * @return Response
     */
    public function actionInterface(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $settings = ShortLinkManager::$plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/interface', [
            'settings' => $settings,
        ]);
    }

    /**
     * Cache settings
     *
     * @return Response
     */
    public function actionCache(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $settings = ShortLinkManager::$plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/cache', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:manageSettings');

        $plugin = ShortLinkManager::$plugin;

        // Load current settings from database
        $settings = Settings::loadFromDatabase();
        if (!$settings) {
            $settings = new Settings();
        }

        // Get only the posted settings (fields from the current page)
        $settingsData = $this->request->getBodyParam('settings', []);

        // Handle asset field (returns array)
        if (isset($settingsData['defaultQrLogoId'])) {
            if (is_array($settingsData['defaultQrLogoId'])) {
                $settingsData['defaultQrLogoId'] = $settingsData['defaultQrLogoId'][0] ?? null;
            } elseif ($settingsData['defaultQrLogoId'] === '' || $settingsData['defaultQrLogoId'] === null) {
                // Convert empty string to null for integer type
                $settingsData['defaultQrLogoId'] = null;
            }
        }

        // Handle enabledSites checkbox group
        if (isset($settingsData['enabledSites'])) {
            if (is_array($settingsData['enabledSites'])) {
                // Convert string values to integers
                $settingsData['enabledSites'] = array_map('intval', array_filter($settingsData['enabledSites']));
            } else {
                $settingsData['enabledSites'] = [];
            }
        } else {
            // No sites selected = empty array (which means all sites enabled)
            $settingsData['enabledSites'] = [];
        }

        // Fix color fields - add # if missing
        if (isset($settingsData['defaultQrColor']) && !str_starts_with($settingsData['defaultQrColor'], '#')) {
            $settingsData['defaultQrColor'] = '#' . $settingsData['defaultQrColor'];
        }
        if (isset($settingsData['defaultQrBgColor']) && !str_starts_with($settingsData['defaultQrBgColor'], '#')) {
            $settingsData['defaultQrBgColor'] = '#' . $settingsData['defaultQrBgColor'];
        }
        if (isset($settingsData['qrEyeColor'])) {
            if (empty($settingsData['qrEyeColor'])) {
                $settingsData['qrEyeColor'] = null;
            } elseif (!str_starts_with($settingsData['qrEyeColor'], '#')) {
                $settingsData['qrEyeColor'] = '#' . $settingsData['qrEyeColor'];
            }
        }

        // Only update fields that were posted and are not overridden by config
        foreach ($settingsData as $key => $value) {
            if (!$settings->isOverriddenByConfig($key) && property_exists($settings, $key)) {
                // Handle special array field conversions
                if ($key === 'enabledIntegrations') {
                    // Decode JSON string from hidden field
                    $settings->enabledIntegrations = is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
                } elseif ($key === 'redirectManagerEvents') {
                    // Already an array from checkbox fields
                    $settings->redirectManagerEvents = is_array($value) ? $value : [];
                } elseif ($key === 'seomaticTrackingEvents') {
                    // Already an array from checkbox fields
                    $settings->seomaticTrackingEvents = is_array($value) ? $value : [];
                } else {
                    // Check for setter method first (handles array conversions, etc.)
                    $setterMethod = 'set' . ucfirst($key);
                    if (method_exists($settings, $setterMethod)) {
                        $settings->$setterMethod($value);
                    } else {
                        $settings->$key = $value;
                    }
                }
            }
        }

        // Validate (includes conflict checking via validateSlugPrefix and validateQrPrefix)
        if (!$settings->validate()) {
            $this->setFailFlash(Craft::t('shortlink-manager', 'Could not save settings.'));

            // Get the section to re-render the correct template
            $section = $this->request->getBodyParam('section', 'general');
            $template = "shortlink-manager/settings/{$section}";

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        // Save settings to database
        if ($settings->saveToDatabase()) {
            // Update the plugin's cached settings (CRITICAL - forces Craft to refresh)
            $plugin->setSettings($settings->getAttributes());

            $this->setSuccessFlash(Craft::t('shortlink-manager', 'Settings saved successfully.'));
        } else {
            $this->setFailFlash(Craft::t('shortlink-manager', 'Could not save settings.'));
            return null;
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Cleanup analytics data
     *
     * @return Response
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionCleanupAnalytics(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Check admin permissions
        if (!Craft::$app->getUser()->getIsAdmin()) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('shortlink-manager', 'Only administrators can clean up analytics data.')
            ]);
        }

        try {
            // Queue the cleanup job
            Craft::$app->queue->push(new \lindemannrock\shortlinkmanager\jobs\CleanupAnalyticsJob());

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Analytics cleanup job has been queued. It will run in the background.')
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear QR code cache
     *
     * @return Response
     */
    public function actionClearQrCache(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        try {
            $cachePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';
            $cleared = 0;

            if (is_dir($cachePath)) {
                $files = glob($cachePath . '*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $cleared++;
                    }
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Cleared {count} QR code caches.', ['count' => $cleared])
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear device detection cache
     *
     * @return Response
     */
    public function actionClearDeviceCache(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        try {
            $cachePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/devices/';
            $cleared = 0;

            if (is_dir($cachePath)) {
                $files = glob($cachePath . '*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $cleared++;
                    }
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Cleared {count} device detection caches.', ['count' => $cleared])
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all caches
     *
     * @return Response
     */
    public function actionClearAllCaches(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        try {
            $totalCleared = 0;

            // Clear QR code caches
            $qrPath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';
            if (is_dir($qrPath)) {
                $files = glob($qrPath . '*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $totalCleared++;
                    }
                }
            }

            // Clear device detection caches
            $devicePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/devices/';
            if (is_dir($devicePath)) {
                $files = glob($devicePath . '*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $totalCleared++;
                    }
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Cleared {count} total caches.', ['count' => $totalCleared])
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all analytics data
     *
     * @return Response
     */
    public function actionClearAllAnalytics(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Require admin permission for deleting analytics data
        if (!Craft::$app->getUser()->getIsAdmin()) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('shortlink-manager', 'Only administrators can clear analytics data.')
            ]);
        }

        try {
            // Get count before deleting
            $count = (new \craft\db\Query())
                ->from('{{%shortlinkmanager_analytics}}')
                ->count();

            // Delete all analytics records
            Craft::$app->db->createCommand()
                ->delete('{{%shortlinkmanager_analytics}}')
                ->execute();

            // Reset hit counts on all links
            Craft::$app->db->createCommand()
                ->update('{{%shortlinkmanager}}', ['hits' => 0])
                ->execute();

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Deleted {count} analytics records and reset all click counts.', ['count' => $count])
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}


