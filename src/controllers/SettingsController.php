<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\SettingsPostHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\ForbiddenHttpException;
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
     * @var bool
     */
    private bool $readOnly = false;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if ($action->id === 'save-field-layout' && !Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException(Craft::t('shortlink-manager', 'Administrative changes are disallowed in this environment.'));
        }

        $this->readOnly = ($action->id === 'field-layout' || $action->id === 'save-field-layout')
            && !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;

        return parent::beforeAction($action);
    }

    /**
     * Settings index - redirect to general
     *
     * @return Response
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
     * Field Layout settings
     *
     * @return Response
     * @since 5.21.0
     */
    public function actionFieldLayout(): Response
    {
        $this->requirePermission('shortLinkManager:manageSettings');

        $fieldLayouts = Craft::$app->getProjectConfig()->get('shortlink-manager.fieldLayouts') ?? [];
        $fieldLayout = null;

        if (!empty($fieldLayouts)) {
            $fieldLayoutUid = array_key_first($fieldLayouts);
            $fieldLayout = Craft::$app->getFields()->getLayoutByUid($fieldLayoutUid);
        }

        if (!$fieldLayout) {
            $oldUid = Craft::$app->getProjectConfig()->get('shortlink-manager.fieldLayout');
            if ($oldUid) {
                $fieldLayout = Craft::$app->getFields()->getLayoutByUid($oldUid);
            }
        }

        if (!$fieldLayout) {
            $fieldLayout = Craft::$app->getFields()->getLayoutByType(ShortLink::class);
        }

        if (!$fieldLayout) {
            $fieldLayout = new \craft\models\FieldLayout([
                'type' => ShortLink::class,
            ]);

            Craft::$app->getFields()->saveLayout($fieldLayout);

            if (!$this->readOnly) {
                $fieldLayoutConfig = $fieldLayout->getConfig();
                if ($fieldLayoutConfig) {
                    Craft::$app->getProjectConfig()->set(
                        "shortlink-manager.fieldLayouts.{$fieldLayout->uid}",
                        $fieldLayoutConfig,
                        "Create ShortLink Manager field layout"
                    );
                }
            }
        }

        $this->logDebug('Field Layout debug info', [
            'id' => $fieldLayout->id ?? 'null',
            'uid' => $fieldLayout->uid ?? 'null',
            'type' => $fieldLayout->type ?? 'null',
            'class' => get_class($fieldLayout),
        ]);

        return $this->renderTemplate('shortlink-manager/settings/field-layout', [
            'fieldLayout' => $fieldLayout,
            'readOnly' => $this->readOnly,
        ]);
    }

    /**
     * Save field layout
     *
     * @return Response|null
     * @since 5.21.0
     */
    public function actionSaveFieldLayout(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:manageSettings');

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = ShortLink::class;

        if (!Craft::$app->getFields()->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Couldn\'t save field layout.'));
            return null;
        }

        $fieldLayoutConfig = $fieldLayout->getConfig();
        if ($fieldLayoutConfig) {
            Craft::$app->getProjectConfig()->set(
                "shortlink-manager.fieldLayouts.{$fieldLayout->uid}",
                $fieldLayoutConfig,
                "Save ShortLink Manager field layout"
            );

            if (Craft::$app->getProjectConfig()->get('shortlink-manager.fieldLayout')) {
                Craft::$app->getProjectConfig()->remove('shortlink-manager.fieldLayout');
            }
        }

        Craft::$app->getSession()->setNotice(Craft::t('shortlink-manager', 'Field layout saved.'));
        return $this->redirectToPostedUrl();
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

        $section = $this->_validSettingsSection(
            $this->request->getBodyParam('section', 'general'),
        );

        // Validate only fields belonging to the current settings section.
        $result = SettingsPostHelper::apply(
            model: $settings,
            postedValues: $settingsData,
            allowedAttributes: $this->_validationAttributesForSection($section),
            shouldSkipAttribute: fn(string $attribute): bool => $settings->isOverriddenByConfig($attribute),
            adapters: [
                'enabledIntegrations' => static fn(mixed $value): array => is_string($value)
                    ? (json_decode($value, true) ?: [])
                    : (is_array($value) ? $value : []),
                'redirectManagerEvents' => static fn(mixed $value): array => is_array($value) ? $value : [],
                'seomaticTrackingEvents' => static fn(mixed $value): array => is_array($value) ? $value : [],
            ],
        );
        $attributesToValidate = $result->attributesToValidate;

        if ($result->hasErrors || !$settings->validate($attributesToValidate)) {
            $this->setFailFlash(Craft::t('shortlink-manager', 'Could not save settings.'));

            return $this->renderTemplate("shortlink-manager/settings/{$section}", [
                'settings' => $settings,
            ]);
        }

        // Save settings to database
        if ($settings->saveToDatabase($attributesToValidate)) {
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
            $cleared = ShortLinkManager::$plugin->localCache->clearQrCache();

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('shortlink-manager', 'QR code cache cleared successfully.')
                : Craft::t('shortlink-manager', 'Cleared {count, plural, =1{# QR code cache} other{# QR code caches}}.', ['count' => $cleared]);

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
            $cleared = ShortLinkManager::$plugin->localCache->clearDeviceCache();

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('shortlink-manager', 'Device cache cleared successfully.')
                : Craft::t('shortlink-manager', 'Cleared {count, plural, =1{# device cache} other{# device caches}}.', ['count' => $cleared]);

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

            if ($settings->cacheStorageMethod === 'redis') {
                ShortLinkManager::$plugin->localCache->clearAllCaches();
                $message = Craft::t('shortlink-manager', 'All caches cleared successfully.');
            } else {
                $qrCount = ShortLinkManager::$plugin->localCache->clearQrCache();
                $deviceCount = ShortLinkManager::$plugin->localCache->clearDeviceCache();
                $message = Craft::t('shortlink-manager', 'Cleared {qrCount, plural, =1{# QR code cache} other{# QR code caches}} and {deviceCount, plural, =1{# device cache} other{# device caches}}.', [
                    'qrCount' => $qrCount,
                    'deviceCount' => $deviceCount,
                ]);
            }

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
     * Queue a Servd static-cache purge for all public ShortLink URLs.
     *
     * @return Response
     * @since 5.25.0
     */
    public function actionPurgeServdStaticCache(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:clearCache');
        $this->requireAcceptsJson();

        try {
            if (!ShortLinkManager::$plugin->servdStaticCache->isAvailable()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('shortlink-manager', 'Servd static cache is not available.'),
                ]);
            }

            ShortLinkManager::$plugin->servdStaticCache->purgeAllUrls();

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('shortlink-manager', 'Servd static cache purge queued.'),
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
        $allowed = ['general', 'behavior', 'qr-code', 'analytics', 'integrations', 'interface', 'cache', 'field-layout'];

        return in_array($section, $allowed, true) ? $section : 'general';
    }

    /**
     * Get validation attributes for a settings section.
     */
    private function _validationAttributesForSection(string $section): array
    {
        return match ($section) {
            'general' => [
                'pluginName',
                'enabledSites',
                'usePrefix',
                'slugPrefix',
                'qrPrefix',
                'shortlinkBaseUrl',
                'codeLength',
                'redirectTemplate',
                'expiredTemplate',
                'qrTemplate',
                'expiredMessage',
                'logLevel',
            ],
            'behavior' => [
                'notFoundRedirectUrl',
                'defaultHttpCode',
                'passQueryParams',
                'directRedirect',
            ],
            'qr-code' => [
                'defaultQrSize',
                'defaultQrFormat',
                'defaultQrColor',
                'defaultQrBgColor',
                'defaultQrMargin',
                'qrModuleStyle',
                'qrEyeStyle',
                'qrEyeColor',
                'enableQrLogo',
                'qrLogoVolumeUid',
                'defaultQrLogoId',
                'qrLogoSize',
                'defaultQrErrorCorrection',
                'enableQrDownload',
                'qrDownloadFilename',
            ],
            'analytics' => [
                'enableAnalytics',
                'enableGeoDetection',
                'geoProvider',
                'geoApiKey',
                'anonymizeIpAddress',
                'analyticsRetention',
            ],
            'integrations' => [
                'enabledIntegrations',
                'seomaticTrackingEvents',
                'seomaticEventPrefix',
                'redirectManagerEvents',
            ],
            'interface' => [
                'itemsPerPage',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'defaultDateRange',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'cache' => [
                'cacheStorageMethod',
                'enableQrCodeCache',
                'qrCodeCacheDuration',
                'cacheDeviceDetection',
                'deviceDetectionCacheDuration',
            ],
            default => [],
        };
    }
}
