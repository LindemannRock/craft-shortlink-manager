<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * Advanced shortlink management with QR codes, analytics
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\DeleteElementEvent;
use craft\events\ElementEvent;
use craft\events\ExecuteGqlQueryEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fields\Link as LinkField;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\LoggingLibrary;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\gql\queries\ShortLinkQuery;
use lindemannrock\shortlinkmanager\gql\types\ShortLinkType as GqlShortLinkType;
use lindemannrock\shortlinkmanager\integrations\seomatic\SeoShortLink;
use lindemannrock\shortlinkmanager\integrations\ShortLinkType;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\AnalyticsCleanupScheduler;
use lindemannrock\shortlinkmanager\services\AnalyticsService;
use lindemannrock\shortlinkmanager\services\CacheStorageService;
use lindemannrock\shortlinkmanager\services\DeviceDetectionService;
use lindemannrock\shortlinkmanager\services\FrontendService;
use lindemannrock\shortlinkmanager\services\IntegrationService;
use lindemannrock\shortlinkmanager\services\LocalCacheService;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\services\ServdStaticCacheService;
use lindemannrock\shortlinkmanager\services\SetupService;
use lindemannrock\shortlinkmanager\services\ShortLinksService;
use lindemannrock\shortlinkmanager\services\TaxonomyService;
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
 * @property-read AnalyticsCleanupScheduler $analyticsCleanupScheduler
 * @property-read CacheStorageService $cacheStorage
 * @property-read QrCodeService $qrCode
 * @property-read DeviceDetectionService $deviceDetection
 * @property-read FrontendService $frontend
 * @property-read IntegrationService $integration
 * @property-read LocalCacheService $localCache
 * @property-read TaxonomyService $taxonomy
 * @property-read ServdStaticCacheService $servdStaticCache
 * @property-read SetupService $setup
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
    public string $schemaVersion = '1.1.0';

    /**
     * @var bool Whether the plugin exposes a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the plugin settings page is accessible when allowAdminChanges is false
     */
    public bool $hasReadOnlyCpSettings = true;

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
            ['shortLinkManager:downloadSystemLogs'],
            [
                'installExperience' => [
                    'headline' => Craft::t('shortlink-manager', 'ShortLink Manager'),
                    'body' => Craft::t('shortlink-manager', 'Create short links, generate QR codes, and track performance from one control panel workspace.'),
                    'ctaLabel' => Craft::t('shortlink-manager', 'Complete setup'),
                    'ctaUrl' => 'shortlink-manager/setup',
                    'redirectUri' => 'shortlink-manager/setup',
                    'confettiPreset' => 'surprise',
                ],
            ]
        );
        PluginHelper::applyPluginNameFromConfig($this);

        // Register services
        $this->setComponents([
            'shortLinks' => ShortLinksService::class,
            'analytics' => AnalyticsService::class,
            'analyticsCleanupScheduler' => AnalyticsCleanupScheduler::class,
            'cacheStorage' => CacheStorageService::class,
            'qrCode' => QrCodeService::class,
            'deviceDetection' => DeviceDetectionService::class,
            'frontend' => FrontendService::class,
            'integration' => IntegrationService::class,
            'localCache' => LocalCacheService::class,
            'taxonomy' => TaxonomyService::class,
            'servdStaticCache' => ServdStaticCacheService::class,
            'setup' => SetupService::class,
        ]);

        // Schedule analytics cleanup if retention is enabled
        $this->analyticsCleanupScheduler->bootstrap($this->getSettings());

        $this->registerProjectConfigEventHandlers();

        $this->registerGraphql();
        $this->registerSeomaticSeoElement();

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
                $siteUrlRules = $this->getSiteUrlRules();

                // Keep explicit QR/prefixed routes high priority, but append
                // root-level fallback routes after existing site routes.
                $event->rules = array_merge(
                    $siteUrlRules['priority'],
                    $event->rules,
                    $siteUrlRules['fallback']
                );
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
                    'heading' => $this->getSettings()->getFullName(),
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
                    'action' => function() {
                        $cleared = $this->localCache->clearAllCaches();
                        $this->logInfo('Cleared cache entries', ['count' => $cleared]);
                        $this->servdStaticCache->purgeAllUrls();
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
     * Register ShortLinks as a SEOmatic content source when SEOmatic is installed.
     */
    private function registerSeomaticSeoElement(): void
    {
        $seoElementsClass = 'nystudio107\seomatic\services\SeoElements';

        if (!class_exists($seoElementsClass)) {
            return;
        }

        $seomatic = $this->integration->getIntegration('seomatic');

        if ($seomatic === null || !$seomatic->isAvailable() || !$seomatic->isEnabled()) {
            return;
        }

        Event::on(
            $seoElementsClass,
            'registerSeoElementTypes',
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = SeoShortLink::class;
            }
        );
    }

    /**
     * Register GraphQL types, queries, and schema permissions.
     *
     * @return void
     * @since 5.21.0
     */
    private function registerGraphql(): void
    {
        $graphqlCacheSetting = null;

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            static function(RegisterGqlTypesEvent $event) {
                $event->types[] = GqlShortLinkType::class;
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            static function(RegisterGqlQueriesEvent $event) {
                foreach (ShortLinkQuery::getQueries() as $key => $value) {
                    $event->queries[$key] = $value;
                }
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS,
            static function(RegisterGqlSchemaComponentsEvent $event) {
                if (self::$plugin === null) {
                    return;
                }

                $pluginName = self::$plugin->getSettings()->getFullName();

                $event->queries[$pluginName]['shortlinkManager.all:read'] = [
                    'label' => Craft::t('shortlink-manager', 'Query {name} data', ['name' => $pluginName]),
                ];
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_BEFORE_EXECUTE_GQL_QUERY,
            static function(ExecuteGqlQueryEvent $event) use (&$graphqlCacheSetting) {
                if (!self::queryResolvesShortlink($event->query)) {
                    return;
                }

                $generalConfig = Craft::$app->getConfig()->getGeneral();
                $graphqlCacheSetting = $generalConfig->enableGraphqlCaching;
                $generalConfig->enableGraphqlCaching = false;
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_AFTER_EXECUTE_GQL_QUERY,
            static function(ExecuteGqlQueryEvent $event) use (&$graphqlCacheSetting) {
                if ($graphqlCacheSetting === null || !self::queryResolvesShortlink($event->query)) {
                    return;
                }

                Craft::$app->getConfig()->getGeneral()->enableGraphqlCaching = $graphqlCacheSetting;
                $graphqlCacheSetting = null;
            }
        );
    }

    /**
     * Return whether a GraphQL operation includes the side-effecting resolver.
     *
     * @param string $query
     * @return bool
     * @since 5.21.0
     */
    private static function queryResolvesShortlink(string $query): bool
    {
        return str_contains($query, 'shortlinkManagerResolveShortlink');
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
     * @since 5.11.0
     */
    public function getCpSections(Settings $settings, bool $includeLinks = true, bool $includeLogs = false): array
    {
        $sections = [];

        if ($includeLinks) {
            $sections[] = [
                'key' => 'shortlinks',
                'label' => Craft::t('shortlink-manager', 'Links'),
                'url' => 'shortlink-manager',
                'permissionsAll' => ['shortLinkManager:manageLinks'],
            ];
        }

        $sections[] = [
            'key' => 'taxonomy',
            'label' => Craft::t('shortlink-manager', 'Folders & Tags'),
            'url' => 'shortlink-manager/taxonomy',
            'permissionsAll' => ['shortLinkManager:manageTaxonomy'],
        ];

        $sections[] = [
            'key' => 'analytics',
            'label' => Craft::t('shortlink-manager', 'Analytics'),
            'url' => 'shortlink-manager/analytics',
            'permissionsAll' => ['shortLinkManager:viewAnalytics'],
            'when' => $settings->enableAnalytics,
        ];

        $sections[] = [
            'key' => 'import-export',
            'label' => Craft::t('shortlink-manager', 'Import/Export'),
            'url' => 'shortlink-manager/import-export',
            'permissionsAll' => ['shortLinkManager:manageImportExport'],
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
            'key' => 'setup',
            'label' => Craft::t('shortlink-manager', 'Setup'),
            'url' => 'shortlink-manager/setup',
            'permissionsAll' => ['shortLinkManager:manageSettings'],
        ];

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
        $editableSiteIds = Craft::$app->getIsMultiSite()
            ? Craft::$app->getSites()->getEditableSiteIds()
            : null;

        // Return only sites that are enabled for this plugin AND editable by the current user
        return array_filter(Craft::$app->getSites()->getAllSites(), function($site) use ($enabledSiteIds, $editableSiteIds) {
            if (!in_array($site->id, $enabledSiteIds)) {
                return false;
            }
            if ($editableSiteIds !== null && !in_array($site->id, $editableSiteIds)) {
                return false;
            }
            return true;
        });
    }

    /**
     * @inheritdoc
     */
    public function setSettings(array|Model $settings): void
    {
        // No-op: settings come from loadFromDatabase() in createSettingsModel()
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
            PluginHelper::applyConfigOverridesToSettings($settings, 'shortlink-manager');
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
     * @inheritdoc
     */
    public function getReadOnlySettingsResponse(): mixed
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
            'shortlink-manager/analytics/export' => 'shortlink-manager/analytics/export',

            // Import/Export routes
            'shortlink-manager/import-export' => 'shortlink-manager/import-export/index',
            'shortlink-manager/import-export/upload' => 'shortlink-manager/import-export/upload',
            'shortlink-manager/import-export/map' => 'shortlink-manager/import-export/map',
            'shortlink-manager/import-export/preview' => 'shortlink-manager/import-export/preview',
            'shortlink-manager/import-export/import' => 'shortlink-manager/import-export/import',
            'shortlink-manager/import-export/export' => 'shortlink-manager/import-export/export',
            'shortlink-manager/import-export/clear-logs' => 'shortlink-manager/import-export/clear-logs',

            // Taxonomy routes
            'shortlink-manager/taxonomy' => 'shortlink-manager/taxonomy/index',
            'shortlink-manager/taxonomy/folders/new' => 'shortlink-manager/taxonomy/new-folder',
            'shortlink-manager/taxonomy/folders/<folderId:\d+>' => 'shortlink-manager/taxonomy/edit-folder',
            'shortlink-manager/taxonomy/tags/new' => 'shortlink-manager/taxonomy/new-tag',
            'shortlink-manager/taxonomy/tags/<tagId:\d+>' => 'shortlink-manager/taxonomy/edit-tag',
            'shortlink-manager/taxonomy/save-folder' => 'shortlink-manager/taxonomy/save-folder',
            'shortlink-manager/taxonomy/save-tag' => 'shortlink-manager/taxonomy/save-tag',
            'shortlink-manager/taxonomy/delete-folder' => 'shortlink-manager/taxonomy/delete-folder',
            'shortlink-manager/taxonomy/bulk-delete-folders' => 'shortlink-manager/taxonomy/bulk-delete-folders',
            'shortlink-manager/taxonomy/delete-tag' => 'shortlink-manager/taxonomy/delete-tag',
            'shortlink-manager/taxonomy/bulk-delete-tags' => 'shortlink-manager/taxonomy/bulk-delete-tags',

            // Setup route
            'shortlink-manager/setup' => 'shortlink-manager/settings/setup',

            // Settings routes
            'shortlink-manager/settings' => 'shortlink-manager/settings/index',
            'shortlink-manager/settings/general' => 'shortlink-manager/settings/general',
            'shortlink-manager/settings/behavior' => 'shortlink-manager/settings/behavior',
            'shortlink-manager/settings/qr-code' => 'shortlink-manager/settings/qr-code',
            'shortlink-manager/settings/analytics' => 'shortlink-manager/settings/analytics',
            'shortlink-manager/settings/integrations' => 'shortlink-manager/settings/integrations',
            'shortlink-manager/settings/interface' => 'shortlink-manager/settings/interface',
            'shortlink-manager/settings/cache' => 'shortlink-manager/settings/cache',
            'shortlink-manager/settings/field-layout' => 'shortlink-manager/settings/field-layout',
            'shortlink-manager/settings/save-field-layout' => 'shortlink-manager/settings/save-field-layout',
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
        $usePrefix = (bool) ($settings->usePrefix ?? true);
        $slugPrefix = trim((string) ($settings->slugPrefix ?? 's'), '/');
        $qrPrefix = trim((string) ($settings->qrPrefix ?? 'qr'), '/');
        $slugPrefix = $slugPrefix !== '' ? $slugPrefix : 's';
        $qrPrefix = $qrPrefix !== '' ? $qrPrefix : 'qr';
        $siteIdentifiers = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            foreach ([$site->handle, (string)$site->id, $site->uid] as $identifier) {
                $siteIdentifiers[] = preg_quote($identifier, '/');
            }
        }
        $siteIdentifiers = array_values(array_unique($siteIdentifiers));
        $siteIdentifierPattern = $siteIdentifiers !== []
            ? implode('|', $siteIdentifiers)
            : '(?!)';

        $priorityRules = [
            'shortlink-manager/redirect/go/<code:[a-zA-Z0-9\-\_]+>' => 'shortlink-manager/redirect/go',
            '<siteHandle:' . $siteIdentifierPattern . '>/shortlink-manager/redirect/go/<code:[a-zA-Z0-9\-\_]+>' => 'shortlink-manager/redirect/go',
            // QR Code routes - supports both standalone ('qr') and nested ('s/qr') patterns
            $qrPrefix . '/<code:[a-zA-Z0-9\-\_]+>' => 'shortlink-manager/qr-code/generate',
            $qrPrefix . '/<code:[a-zA-Z0-9\-\_]+>/view' => 'shortlink-manager/qr-code/display',
            '<siteHandle:' . $siteIdentifierPattern . '>/' . $qrPrefix . '/<code:[a-zA-Z0-9\-\_]+>' => 'shortlink-manager/qr-code/generate',
            '<siteHandle:' . $siteIdentifierPattern . '>/' . $qrPrefix . '/<code:[a-zA-Z0-9\-\_]+>/view' => 'shortlink-manager/qr-code/display',
        ];

        // Always keep prefixed routes for backward compatibility.
        $priorityRules[$slugPrefix . '/<code:[a-zA-Z0-9\-\_]+>'] = 'shortlink-manager/redirect/index';
        $priorityRules['<siteHandle:' . $siteIdentifierPattern . '>/' . $slugPrefix . '/<code:[a-zA-Z0-9\-\_]+>'] = 'shortlink-manager/redirect/index';

        // Root routes are enabled when usePrefix is disabled.
        $fallbackRules = [];
        if (!$usePrefix) {
            $rootCodePattern = $this->getRootCodeRoutePattern($settings);
            $fallbackRules['<code:' . $rootCodePattern . '>'] = 'shortlink-manager/redirect/index';
            $fallbackRules['<siteHandle:' . $siteIdentifierPattern . '>/<code:' . $rootCodePattern . '>'] = 'shortlink-manager/redirect/index';
        }

        return [
            'priority' => $priorityRules,
            'fallback' => $fallbackRules,
        ];
    }

    /**
     * Build the root shortlink code pattern, excluding reserved codes.
     */
    private function getRootCodeRoutePattern(Settings $settings): string
    {
        $pattern = '[a-zA-Z0-9\-\_]+';
        $reservedCodes = array_values(array_filter(array_map(
            static fn(string $code): string => trim($code),
            $settings->reservedCodes ?? []
        )));

        if ($reservedCodes === []) {
            return $pattern;
        }

        $reservedPattern = implode('|', array_map(
            static fn(string $code): string => preg_quote($code, '/'),
            $reservedCodes
        ));

        return '(?!(?i:' . $reservedPattern . ')$)' . $pattern;
    }

    /**
     * Get plugin permissions
     */
    private function getPluginPermissions(): array
    {
        $settings = $this->getSettings();
        $plural = $settings->getPluralLowerDisplayName();

        return [
            // Shortlinks
            'shortLinkManager:manageLinks' => [
                'label' => Craft::t('shortlink-manager', 'Manage {plural}', ['plural' => $plural]),
                'nested' => [
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
            // Taxonomy (Folders & Tags)
            'shortLinkManager:manageTaxonomy' => [
                'label' => Craft::t('shortlink-manager', 'Manage folders & tags'),
                'nested' => [
                    'shortLinkManager:createTaxonomy' => [
                        'label' => Craft::t('shortlink-manager', 'Create folders & tags'),
                    ],
                    'shortLinkManager:editTaxonomy' => [
                        'label' => Craft::t('shortlink-manager', 'Edit folders & tags'),
                    ],
                    'shortLinkManager:deleteTaxonomy' => [
                        'label' => Craft::t('shortlink-manager', 'Delete folders & tags'),
                    ],
                ],
            ],
            // Import/Export
            'shortLinkManager:manageImportExport' => [
                'label' => Craft::t('shortlink-manager', 'Manage import/export'),
                'nested' => [
                    'shortLinkManager:importLinks' => [
                        'label' => Craft::t('shortlink-manager', 'Import links'),
                    ],
                    'shortLinkManager:exportLinks' => [
                        'label' => Craft::t('shortlink-manager', 'Export links'),
                    ],
                    'shortLinkManager:clearImportHistory' => [
                        'label' => Craft::t('shortlink-manager', 'Clear import history'),
                    ],
                ],
            ],
            // Analytics
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
            // Cache
            'shortLinkManager:clearCache' => [
                'label' => Craft::t('shortlink-manager', 'Clear cache'),
            ],
            // Logs
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
            // Settings
            'shortLinkManager:manageSettings' => [
                'label' => Craft::t('shortlink-manager', 'Manage settings'),
            ],
        ];
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
                if ($event->element instanceof ShortLink) {
                    $this->servdStaticCache->purgeElement($event->element);
                    return;
                }

                if (!$event->isNew) {
                    $this->shortLinks->onSaveElement($event->element);
                }
            }
        );

        // Purge public shortlink URLs before delete while the element data is still available.
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(DeleteElementEvent $event) {
                if ($event->element instanceof ShortLink) {
                    $this->servdStaticCache->purgeElement($event->element);
                }
            }
        );

        // Listen for element deletions to delete associated shortlinks
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function(ElementEvent $event) {
                if ($event->element instanceof ShortLink) {
                    return;
                }

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

    /**
     * Register project config event handlers.
     *
     * @return void
     */
    private function registerProjectConfigEventHandlers(): void
    {
        Craft::$app->getProjectConfig()
            ->onAdd('shortlink-manager.fieldLayouts.{uid}', [$this, 'handleChangedFieldLayout'])
            ->onUpdate('shortlink-manager.fieldLayouts.{uid}', [$this, 'handleChangedFieldLayout'])
            ->onRemove('shortlink-manager.fieldLayouts.{uid}', [$this, 'handleDeletedFieldLayout']);
    }

    /**
     * Handle field layout changes from project config.
     *
     * @param \craft\events\ConfigEvent $event
     * @return void
     * @since 5.21.0
     */
    public function handleChangedFieldLayout(\craft\events\ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $fieldLayout = \craft\models\FieldLayout::createFromConfig($data);
        $fieldLayout->uid = $uid;
        $fieldLayout->type = \lindemannrock\shortlinkmanager\elements\ShortLink::class;

        Craft::$app->getFields()->saveLayout($fieldLayout, false);

        $this->logInfo('Applied ShortLink Manager field layout from project config', ['uid' => $uid]);
    }

    /**
     * Handle field layout deletion from project config.
     *
     * @param \craft\events\ConfigEvent $event
     * @return void
     * @since 5.21.0
     */
    public function handleDeletedFieldLayout(\craft\events\ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $fieldLayout = Craft::$app->getFields()->getLayoutByUid($uid);

        if ($fieldLayout) {
            Craft::$app->getFields()->deleteLayoutById($fieldLayout->id);
        }
    }
}
