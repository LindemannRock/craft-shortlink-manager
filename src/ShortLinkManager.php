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
use craft\base\Model;
use craft\base\Plugin;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fields\Link as LinkField;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\LoggingLibrary;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\integrations\ShortLinkType;
use lindemannrock\shortlinkmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\AnalyticsService;
use lindemannrock\shortlinkmanager\services\DeviceDetectionService;
use lindemannrock\shortlinkmanager\services\IntegrationService;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\services\ShortLinksService;
use lindemannrock\shortlinkmanager\utilities\ShortLinkManagerUtility;
use lindemannrock\shortlinkmanager\variables\ShortLinkManagerVariable;
use lindemannrock\shortlinkmanager\widgets\AnalyticsSummaryWidget;
use lindemannrock\shortlinkmanager\widgets\TopLinksWidget;
use yii\base\Event;

/**
 * ShortLink Manager Plugin
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.0.0
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
     * @var ShortLinkManager|null Singleton plugin instance
     */
    public static ?ShortLinkManager $plugin = null;

    /**
     * @var string Plugin schema version for migrations
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin exposes a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the plugin registers a control panel section
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Bootstrap shared plugin functionality (Twig helper, logging)
        PluginHelper::bootstrap(
            $this,
            'shortlinkHelper',
            ['shortLinkManager:viewSystemLogs'],
            ['shortLinkManager:downloadSystemLogs']
        );
        PluginHelper::applyPluginNameFromConfig($this);

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

        // Register Link field integration
        Event::on(
            LinkField::class,
            LinkField::EVENT_REGISTER_LINK_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = ShortLinkType::class;
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
                // Only show cache option if user has permission to clear cache
                if (!Craft::$app->getUser()->checkPermission('shortLinkManager:clearCache')) {
                    return;
                }

                $settings = $this->getSettings();
                $displayName = $settings->getDisplayName();

                $event->options[] = [
                    'key' => 'shortlink-manager-cache',
                    'label' => Craft::t('shortlink-manager', '{displayName} caches', ['displayName' => $displayName]),
                    'action' => function() use ($settings) {
                        $cleared = 0;

                        if ($settings->cacheStorageMethod === 'redis') {
                            // Clear Redis cache
                            $cache = Craft::$app->cache;
                            if ($cache instanceof \yii\redis\Cache) {
                                $redis = $cache->redis;

                                // Get all keys from tracking sets
                                $qrKeys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet($this->id, 'qr')]) ?: [];
                                $deviceKeys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet($this->id, 'device')]) ?: [];

                                // Delete QR cache keys using Craft's cache component
                                foreach ($qrKeys as $key) {
                                    $cache->delete($key);
                                }

                                // Delete device cache keys using Craft's cache component
                                foreach ($deviceKeys as $key) {
                                    $cache->delete($key);
                                }

                                // Clear the tracking sets
                                $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet($this->id, 'qr')]);
                                $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet($this->id, 'device')]);
                            }
                        } else {
                            // Clear QR code file caches
                            $qrPath = PluginHelper::getCachePath(self::$plugin, 'qr');
                            if (is_dir($qrPath)) {
                                $files = glob($qrPath . '*.cache');
                                foreach ($files as $file) {
                                    if (@unlink($file)) {
                                        $cleared++;
                                    }
                                }
                            }

                            // Clear device detection file caches
                            $devicePath = PluginHelper::getCachePath(self::$plugin, 'device');
                            if (is_dir($devicePath)) {
                                $files = glob($devicePath . '*.cache');
                                foreach ($files as $file) {
                                    if (@unlink($file)) {
                                        $cleared++;
                                    }
                                }
                            }
                        }

                        $this->logInfo('Cleared cache entries', ['count' => $cleared]);
                    },
                ];
            }
        );

        // Install event listeners for element changes
        $this->installEventListeners();

        // Install sidebar event listeners (only for non-console requests)
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->installSidebarListeners();
        }

        // DO NOT log in init() - it's called on every request
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $settings = $this->getSettings();

        // Show nav if enabled for ANY site (not just current site)
        // This allows managing shortlinks from any CP context, even if
        // shortlinks are only enabled for a dedicated short URL site
        $enabledSiteIds = $settings->getEnabledSiteIds();
        if (empty($enabledSiteIds)) {
            return null; // Hide navigation item if not enabled for any site
        }

        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        if ($item) {
            $item['label'] = $settings->getFullName();
            $item['icon'] = '@appicons/link-simple.svg';

            $sections = $this->getCpSections($settings);
            $item['subnav'] = CpNavHelper::buildSubnav($user, $settings, $sections);

            // Add logs section using the logging library
            if (PluginHelper::isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'shortLinkManager:viewSystemLogs',
                ]);
            }

            // Hide from nav if no accessible subnav items
            if (empty($item['subnav'])) {
                return null;
            }
        }

        return $item;
    }

    /**
     * Get CP sections for nav + default route resolution
     *
     * @param Settings $settings
     * @param bool $includeLinks
     * @param bool $includeLogs
     * @return array
     * @since 5.14.0
     */
    public function getCpSections(Settings $settings, bool $includeLinks = true, bool $includeLogs = false): array
    {
        $sections = [];

        if ($includeLinks) {
            $sections[] = [
                'key' => 'shortlinks',
                'label' => Craft::t('shortlink-manager', 'Links'),
                'url' => 'shortlink-manager',
                'permissionsAll' => ['shortLinkManager:viewLinks'],
            ];
        }

        $sections[] = [
            'key' => 'analytics',
            'label' => Craft::t('shortlink-manager', 'Analytics'),
            'url' => 'shortlink-manager/analytics',
            'permissionsAll' => ['shortLinkManager:viewAnalytics'],
            'when' => $settings->enableAnalytics,
        ];

        if ($includeLogs) {
            $sections[] = [
                'key' => 'logs',
                'label' => Craft::t('shortlink-manager', 'Logs'),
                'url' => 'shortlink-manager/logs',
                'permissionsAll' => ['shortLinkManager:viewSystemLogs'],
                'when' => fn() => PluginHelper::isPluginEnabled('logging-library'),
            ];
        }

        $sections[] = [
            'key' => 'settings',
            'label' => Craft::t('shortlink-manager', 'Settings'),
            'url' => 'shortlink-manager/settings',
            'permissionsAll' => ['shortLinkManager:manageSettings'],
        ];

        return $sections;
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
            // Override with config file values using Craft's native multi-environment handling
            // This properly merges '*' with environment-specific configs (e.g., 'production')
            $config = Craft::$app->getConfig()->getConfigFromFile('shortlink-manager');
            if (!empty($config) && is_array($config)) {
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
        $settings = $this->getSettings();
        $plural = $settings->getPluralLowerDisplayName();

        return [
            // Shortlinks - grouped
            'shortLinkManager:manageLinks' => [
                'label' => Craft::t('shortlink-manager', 'Manage {plural}', ['plural' => $plural]),
                'nested' => [
                    'shortLinkManager:viewLinks' => [
                        'label' => Craft::t('shortlink-manager', 'View {plural}', ['plural' => $plural]),
                    ],
                    'shortLinkManager:createLinks' => [
                        'label' => Craft::t('shortlink-manager', 'Create {plural}', ['plural' => $plural]),
                    ],
                    'shortLinkManager:editLinks' => [
                        'label' => Craft::t('shortlink-manager', 'Edit {plural}', ['plural' => $plural]),
                    ],
                    'shortLinkManager:deleteLinks' => [
                        'label' => Craft::t('shortlink-manager', 'Delete {plural}', ['plural' => $plural]),
                    ],
                ],
            ],
            'shortLinkManager:viewAnalytics' => [
                'label' => Craft::t('shortlink-manager', 'View analytics'),
                'nested' => [
                    'shortLinkManager:exportAnalytics' => [
                        'label' => Craft::t('shortlink-manager', 'Export analytics'),
                    ],
                    'shortLinkManager:clearAnalytics' => [
                        'label' => Craft::t('shortlink-manager', 'Clear analytics'),
                    ],
                ],
            ],
            'shortLinkManager:clearCache' => [
                'label' => Craft::t('shortlink-manager', 'Clear cache'),
            ],
            'shortLinkManager:viewLogs' => [
                'label' => Craft::t('shortlink-manager', 'View logs'),
                'nested' => [
                    'shortLinkManager:viewSystemLogs' => [
                        'label' => Craft::t('shortlink-manager', 'View system logs'),
                        'nested' => [
                            'shortLinkManager:downloadSystemLogs' => [
                                'label' => Craft::t('shortlink-manager', 'Download system logs'),
                            ],
                        ],
                    ],
                ],
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
            // Check if a cleanup job is already scheduled
            $existingJob = (new \craft\db\Query())
                ->from('{{%queue}}')
                ->where(['like', 'job', 'shortlinkmanager'])
                ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
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

    /**
     * Install sidebar event listeners for displaying shortlink info
     */
    private function installSidebarListeners(): void
    {
        // Listen to Entry sidebar HTML
        Event::on(
            \craft\elements\Entry::class,
            \craft\base\Element::EVENT_DEFINE_SIDEBAR_HTML,
            function(\craft\events\DefineHtmlEvent $event) {
                /** @var \craft\elements\Entry $entry */
                $entry = $event->sender;

                // Check if ShortLink Manager is enabled for this site
                $settings = $this->getSettings();
                if (!$settings->isSiteEnabled($entry->siteId)) {
                    return;
                }

                // Check if entry has a shortlink
                $shortLink = $this->shortLinks->getByElement($entry);

                if ($shortLink) {
                    $html = Craft::$app->getView()->renderTemplate('shortlink-manager/_sidebars/shortlink-info', [
                        'shortLink' => $shortLink,
                        'element' => $entry,
                    ]);

                    $event->html .= $html;
                }
            }
        );

        // TODO: Add support for other element types (Category, Asset, etc.)
    }
}
