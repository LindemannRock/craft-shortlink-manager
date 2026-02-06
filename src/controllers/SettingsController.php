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
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @since 5.0.0
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
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Settings index - redirect to general
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');
        return $this->redirect('shortlink-manager/settings/general');
    }

    /**
     * General settings
     *
     * @return Response
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
     */
    public function actionQrCode(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $plugin = ShortLinkManager::$plugin;
        $settings = $plugin->getSettings();

        return $this->renderTemplate('shortlink-manager/settings/qr-code', [
            'settings' => $settings,
            'plugin' => $plugin,
        ]);
    }

    /**
     * Analytics settings
     *
     * @return Response
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.3.0
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
     * @since 5.0.0
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:manageSettings');

        $plugin = ShortLinkManager::$plugin;

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // Get only the posted settings (fields from the current page)
        $settingsData = $this->request->getBodyParam('settings', []);

        // Handle asset field (returns array)
        if (isset($settingsData['defaultQrLogoId'])) {
            if (is_array($settingsData['defaultQrLogoId'])) {
                $settingsData['defaultQrLogoId'] = $settingsData['defaultQrLogoId'][0] ?? null;
            } elseif ($settingsData['defaultQrLogoId'] === '') {
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
            $section = $this->_validSettingsSection(
                $this->request->getBodyParam('section', 'general'),
            );

            return $this->renderTemplate("shortlink-manager/settings/{$section}", [
                'settings' => $settings,
            ]);
        }

        // Save settings to database
        if ($settings->saveToDatabase()) {
            // Update the plugin's cached settings (CRITICAL - forces Craft to refresh)
            $plugin->setSettings($settings->getAttributes());

            $this->setSuccessFlash(Craft::t('shortlink-manager', 'Settings saved.'));
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
     * @since 5.0.0
     */
    public function actionCleanupAnalytics(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $this->requirePermission('shortLinkManager:clearAnalytics');

        try {
            // Queue the cleanup job
            Craft::$app->queue->push(new \lindemannrock\shortlinkmanager\jobs\CleanupAnalyticsJob());

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Analytics cleanup job has been queued. It will run in the background.'),
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
     * Clear QR code cache
     *
     * @return Response
     * @since 5.3.0
     */
    public function actionClearQrCache(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:clearCache');
        $this->requireAcceptsJson();

        try {
            $settings = ShortLinkManager::$plugin->getSettings();
            $cleared = 0;

            if ($settings->cacheStorageMethod === 'redis') {
                // Clear Redis cache
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;

                    // Get all QR cache keys from tracking set
                    $keys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'qr')]) ?: [];

                    // Delete QR cache keys using Craft's cache component
                    foreach ($keys as $key) {
                        $cache->delete($key);
                    }

                    // Clear the tracking set
                    $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'qr')]);
                }
            } else {
                // Clear file cache
                $cachePath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'qr');
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . '*.cache');
                    foreach ($files as $file) {
                        if (@unlink($file)) {
                            $cleared++;
                        }
                    }
                }
            }

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('shortlink-manager', 'QR code cache cleared successfully.')
                : Craft::t('shortlink-manager', 'Cleared {count} QR code caches.', ['count' => $cleared]);

            return $this->asJson([
                'success' => true,
                'message' => $message,
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
     * Clear device detection cache
     *
     * @return Response
     * @since 5.3.0
     */
    public function actionClearDeviceCache(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:clearCache');
        $this->requireAcceptsJson();

        try {
            $settings = ShortLinkManager::$plugin->getSettings();
            $cleared = 0;

            if ($settings->cacheStorageMethod === 'redis') {
                // Clear Redis cache
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;

                    // Get all device cache keys from tracking set
                    $keys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'device')]) ?: [];

                    // Delete device cache keys using Craft's cache component
                    foreach ($keys as $key) {
                        $cache->delete($key);
                    }

                    // Clear the tracking set
                    $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'device')]);
                }
            } else {
                // Clear file cache
                $cachePath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'device');
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . '*.cache');
                    foreach ($files as $file) {
                        if (@unlink($file)) {
                            $cleared++;
                        }
                    }
                }
            }

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('shortlink-manager', 'Device cache cleared successfully.')
                : Craft::t('shortlink-manager', 'Cleared {count} device detection caches.', ['count' => $cleared]);

            return $this->asJson([
                'success' => true,
                'message' => $message,
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
     * Clear all caches
     *
     * @return Response
     * @since 5.3.0
     */
    public function actionClearAllCaches(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:clearCache');
        $this->requireAcceptsJson();

        try {
            $settings = ShortLinkManager::$plugin->getSettings();
            $totalCleared = 0;

            if ($settings->cacheStorageMethod === 'redis') {
                // Clear Redis cache
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;

                    // Get all QR cache keys from tracking set
                    $qrKeys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'qr')]) ?: [];

                    // Delete QR cache keys using Craft's cache component
                    foreach ($qrKeys as $key) {
                        $cache->delete($key);
                    }

                    // Get all device cache keys from tracking set
                    $deviceKeys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'device')]) ?: [];

                    // Delete device cache keys using Craft's cache component
                    foreach ($deviceKeys as $key) {
                        $cache->delete($key);
                    }

                    // Clear the tracking sets
                    $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'qr')]);
                    $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet(ShortLinkManager::$plugin->id, 'device')]);
                }
            } else {
                // Clear QR code file caches
                $qrPath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'qr');
                if (is_dir($qrPath)) {
                    $files = glob($qrPath . '*.cache');
                    foreach ($files as $file) {
                        if (@unlink($file)) {
                            $totalCleared++;
                        }
                    }
                }

                // Clear device detection file caches
                $devicePath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'device');
                if (is_dir($devicePath)) {
                    $files = glob($devicePath . '*.cache');
                    foreach ($files as $file) {
                        if (@unlink($file)) {
                            $totalCleared++;
                        }
                    }
                }
            }

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('shortlink-manager', 'All caches cleared successfully.')
                : Craft::t('shortlink-manager', 'Cleared {count} total caches.', ['count' => $totalCleared]);

            return $this->asJson([
                'success' => true,
                'message' => $message,
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
     * Clear all analytics data
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionClearAllAnalytics(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:clearAnalytics');

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
                'message' => Craft::t('shortlink-manager', 'Deleted {count} analytics records and reset all click counts.', ['count' => $count]),
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
     * Validate settings section against allowlist to prevent path traversal.
     */
    private function _validSettingsSection(string $section): string
    {
        $allowed = ['general', 'behavior', 'qr-code', 'analytics', 'integrations', 'interface', 'cache'];

        return in_array($section, $allowed, true) ? $section : 'general';
    }
}
