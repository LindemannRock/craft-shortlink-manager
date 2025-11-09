<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Query;
use craft\helpers\Db;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * ShortLink Manager Settings Model
 */
class Settings extends Model
{
    use LoggingTrait;

    /**
     * @event Event The event that is triggered after settings are saved
     */
    const EVENT_AFTER_SAVE_SETTINGS = 'afterSaveSettings';

    // Plugin settings
    public string $pluginName = 'ShortLink Manager';

    // Site settings
    public array $enabledSites = [];

    // URL settings
    public string $slugPrefix = 's';
    public int $codeLength = 8;
    public string $customDomain = '';
    public array $reservedCodes = ['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings'];

    // QR Code settings
    public bool $enableQrCodes = true;
    public int $defaultQrSize = 256;
    public string $defaultQrColor = '#000000';
    public string $defaultQrBgColor = '#FFFFFF';
    public string $defaultQrFormat = 'png';
    public bool $enableQrCodeCache = true;
    public int $qrCodeCacheDuration = 86400; // 24 hours
    public string $defaultQrErrorCorrection = 'M';
    public int $defaultQrMargin = 4;
    public string $qrModuleStyle = 'square';
    public string $qrEyeStyle = 'square';
    public ?string $qrEyeColor = null;
    public bool $enableQrLogo = false;
    public ?string $qrLogoVolumeUid = null;
    public ?int $defaultQrLogoId = null;
    public int $qrLogoSize = 20;
    public bool $enableQrDownload = true;
    public string $qrDownloadFilename = '{code}-qr-{size}';

    // Redirect settings
    public int $defaultHttpCode = 301;
    public string $notFoundRedirectUrl = '/';
    public ?string $redirectTemplate = null;
    public string $expiredMessage = 'This link has expired';
    public ?string $expiredTemplate = null;

    // QR Code settings
    public string $qrPrefix = '';
    public ?string $qrTemplate = null;

    // Analytics settings
    public bool $enableAnalytics = true;
    public int $analyticsRetention = 90; // days, 0 = forever
    public bool $anonymizeIpAddress = false; // Subnet masking (192.168.1.123 → 192.168.1.0)
    public ?string $ipHashSalt = null; // Secret salt for IP hashing (from .env)
    public bool $enableGeoDetection = false;
    public bool $cacheDeviceDetection = true;
    public int $deviceDetectionCacheDuration = 3600; // 1 hour

    // Logging
    public string $logLevel = 'error';

    // Display
    public int $itemsPerPage = 50;

    // Integrations
    public ?array $enabledIntegrations = ['redirect-manager'];
    public ?array $redirectManagerEvents = ['slug-change', 'expire', 'delete'];
    public array $seomaticTrackingEvents = ['redirect', 'qr_scan'];
    public string $seomaticEventPrefix = 'shortlink_manager';

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'customDomain',
                    'notFoundRedirectUrl',
                    'expiredMessage',
                    'ipHashSalt',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('shortlink-manager');

        // Fallback to .env if ipHashSalt not set by config file
        if ($this->ipHashSalt === null) {
            $this->ipHashSalt = App::env('SHORTLINK_MANAGER_IP_SALT');
        }
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['pluginName', 'slugPrefix', 'qrPrefix'], 'required'],
            [['pluginName'], 'string', 'max' => 255],
            [['slugPrefix', 'qrPrefix'], 'string', 'max' => 50],
            [['slugPrefix'], 'match', 'pattern' => '/^[a-zA-Z0-9\-\_]+$/', 'message' => Craft::t('shortlink-manager', 'Only letters, numbers, hyphens, and underscores are allowed.')],
            [['slugPrefix'], 'validateSlugPrefix'],
            [['qrPrefix'], 'match', 'pattern' => '/^[a-zA-Z0-9\-\_\/]+$/', 'message' => Craft::t('shortlink-manager', 'Only letters, numbers, hyphens, underscores, and slashes are allowed.')],
            [['qrPrefix'], 'validateQrPrefix'],
            [['enableQrCodes', 'enableAnalytics', 'enableGeoDetection', 'anonymizeIpAddress', 'enableQrLogo', 'enableQrDownload'], 'boolean'],
            [['enabledSites', 'enabledIntegrations', 'redirectManagerEvents', 'seomaticTrackingEvents'], 'safe'],
            [['enabledSites'], 'each', 'rule' => ['integer']],
            [['seomaticTrackingEvents'], 'each', 'rule' => ['string']],
            [['seomaticEventPrefix'], 'string', 'max' => 50],
            [['seomaticEventPrefix'], 'match', 'pattern' => '/^[a-z0-9\_]+$/', 'message' => Craft::t('shortlink-manager', 'Only lowercase letters, numbers, and underscores are allowed.')],
            [['redirectTemplate', 'qrTemplate'], 'string', 'max' => 500],
            [['codeLength', 'defaultQrSize', 'qrCodeCacheDuration', 'defaultQrMargin', 'qrLogoSize', 'defaultHttpCode', 'analyticsRetention', 'itemsPerPage'], 'integer'],
            [['itemsPerPage'], 'integer', 'min' => 10, 'max' => 500],
            [['codeLength'], 'integer', 'min' => 4, 'max' => 32],
            [['defaultQrSize'], 'integer', 'min' => 100, 'max' => 1000],
            [['defaultQrMargin'], 'integer', 'min' => 0, 'max' => 10],
            [['qrLogoSize'], 'integer', 'min' => 10, 'max' => 30],
            [['analyticsRetention'], 'integer', 'min' => 0, 'max' => 3650],
            [['defaultHttpCode'], 'in', 'range' => [301, 302, 307, 308]],
            [['defaultQrColor', 'defaultQrBgColor', 'qrEyeColor'], 'string'],
            [['defaultQrColor', 'defaultQrBgColor'], 'match', 'pattern' => '/^#[0-9A-F]{6}$/i'],
            [['qrEyeColor'], 'match', 'pattern' => '/^#[0-9A-F]{6}$/i', 'skipOnEmpty' => true],
            [['defaultQrFormat'], 'in', 'range' => ['png', 'svg']],
            [['defaultQrErrorCorrection'], 'in', 'range' => ['L', 'M', 'Q', 'H']],
            [['qrModuleStyle'], 'in', 'range' => ['square', 'rounded', 'dots']],
            [['qrEyeStyle'], 'in', 'range' => ['square', 'rounded', 'leaf']],
            [['qrDownloadFilename'], 'string'],
            [['qrLogoVolumeUid'], 'string'],
            [['defaultQrLogoId'], 'integer'],
            [['defaultQrLogoId'], 'required', 'when' => function($model) {
                return $model->enableQrLogo;
            }, 'message' => Craft::t('shortlink-manager', 'Default logo is required when logo overlay is enabled.')],
            [['customDomain', 'notFoundRedirectUrl', 'expiredMessage'], 'string'],
            [['ipHashSalt'], 'string', 'min' => 32, 'message' => Craft::t('shortlink-manager', 'Salt must be at least 32 characters'), 'skipOnEmpty' => true],
            [['reservedCodes'], 'each', 'rule' => ['string']],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            [['logLevel'], 'validateLogLevel'],
        ];
    }

    /**
     * Validate log level - debug requires devMode
     */
    public function validateLogLevel($attribute, $params, $validator)
    {
        $logLevel = $this->$attribute;

        // Reset session warning when devMode is true - allows warning to show again if devMode changes
        if (Craft::$app->getConfig()->getGeneral()->devMode && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            Craft::$app->getSession()->remove('slm_debug_config_warning');
        }

        // Debug level is only allowed when devMode is enabled
        if ($logLevel === 'debug' && !Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->$attribute = 'info';

            if ($this->isOverriddenByConfig('logLevel')) {
                if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
                    if (Craft::$app->getSession()->get('slm_debug_config_warning') === null) {
                        $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                            'configFile' => 'config/shortlink-manager.php'
                        ]);
                        Craft::$app->getSession()->set('slm_debug_config_warning', true);
                    }
                } else {
                    $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                        'configFile' => 'config/shortlink-manager.php'
                    ]);
                }
            } else {
                $this->logWarning('Log level automatically changed from "debug" to "info" because devMode is disabled');
                $this->saveToDatabase();
            }
        }
    }

    /**
     * Validate slug prefix to prevent conflicts
     */
    public function validateSlugPrefix($attribute, $params, $validator)
    {
        $slugPrefix = $this->$attribute;

        if (empty($slugPrefix)) {
            return;
        }

        $conflicts = [];

        // Check against Smart Links if installed
        if (Craft::$app->plugins->isPluginInstalled('smart-links')) {
            try {
                $smartLinksPlugin = Craft::$app->plugins->getPlugin('smart-links');
                if ($smartLinksPlugin) {
                    $smartLinksSettings = $smartLinksPlugin->getSettings();
                    $smartLinksPluginName = $smartLinksSettings->pluginName ?? 'Smart Links';

                    // Check against Smart Links slugPrefix
                    if ($slugPrefix === ($smartLinksSettings->slugPrefix ?? 'go')) {
                        $conflicts[] = "{$smartLinksPluginName} slug prefix ('{$smartLinksSettings->slugPrefix}')";
                    }

                    // Check against Smart Links qrPrefix
                    if ($slugPrefix === ($smartLinksSettings->qrPrefix ?? 'qr')) {
                        $conflicts[] = "{$smartLinksPluginName} QR prefix ('{$smartLinksSettings->qrPrefix}')";
                    }
                }
            } catch (\Exception $e) {
                // Silently continue if we can't check smart-links
            }
        }

        if (!empty($conflicts)) {
            $suggestions = ['sl', 'link', 'l', 's'];
            $this->addError($attribute, Craft::t('shortlink-manager', 'Slug prefix "{prefix}" conflicts with: {conflicts}. Suggestions: {suggestions}', [
                'prefix' => $slugPrefix,
                'conflicts' => implode(', ', $conflicts),
                'suggestions' => implode(', ', $suggestions)
            ]));
        }
    }

    /**
     * Validate QR prefix to prevent conflicts
     */
    public function validateQrPrefix($attribute, $params, $validator)
    {
        $qrPrefix = $this->$attribute;

        if (empty($qrPrefix)) {
            return;
        }

        $conflicts = [];

        // Parse the prefix (supports both "qr" and "s/qr" patterns)
        $segments = explode('/', $qrPrefix);
        $isNested = count($segments) > 1;

        // Check against own slugPrefix
        if (!$isNested && $qrPrefix === $this->slugPrefix) {
            $this->addError($attribute, Craft::t('shortlink-manager', 'QR prefix cannot be the same as your URL segment. Try: sqr, q, or {segment}/qr', [
                'segment' => $this->slugPrefix
            ]));
            return;
        }

        // Check if nested pattern conflicts with own slugPrefix
        if ($isNested) {
            $baseSegment = $segments[0];
            if ($baseSegment !== $this->slugPrefix) {
                $this->addError($attribute, Craft::t('shortlink-manager', 'Nested QR prefix must start with your URL segment "{segment}". Use: {segment}/{qr} or use standalone like "qr"', [
                    'segment' => $this->slugPrefix,
                    'qr' => $segments[1] ?? 'qr'
                ]));
                return;
            }
        }

        // Check against Smart Links if installed
        if (Craft::$app->plugins->isPluginInstalled('smart-links')) {
            try {
                $smartLinksPlugin = Craft::$app->plugins->getPlugin('smart-links');
                if ($smartLinksPlugin) {
                    $smartLinksSettings = $smartLinksPlugin->getSettings();
                    $smartLinksPluginName = $smartLinksSettings->pluginName ?? 'Smart Links';

                    // Check against Smart Links slugPrefix
                    if (!$isNested && $qrPrefix === ($smartLinksSettings->slugPrefix ?? 'go')) {
                        $conflicts[] = "{$smartLinksPluginName} link prefix ('{$smartLinksSettings->slugPrefix}')";
                    }

                    // Check against Smart Links qrPrefix
                    if (!$isNested && $qrPrefix === ($smartLinksSettings->qrPrefix ?? 'qr')) {
                        $conflicts[] = "{$smartLinksPluginName} QR prefix ('{$smartLinksSettings->qrPrefix}')";
                    }
                }
            } catch (\Exception $e) {
                // Silently continue if we can't check smart-links
            }
        }

        if (!empty($conflicts)) {
            $suggestions = $this->suggestQrPrefix($qrPrefix);
            $this->addError($attribute, Craft::t('shortlink-manager', 'QR prefix "{prefix}" conflicts with: {conflicts}. Suggestions: {suggestions}', [
                'prefix' => $qrPrefix,
                'conflicts' => implode(', ', $conflicts),
                'suggestions' => implode(', ', $suggestions)
            ]));
        }
    }

    /**
     * Suggest alternative QR prefixes
     */
    private function suggestQrPrefix(string $current): array
    {
        $suggestions = ['sqr', 'q', 's-qr', 's/qr'];

        return $suggestions;
    }

    /**
     * Get smart default for qrPrefix that avoids conflicts
     */
    private function getSmartQrPrefixDefault(): string
    {
        $preferredDefaults = ['qr', 'sqr', 'q', 's-qr'];
        $conflictingPrefixes = [];

        // Check against Smart Links if installed
        $smartLinksInstalled = Craft::$app->plugins->isPluginInstalled('smart-links');
        $this->logInfo('Smart Links check', ['installed' => $smartLinksInstalled]);

        if ($smartLinksInstalled) {
            try {
                $smartLinksPlugin = Craft::$app->plugins->getPlugin('smart-links');
                $this->logInfo('Smart Links plugin', ['found' => $smartLinksPlugin ? 'yes' : 'no']);

                if ($smartLinksPlugin) {
                    $smartLinksSettings = $smartLinksPlugin->getSettings();
                    $conflictingPrefixes[] = $smartLinksSettings->slugPrefix ?? 'go';
                    $conflictingPrefixes[] = $smartLinksSettings->qrPrefix ?? 'qr';

                    $this->logInfo('Checking Smart Links conflicts', [
                        'slugPrefix' => $smartLinksSettings->slugPrefix ?? 'go',
                        'qrPrefix' => $smartLinksSettings->qrPrefix ?? 'qr',
                        'conflictingPrefixes' => $conflictingPrefixes
                    ]);
                }
            } catch (\Exception $e) {
                $this->logWarning('Could not check smart-links', ['error' => $e->getMessage()]);
            }
        } else {
            $this->logInfo('Smart Links not installed, no conflicts to check');
        }

        // Check against own slugPrefix
        $conflictingPrefixes[] = $this->slugPrefix;

        // Find first non-conflicting prefix
        foreach ($preferredDefaults as $prefix) {
            if (!in_array($prefix, $conflictingPrefixes)) {
                $this->logInfo('Selected QR prefix', [
                    'selected' => $prefix,
                    'conflictingPrefixes' => $conflictingPrefixes
                ]);
                return $prefix;
            }
        }

        // Fallback to nested pattern
        $fallback = $this->slugPrefix . '/qr';
        $this->logInfo('Using fallback nested QR prefix', ['prefix' => $fallback]);
        return $fallback;
    }

    /**
     * Load settings from database
     *
     * @param Settings|null $settings Optional existing settings instance
     * @return self
     */
    public static function loadFromDatabase(?Settings $settings = null): self
    {
        if ($settings === null) {
            $settings = new self();
        }

        // Load from database
        try {
            $row = (new Query())
                ->from('{{%shortlinkmanager_settings}}')
                ->where(['id' => 1])
                ->one();
        } catch (\Exception $e) {
            $settings->logError('Failed to load settings from database', ['error' => $e->getMessage()]);
            // Return default settings if database query fails
            return $settings;
        }

        if (!$row) {
            $settings->logWarning('No settings found in database');
            return $settings;
        }

        // Remove system fields that aren't attributes
        unset($row['id'], $row['dateCreated'], $row['dateUpdated'], $row['uid']);

        // Only set attributes that actually exist in the row to handle missing columns gracefully
        $safeRow = [];
        foreach ($row as $key => $value) {
            if (property_exists($settings, $key)) {
                $safeRow[$key] = $value;
            }
        }

        if (!empty($safeRow)) {
            // Convert numeric boolean values to actual booleans
            $booleanFields = [
                'enableQrCodes',
                'enableQrLogo',
                'enableQrDownload',
                'enableAnalytics',
                'enableGeoDetection',
                'anonymizeIpAddress'
            ];

            foreach ($booleanFields as $field) {
                if (isset($safeRow[$field])) {
                    $safeRow[$field] = (bool) $safeRow[$field];
                }
            }

            // Convert numeric values to integers
            $integerFields = [
                'codeLength',
                'defaultQrSize',
                'qrCodeCacheDuration',
                'defaultQrMargin',
                'qrLogoSize',
                'defaultHttpCode',
                'analyticsRetention',
                'defaultQrLogoId',
                'itemsPerPage'
            ];

            foreach ($integerFields as $field) {
                if (isset($safeRow[$field])) {
                    $safeRow[$field] = (int) $safeRow[$field];
                }
            }

            // Handle array fields (JSON serialization)
            if (isset($safeRow['enabledSites'])) {
                $safeRow['enabledSites'] = !empty($safeRow['enabledSites']) ? json_decode($safeRow['enabledSites'], true) : [];
            }

            if (isset($safeRow['reservedCodes'])) {
                $safeRow['reservedCodes'] = !empty($safeRow['reservedCodes']) ? json_decode($safeRow['reservedCodes'], true) : [];
            }

            if (isset($safeRow['enabledIntegrations'])) {
                $safeRow['enabledIntegrations'] = !empty($safeRow['enabledIntegrations']) ? json_decode($safeRow['enabledIntegrations'], true) : [];
            }

            if (isset($safeRow['redirectManagerEvents'])) {
                $safeRow['redirectManagerEvents'] = !empty($safeRow['redirectManagerEvents']) ? json_decode($safeRow['redirectManagerEvents'], true) : [];
            }

            if (isset($safeRow['seomaticTrackingEvents'])) {
                $safeRow['seomaticTrackingEvents'] = !empty($safeRow['seomaticTrackingEvents']) ? json_decode($safeRow['seomaticTrackingEvents'], true) : [];
            }

            // Set attributes from database
            $settings->setAttributes($safeRow, false);
        }

        // Set default for expiredMessage if empty
        if (empty($settings->expiredMessage)) {
            $settings->expiredMessage = 'This link has expired';
        }

        return $settings;
    }

    /**
     * Save settings to database
     *
     * @return bool
     */
    public function saveToDatabase(): bool
    {
        if (!$this->validate()) {
            $this->logError('Settings validation failed', ['errors' => $this->getErrors()]);
            return false;
        }

        $db = Craft::$app->getDb();
        $attributes = $this->getAttributes();

        // Exclude ipHashSalt - it's config-only, never saved to database
        unset($attributes['ipHashSalt']);

        // Debug: Log what we're trying to save
        $this->logDebug('Attempting to save settings', ['attributes' => $attributes]);

        // Handle array serialization
        if (isset($attributes['enabledSites'])) {
            $attributes['enabledSites'] = json_encode($attributes['enabledSites']);
        }
        if (isset($attributes['reservedCodes'])) {
            $attributes['reservedCodes'] = json_encode($attributes['reservedCodes']);
        }
        if (isset($attributes['enabledIntegrations'])) {
            $attributes['enabledIntegrations'] = json_encode($attributes['enabledIntegrations']);
        }
        if (isset($attributes['redirectManagerEvents'])) {
            $attributes['redirectManagerEvents'] = json_encode($attributes['redirectManagerEvents']);
        }
        if (isset($attributes['seomaticTrackingEvents'])) {
            $attributes['seomaticTrackingEvents'] = json_encode($attributes['seomaticTrackingEvents']);
        }

        // Add/update timestamps
        $now = Db::prepareDateForDb(new \DateTime());
        $attributes['dateUpdated'] = $now;

        // Update existing settings (we know there's always one row from migration)
        try {
            $result = $db->createCommand()
                ->update('{{%shortlinkmanager_settings}}', $attributes, ['id' => 1])
                ->execute();

            // Debug: Log the result
            $this->logDebug('Database update result', ['result' => $result]);

            if ($result !== false) {
                // Trigger event after successful save
                $this->trigger(self::EVENT_AFTER_SAVE_SETTINGS);
                $this->logInfo('Settings saved successfully to database');
                return true;
            }

            $this->logError('Database update returned false');
            return false;
        } catch (\Exception $e) {
            $this->logError('Failed to save ShortLink Manager settings', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if a setting is overridden by config file
     * Supports dot notation for nested settings like: reservedCodes.0
     *
     * @param string $attribute The setting attribute name or dot-notation path
     * @return bool
     */
    public function isOverriddenByConfig(string $attribute): bool
    {
        $configPath = \Craft::$app->getPath()->getConfigPath() . '/shortlink-manager.php';

        if (!file_exists($configPath)) {
            return false;
        }

        // Load the raw config file instead of using Craft's config which merges with database
        $rawConfig = require $configPath;

        // Handle dot notation for nested config
        if (str_contains($attribute, '.')) {
            $parts = explode('.', $attribute);
            $current = $rawConfig;

            foreach ($parts as $part) {
                if (!is_array($current) || !array_key_exists($part, $current)) {
                    return false;
                }
                $current = $current[$part];
            }

            return true;
        }

        // Check for the attribute in the config
        // Use array_key_exists instead of isset to detect null values
        if (array_key_exists($attribute, $rawConfig)) {
            return true;
        }

        // Check environment-specific configs
        $env = \Craft::$app->getConfig()->env;
        if ($env && is_array($rawConfig[$env] ?? null) && array_key_exists($attribute, $rawConfig[$env])) {
            return true;
        }

        // Check wildcard config
        if (is_array($rawConfig['*'] ?? null) && array_key_exists($attribute, $rawConfig['*'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if a site is enabled for ShortLink Manager
     *
     * @param int $siteId
     * @return bool
     */
    public function isSiteEnabled(int $siteId): bool
    {
        // If no sites are specifically enabled, assume all sites are enabled (backwards compatibility)
        if (empty($this->enabledSites)) {
            return true;
        }

        return in_array($siteId, $this->enabledSites);
    }

    /**
     * Get enabled site IDs, defaulting to all sites if none specified
     *
     * @return array
     */
    public function getEnabledSiteIds(): array
    {
        if (empty($this->enabledSites)) {
            // Return all site IDs if none specifically enabled
            return array_map(function($site) {
                return $site->id;
            }, Craft::$app->getSites()->getAllSites());
        }

        return $this->enabledSites;
    }

    /**
     * Get attribute labels
     *
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'pluginName' => Craft::t('shortlink-manager', 'Plugin Name'),
            'enabledSites' => Craft::t('shortlink-manager', 'Enabled Sites'),
            'slugPrefix' => Craft::t('shortlink-manager', 'Slug Prefix'),
            'codeLength' => Craft::t('shortlink-manager', 'Code Length'),
            'customDomain' => Craft::t('shortlink-manager', 'Custom Domain'),
            'reservedCodes' => Craft::t('shortlink-manager', 'Reserved Codes'),
            'enableQrCodes' => Craft::t('shortlink-manager', 'Enable QR Codes'),
            'defaultQrSize' => Craft::t('shortlink-manager', 'Default QR Code Size'),
            'defaultQrColor' => Craft::t('shortlink-manager', 'Default QR Code Color'),
            'defaultQrBgColor' => Craft::t('shortlink-manager', 'Default QR Background Color'),
            'defaultQrFormat' => Craft::t('shortlink-manager', 'Default QR Code Format'),
            'qrCodeCacheDuration' => Craft::t('shortlink-manager', 'QR Code Cache Duration (seconds)'),
            'defaultQrMargin' => Craft::t('shortlink-manager', 'QR Code Margin'),
            'qrModuleStyle' => Craft::t('shortlink-manager', 'Module Style'),
            'qrEyeStyle' => Craft::t('shortlink-manager', 'Eye Style'),
            'qrEyeColor' => Craft::t('shortlink-manager', 'Eye Color'),
            'enableQrLogo' => Craft::t('shortlink-manager', 'Enable QR Code Logo'),
            'defaultQrLogoId' => Craft::t('shortlink-manager', 'Default Logo'),
            'qrLogoSize' => Craft::t('shortlink-manager', 'Logo Size (%)'),
            'enableQrDownload' => Craft::t('shortlink-manager', 'Enable QR Code Downloads'),
            'defaultHttpCode' => Craft::t('shortlink-manager', 'Default HTTP Code'),
            'expiredMessage' => Craft::t('shortlink-manager', 'Expired Message'),
            'notFoundRedirectUrl' => Craft::t('shortlink-manager', '404 Redirect URL'),
            'enableAnalytics' => Craft::t('shortlink-manager', 'Enable Analytics'),
            'analyticsRetention' => Craft::t('shortlink-manager', 'Analytics Retention (days)'),
            'anonymizeIp' => Craft::t('shortlink-manager', 'Anonymize IP Addresses'),
            'enableGeoDetection' => Craft::t('shortlink-manager', 'Enable Geographic Detection'),
            'logLevel' => Craft::t('shortlink-manager', 'Log Level'),
        ];
    }
}
