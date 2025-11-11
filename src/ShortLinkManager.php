<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * Advanced shortlink management with QR codes, analytics
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager;

use Craft;
use craft\base\Plugin;
use craft\base\Model;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\ElementEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\ShortLinksService;
use lindemannrock\shortlinkmanager\services\AnalyticsService;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\services\DeviceDetectionService;
use lindemannrock\shortlinkmanager\services\IntegrationService;
use lindemannrock\shortlinkmanager\variables\ShortLinkManagerVariable;
use lindemannrock\shortlinkmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\shortlinkmanager\utilities\ShortLinkManagerUtility;
use lindemannrock\shortlinkmanager\widgets\AnalyticsSummaryWidget;
use lindemannrock\shortlinkmanager\widgets\TopLinksWidget;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\logginglibrary\LoggingLibrary;
use yii\base\Event;

/**
 * ShortLink Manager Plugin
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     1.0.0
 *
 * @property-read ShortLinksService $shortLinks
 * @property-read AnalyticsService $analytics
 * @property-read QrCodeService $qrCode
 * @property-read DeviceDetectionService $deviceDetection
 * @property-read IntegrationService $integration
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class ShortLinkManager extends Plugin
{
    use LoggingTrait;

    /**
     * @var ShortLinkManager|null
     */
    public static ?ShortLinkManager $plugin = null;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Configure logging
        $settings = $this->getSettings();
        LoggingLibrary::configure([
            'pluginHandle' => $this->handle,
            'pluginName' => $settings->pluginName ?? $this->name,
            'logLevel' => $settings->logLevel ?? 'error',
            'itemsPerPage' => $settings->itemsPerPage ?? 50,
            'permissions' => ['shortLinkManager:viewLogs'],
        ]);

        // Set plugin name from config if available
        $configPath = Craft::$app->getPath()->getConfigPath() . '/shortlink-manager.php';
        if (file_exists($configPath)) {
            $rawConfig = require $configPath;
            if (isset($rawConfig['pluginName'])) {
                $this->name = $rawConfig['pluginName'];
            }
        }

        // Register services
        $this->setComponents([
            'shortLinks' => ShortLinksService::class,
            'analytics' => AnalyticsService::class,
            'qrCode' => QrCodeService::class,
            'deviceDetection' => DeviceDetectionService::class,
            'integration' => IntegrationService::class,
        ]);

        // Schedule analytics cleanup if retention is enabled
        $this->scheduleAnalyticsCleanup();

        // Register translations
        Craft::$app->i18n->translations['shortlink-manager'] = [
            'class' => \craft\i18n\PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/translations',
            'forceTranslation' => true,
            'allowOverrides' => true,
        ];

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('shortLinkManager', ShortLinkManagerVariable::class);
            }
        );

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getCpUrlRules());
            }
        );

        // Register site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Add at the BEGINNING of rules array (higher priority)
                $event->rules = array_merge($this->getSiteUrlRules(), $event->rules);
            }
        );

        // Register element type
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = \lindemannrock\shortlinkmanager\elements\ShortLink::class;
            }
        );

        // Register field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = \lindemannrock\shortlinkmanager\fields\ShortLinkField::class;
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('shortlink-manager', 'ShortLink Manager'),
                    'permissions' => $this->getPluginPermissions(),
                ];
            }
        );

        // Register dashboard widgets
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = AnalyticsSummaryWidget::class;
                $event->types[] = TopLinksWidget::class;
            }
        );

        // Register utilities
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = ShortLinkManagerUtility::class;
            }
        );

        // Register cache clearing options
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $settings = $this->getSettings();
                $pluginName = $settings->pluginName ?? 'ShortLink Manager';

                $event->options[] = [
                    'key' => 'shortlink-manager-cache',
                    'label' => Craft::t('shortlink-manager', '{pluginName} Cache', ['pluginName' => $pluginName]),
                    'action' => function() {
                        $cleared = 0;

                        // Clear QR code caches
                        $qrPath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';
                        if (is_dir($qrPath)) {
                            $files = glob($qrPath . '*.cache');
                            foreach ($files as $file) {
                                if (@unlink($file)) {
                                    $cleared++;
                                }
                            }
                        }

                        Craft::info('Cleared ShortLink Manager cache entries', __METHOD__, ['count' => $cleared]);
                    },
                ];
            }
        );

        // Install event listeners for element changes
        $this->installEventListeners();

        // DO NOT log in init() - it's called on every request
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        // Check if ShortLink Manager is enabled for the current site
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $settings = $this->getSettings();

        if (!$settings->isSiteEnabled($currentSite->id)) {
            return null; // Hide navigation item entirely
        }

        $item = parent::getCpNavItem();

        if ($item) {
            $item['label'] = $settings->pluginName;
            $item['icon'] = '@appicons/link.svg';

            // Get singular name for menu
            $pluginName = $this->getSettings()->pluginName ?? 'ShortLink Manager';
            $singularName = rtrim($pluginName, 's'); // Remove trailing 's' for singular

            $item['subnav'] = [
                'shortlinks' => [
                    'label' => 'Links',
                    'url' => 'shortlink-manager',
                ],
            ];

            // Add analytics if enabled
            if ($this->getSettings()->enableAnalytics) {
                $item['subnav']['analytics'] = [
                    'label' => Craft::t('shortlink-manager', 'Analytics'),
                    'url' => 'shortlink-manager/analytics',
                ];
            }

            // Add logs section using the logging library
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'shortLinkManager:viewLogs'
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('shortLinkManager:manageSettings')) {
                $item['subnav']['settings'] = [
                    'label' => Craft::t('shortlink-manager', 'Settings'),
                    'url' => 'shortlink-manager/settings',
                ];
            }
        }

        return $item;
    }

    /**
     * Get sites where ShortLink Manager is enabled
     *
     * @return array
     */
    public function getEnabledSites(): array
    {
        $settings = $this->getSettings();
        $enabledSiteIds = $settings->getEnabledSiteIds();

        // Return only enabled sites
        return array_filter(Craft::$app->getSites()->getAllSites(), function($site) use ($enabledSiteIds) {
            return in_array($site->id, $enabledSiteIds);
        });
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        // Load settings from database
        try {
            return Settings::loadFromDatabase();
        } catch (\Exception $e) {
            $this->logInfo('Could not load settings from database', ['error' => $e->getMessage()]);
            return new Settings();
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): ?Model
    {
        $settings = parent::getSettings();

        if ($settings) {
            // Load config file settings and merge with database values
            $configPath = Craft::$app->getPath()->getConfigPath() . '/shortlink-manager.php';
            if (file_exists($configPath)) {
                $config = require $configPath;

                // Apply environment-specific overrides
                $env = Craft::$app->getConfig()->env;
                if ($env && isset($config[$env])) {
                    $config = array_merge($config, $config[$env]);
                }

                // Apply wildcard overrides
                if (isset($config['*'])) {
                    $config = array_merge($config, $config['*']);
                }

                // Remove environment-specific keys
                unset($config['*'], $config['dev'], $config['staging'], $config['production']);

                // Set config values (these override database values)
                foreach ($config as $key => $value) {
                    if (property_exists($settings, $key)) {
                        $settings->$key = $value;
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('shortlink-manager/settings');
    }

    /**
     * Get CP URL rules
     */
    private function getCpUrlRules(): array
    {
        return [
            // Links routes (default page)
            'shortlink-manager' => 'shortlink-manager/shortlinks/index',
            'shortlink-manager/shortlinks' => 'shortlink-manager/shortlinks/index',
            'shortlink-manager/shortlinks/new' => 'shortlink-manager/shortlinks/edit',
            'shortlink-manager/shortlinks/<shortLinkId:\d+>' => 'shortlink-manager/shortlinks/edit',

            // Analytics routes
            'shortlink-manager/analytics' => 'shortlink-manager/analytics/index',

            // Settings routes
            'shortlink-manager/settings' => 'shortlink-manager/settings/index',
            'shortlink-manager/settings/general' => 'shortlink-manager/settings/general',
            'shortlink-manager/settings/behavior' => 'shortlink-manager/settings/behavior',
            'shortlink-manager/settings/qr-code' => 'shortlink-manager/settings/qr-code',
            'shortlink-manager/settings/analytics' => 'shortlink-manager/settings/analytics',
            'shortlink-manager/settings/integrations' => 'shortlink-manager/settings/integrations',
            'shortlink-manager/settings/interface' => 'shortlink-manager/settings/interface',
            'shortlink-manager/settings/cache' => 'shortlink-manager/settings/cache',
            'shortlink-manager/settings/cleanup-analytics' => 'shortlink-manager/settings/cleanup-analytics',

            // QR Code generation for preview
            'shortlink-manager/qr-code/generate' => 'shortlink-manager/qr-code/generate',
            'shortlink-manager/qr-code/download' => 'shortlink-manager/qr-code/download',

            // Logging routes
            'shortlink-manager/logs' => 'logging-library/logs/index',
            'shortlink-manager/logs/download' => 'logging-library/logs/download',
        ];
    }

    /**
     * Get site URL rules
     */
    private function getSiteUrlRules(): array
    {
        $settings = $this->getSettings();
        $slugPrefix = $settings->slugPrefix;
        $qrPrefix = $settings->qrPrefix ?? 'qr';

        return [
            // Shortlink redirect route
            $slugPrefix . '/<code:[a-zA-Z0-9\-\_]+>' => 'shortlink-manager/redirect/index',
            // QR Code routes - supports both standalone ('qr') and nested ('s/qr') patterns
            $qrPrefix . '/<code:[a-zA-Z0-9\-\_]+>' => 'shortlink-manager/qr-code/generate',
            $qrPrefix . '/<code:[a-zA-Z0-9\-\_]+>/view' => 'shortlink-manager/qr-code/display',
        ];
    }

    /**
     * Get plugin permissions
     */
    private function getPluginPermissions(): array
    {
        return [
            'shortLinkManager:viewLinks' => [
                'label' => Craft::t('shortlink-manager', 'View shortlinks'),
            ],
            'shortLinkManager:createLinks' => [
                'label' => Craft::t('shortlink-manager', 'Create shortlinks'),
            ],
            'shortLinkManager:editLinks' => [
                'label' => Craft::t('shortlink-manager', 'Edit shortlinks'),
            ],
            'shortLinkManager:deleteLinks' => [
                'label' => Craft::t('shortlink-manager', 'Delete shortlinks'),
            ],
            'shortLinkManager:viewAnalytics' => [
                'label' => Craft::t('shortlink-manager', 'View analytics'),
            ],
            'shortLinkManager:exportAnalytics' => [
                'label' => Craft::t('shortlink-manager', 'Export analytics'),
            ],
            'shortLinkManager:viewLogs' => [
                'label' => Craft::t('shortlink-manager', 'View logs'),
            ],
            'shortLinkManager:manageSettings' => [
                'label' => Craft::t('shortlink-manager', 'Manage settings'),
            ],
        ];
    }

    /**
     * Schedule analytics cleanup job
     */
    private function scheduleAnalyticsCleanup(): void
    {
        $settings = $this->getSettings();

        // Only schedule cleanup if analytics is enabled and retention is set
        if ($settings->enableAnalytics && $settings->analyticsRetention > 0) {
            // Check if a cleanup job is already scheduled (within next 24 hours)
            $existingJob = (new \craft\db\Query())
                ->from('{{%queue}}')
                ->where(['like', 'job', 'shortlinkmanager'])
                ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
                ->andWhere(['<=', 'timePushed', time() + 86400]) // Within next 24 hours
                ->exists();

            if (!$existingJob) {
                $job = new CleanupAnalyticsJob([
                    'reschedule' => true,
                ]);

                // Add to queue with a small initial delay
                // The job will re-queue itself to run every 24 hours
                Craft::$app->queue->delay(5 * 60)->push($job);

                $this->logInfo('Scheduled initial analytics cleanup job', ['interval' => '24 hours']);
            }
        }
    }

    /**
     * Install event listeners for element changes
     */
    private function installEventListeners(): void
    {
        // Listen for element URI changes to update shortlink destinations
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                if (!$event->isNew) {
                    $this->shortLinks->onSaveElement($event->element);
                }
            }
        );

        // Listen for element deletions to delete associated shortlinks
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->shortLinks->onDeleteElement($event->element);
            }
        );
    }
}