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
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\traits\SettingsConfigTrait;
use lindemannrock\base\traits\SettingsDisplayNameTrait;
use lindemannrock\base\traits\SettingsPersistenceTrait;
use lindemannrock\base\validators\RoutePrefixValidator;
use lindemannrock\base\validators\TemplatePathValidator;
use lindemannrock\base\validators\UrlOrPathValidator;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * ShortLink Manager Settings Model
 *
 * @since 5.0.0
 */
class Settings extends Model
{
    use LoggingTrait;
    use SettingsConfigTrait;
    use SettingsDisplayNameTrait;
    use SettingsPersistenceTrait;

    /**
     * @event Event The event that is triggered after settings are saved
     */
    public const EVENT_AFTER_SAVE_SETTINGS = 'afterSaveSettings';

    /**
     * @var string Plugin display name shown in the control panel
     */
    public string $pluginName = 'ShortLink Manager';

    /**
     * @var array Site IDs where short links are enabled
     */
    public array $enabledSites = [];

    /**
     * @var string URL prefix for generated short links (e.g., /s/abc123)
     */
    public string $slugPrefix = 's';

    /**
     * @var string|null Optional absolute base URL for generated short links and QR URLs
     * (e.g., https://short.example.com). Empty = use site base URL.
     */
    public ?string $shortlinkBaseUrl = null;

    /**
     * @var string|null Optional absolute base URL pattern with site tokens.
     * Supported tokens: {siteHandle}, {siteId}, {siteUid}
     * Example: https://short.example.com/{siteHandle}
     * @since 5.13.0
     */
    public ?string $shortlinkBaseUrlPattern = null;

    /**
     * @var int Length of generated short codes
     */
    public int $codeLength = 8;

    /**
     * @var array Reserved codes that cannot be used for short links
     */
    public array $reservedCodes = ['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings'];

    /**
     * @var int Default QR code size in pixels
     */
    public int $defaultQrSize = 256;

    /**
     * @var string Default QR code foreground color (hex)
     */
    public string $defaultQrColor = '#000000';

    /**
     * @var string Default QR code background color (hex)
     */
    public string $defaultQrBgColor = '#FFFFFF';

    /**
     * @var string Default QR code format (png or svg)
     */
    public string $defaultQrFormat = 'png';

    /**
     * @var bool Whether to cache generated QR codes
     */
    public bool $enableQrCodeCache = true;

    /**
     * @var int QR code cache duration in seconds (24 hours)
     */
    public int $qrCodeCacheDuration = 86400;

    /**
     * @var string Cache storage method (file or redis)
     * @since 5.3.0
     */
    public string $cacheStorageMethod = 'file';

    /**
     * @var string Default QR error correction level (L, M, Q, H)
     */
    public string $defaultQrErrorCorrection = 'M';

    /**
     * @var int Default QR code margin/quiet zone in modules
     */
    public int $defaultQrMargin = 4;

    /**
     * @var string QR code module style (square, rounded, dots)
     */
    public string $qrModuleStyle = 'square';

    /**
     * @var string QR code eye style (square, rounded, leaf)
     */
    public string $qrEyeStyle = 'square';

    /**
     * @var string|null QR eye color override (hex) or null to match modules
     */
    public ?string $qrEyeColor = null;

    /**
     * @var bool Enable logo overlay on QR codes
     */
    public bool $enableQrLogo = false;

    /**
     * @var string|null Asset volume UID allowed for QR logos (null = all)
     */
    public ?string $qrLogoVolumeUid = null;

    /**
     * @var int|null Default QR logo asset ID
     */
    public ?int $defaultQrLogoId = null;

    /**
     * @var int QR logo size as a percentage of QR code (10-30)
     */
    public int $qrLogoSize = 20;

    /**
     * @var bool Allow QR code downloads
     */
    public bool $enableQrDownload = true;

    /**
     * @var string QR code download filename pattern
     */
    public string $qrDownloadFilename = '{code}-qr-{size}';

    /**
     * @var int Default HTTP status code for redirects
     */
    public int $defaultHttpCode = 301;

    /**
     * @var bool Pass query parameters from shortlink URL to destination URL
     * @since 5.11.0
     */
    public bool $passQueryParams = false;

    /**
     * @var bool Perform a direct server-side HTTP redirect without rendering a template
     * @since 5.12.0
     */
    public bool $directRedirect = false;

    /**
     * @var string URL to redirect to when a short link is not found
     */
    public string $notFoundRedirectUrl = '/';

    /**
     * @var string|null Custom template path for redirects
     */
    public ?string $redirectTemplate = null;

    /**
     * @var string Message shown when a link is expired
     */
    public string $expiredMessage = 'This link has expired';

    /**
     * @var string|null Custom template path for expired links
     */
    public ?string $expiredTemplate = null;

    /**
     * @var string URL prefix for QR code endpoints (e.g., qr)
     */
    public string $qrPrefix = '';

    /**
     * @var string|null Custom QR code display template path
     */
    public ?string $qrTemplate = null;

    /**
     * @var bool Whether analytics tracking is enabled
     */
    public bool $enableAnalytics = true;

    /**
     * @var int Analytics retention in days (0 = keep forever)
     */
    public int $analyticsRetention = 90;

    /**
     * @var bool Anonymize IP address with subnet masking (e.g., 192.168.1.123 → 192.168.1.0)
     */
    public bool $anonymizeIpAddress = false;

    /**
     * @var string|null Secret salt for IP hashing (from .env)
     */
    public ?string $ipHashSalt = null;

    /**
     * @var bool Enable geolocation lookup for analytics
     */
    public bool $enableGeoDetection = false;

    /**
     * @var string Geo IP lookup provider (ip-api.com, ipapi.co, ipinfo.io)
     */
    public string $geoProvider = 'ip-api.com';

    /**
     * @var string|null API key for paid provider tiers (enables HTTPS for ip-api.com)
     * @since 5.9.0
     */
    public ?string $geoApiKey = null;

    /**
     * @var bool Cache device detection results
     */
    public bool $cacheDeviceDetection = true;

    /**
     * @var int Device detection cache duration in seconds (1 hour)
     */
    public int $deviceDetectionCacheDuration = 3600;

    /**
     * @var string|null Default country for local development (when IP is private)
     */
    public ?string $defaultCountry = null;

    /**
     * @var string|null Default city for local development (when IP is private)
     */
    public ?string $defaultCity = null;

    /**
     * @var string Log level (debug, info, warning, error)
     */
    public string $logLevel = 'error';

    /**
     * @var int Items per page in element indexes
     */
    public int $itemsPerPage = 50;

    /**
     * @var array|null Enabled integration handles
     */
    public ?array $enabledIntegrations = ['redirect-manager'];

    /**
     * @var array|null Redirect Manager events that trigger link updates
     */
    public ?array $redirectManagerEvents = ['slug-change'];

    /**
     * @var array SEOmatic events to emit for tracking
     */
    public array $seomaticTrackingEvents = ['redirect', 'qr_scan'];

    /**
     * @var string Event prefix for SEOmatic/GTM events
     */
    public string $seomaticEventPrefix = 'shortlink_manager';

    /**
     * Database table name for settings persistence
     */
    protected static function tableName(): string
    {
        return 'shortlinkmanager_settings';
    }

    /**
     * Plugin handle for config file lookup
     */
    protected static function pluginHandle(): string
    {
        return 'shortlink-manager';
    }

    /**
     * Boolean fields for type casting from database
     */
    protected static function booleanFields(): array
    {
        return [
            'enableQrCodeCache',
            'enableQrLogo',
            'enableQrDownload',
            'enableAnalytics',
            'enableGeoDetection',
            'anonymizeIpAddress',
            'cacheDeviceDetection',
            'passQueryParams',
            'directRedirect',
        ];
    }

    /**
     * Integer fields for type casting from database
     */
    protected static function integerFields(): array
    {
        return [
            'codeLength',
            'defaultQrSize',
            'qrCodeCacheDuration',
            'defaultQrMargin',
            'qrLogoSize',
            'defaultHttpCode',
            'analyticsRetention',
            'defaultQrLogoId',
            'itemsPerPage',
            'deviceDetectionCacheDuration',
        ];
    }

    /**
     * Array fields for JSON serialization/deserialization
     */
    protected static function jsonFields(): array
    {
        return [
            'enabledSites',
            'reservedCodes',
            'enabledIntegrations',
            'redirectManagerEvents',
            'seomaticTrackingEvents',
        ];
    }

    /**
     * Fields to exclude from database save (env/config only)
     */
    protected static function excludeFromSave(): array
    {
        return ['ipHashSalt', 'defaultCountry', 'defaultCity'];
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'notFoundRedirectUrl',
                    'ipHashSalt',
                    'shortlinkBaseUrl',
                    'shortlinkBaseUrlPattern',
                    'redirectTemplate',
                    'expiredTemplate',
                    'qrTemplate',
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
        $this->setLoggingHandle(static::pluginHandle());

        // Fallback to .env if ipHashSalt not set by config file
        if ($this->ipHashSalt === null) {
            $this->ipHashSalt = App::env('SHORTLINK_MANAGER_IP_SALT');
        }

        // Load default location from .env if not set by config file
        if ($this->defaultCountry === null) {
            $this->defaultCountry = App::env('SHORTLINK_MANAGER_DEFAULT_COUNTRY');
        }
        if ($this->defaultCity === null) {
            $this->defaultCity = App::env('SHORTLINK_MANAGER_DEFAULT_CITY');
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
            [['shortlinkBaseUrl', 'shortlinkBaseUrlPattern'], 'string', 'max' => 500],
            [['shortlinkBaseUrl'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
            [['shortlinkBaseUrlPattern'], 'validateShortlinkBaseUrlPattern'],
            [['slugPrefix'], 'match', 'pattern' => '/^[a-zA-Z0-9\-\_]+$/', 'message' => Craft::t('shortlink-manager', 'Only letters, numbers, hyphens, and underscores are allowed.')],
            [['slugPrefix'], 'validateSlugPrefix'],
            [['qrPrefix'], 'match', 'pattern' => '/^[a-zA-Z0-9\-\_\/]+$/', 'message' => Craft::t('shortlink-manager', 'Only letters, numbers, hyphens, underscores, and slashes are allowed.')],
            [['qrPrefix'], RoutePrefixValidator::class, 'translationCategory' => 'shortlink-manager'],
            [['qrPrefix'], 'validateQrPrefix'],
            [['enableAnalytics', 'enableGeoDetection', 'anonymizeIpAddress', 'enableQrLogo', 'enableQrDownload', 'passQueryParams', 'directRedirect'], 'boolean'],
            [['enabledSites', 'enabledIntegrations', 'redirectManagerEvents', 'seomaticTrackingEvents'], 'safe'],
            [['enabledSites'], 'each', 'rule' => ['integer']],
            [['seomaticTrackingEvents'], 'each', 'rule' => ['string']],
            [['seomaticEventPrefix'], 'string', 'max' => 50],
            [['seomaticEventPrefix'], 'match', 'pattern' => '/^[a-z0-9\_]+$/', 'message' => Craft::t('shortlink-manager', 'Only lowercase letters, numbers, and underscores are allowed.')],
            [['redirectTemplate', 'expiredTemplate', 'qrTemplate'], 'string', 'max' => 500],
            [['redirectTemplate', 'expiredTemplate', 'qrTemplate'], TemplatePathValidator::class, 'translationCategory' => 'shortlink-manager', 'checkTemplateExists' => true],
            [['codeLength', 'defaultQrSize', 'qrCodeCacheDuration', 'deviceDetectionCacheDuration', 'defaultQrMargin', 'qrLogoSize', 'defaultHttpCode', 'analyticsRetention', 'itemsPerPage'], 'integer'],
            [['itemsPerPage'], 'integer', 'min' => 10, 'max' => 500],
            [['codeLength'], 'integer', 'min' => 4, 'max' => 32],
            [['defaultQrSize'], 'integer', 'min' => 100, 'max' => 1000],
            [['qrCodeCacheDuration', 'deviceDetectionCacheDuration'], 'integer', 'min' => 60, 'max' => 604800],
            [['defaultQrMargin'], 'integer', 'min' => 0, 'max' => 10],
            [['qrLogoSize'], 'integer', 'min' => 10, 'max' => 30],
            [['analyticsRetention'], 'integer', 'min' => 0, 'max' => 3650],
            [['defaultHttpCode'], 'in', 'range' => [301, 302, 307, 308]],
            [['defaultQrColor', 'defaultQrBgColor', 'qrEyeColor'], 'string'],
            [['defaultQrColor', 'defaultQrBgColor'], 'match', 'pattern' => '/^#[0-9A-F]{6}$/i'],
            [['qrEyeColor'], 'match', 'pattern' => '/^#[0-9A-F]{6}$/i', 'skipOnEmpty' => true],
            [['defaultQrFormat'], 'in', 'range' => ['png', 'svg']],
            [['defaultQrErrorCorrection'], 'in', 'range' => ['L', 'M', 'Q', 'H']],
            [['cacheStorageMethod'], 'in', 'range' => ['file', 'redis']],
            [['geoProvider'], 'in', 'range' => ['ip-api.com', 'ipapi.co', 'ipinfo.io']],
            [['geoApiKey'], 'string', 'max' => 255, 'skipOnEmpty' => true],
            [['qrModuleStyle'], 'in', 'range' => ['square', 'rounded', 'dots']],
            [['qrEyeStyle'], 'in', 'range' => ['square', 'rounded', 'leaf']],
            [['qrDownloadFilename'], 'string'],
            [['qrDownloadFilename'], 'validateQrDownloadFilename'],
            [['qrLogoVolumeUid'], 'string'],
            [['defaultQrLogoId'], 'integer'],
            [['defaultQrLogoId'], 'required', 'when' => function($model) {
                return $model->enableQrLogo;
            }, 'message' => Craft::t('shortlink-manager', 'Default logo is required when logo overlay is enabled.')],
            [['notFoundRedirectUrl', 'expiredMessage'], 'string'],
            [['notFoundRedirectUrl'], UrlOrPathValidator::class, 'translationCategory' => 'shortlink-manager'],
            [['ipHashSalt'], 'string', 'min' => 32, 'message' => Craft::t('shortlink-manager', 'Salt must be at least 32 characters'), 'skipOnEmpty' => true],
            [['reservedCodes'], 'each', 'rule' => ['string']],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            [['logLevel'], 'validateLogLevel'],
        ];
    }

    /**
     * Set default QR logo ID from asset field (handles array/string input)
     *
     * @param int|array|string|null $value
     * @since 5.6.0
     */
    public function setDefaultQrLogoId(int|array|string|null $value): void
    {
        if (is_array($value)) {
            $this->defaultQrLogoId = !empty($value) ? (int) reset($value) : null;
        } elseif (is_string($value)) {
            $this->defaultQrLogoId = $value !== '' ? (int) $value : null;
        } else {
            $this->defaultQrLogoId = $value !== null ? (int) $value : null;
        }
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
                            'configFile' => 'config/shortlink-manager.php',
                        ]);
                        Craft::$app->getSession()->set('slm_debug_config_warning', true);
                    }
                } else {
                    $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                        'configFile' => 'config/shortlink-manager.php',
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
        if (PluginHelper::isPluginInstalled('smartlink-manager')) {
            try {
                $smartLinksPlugin = PluginHelper::getPlugin('smartlink-manager');
                if ($smartLinksPlugin) {
                    $smartLinksSettings = $smartLinksPlugin->getSettings();
                    $smartLinksPluginName = $smartLinksSettings->pluginName ?? 'Smart Links';

                    // Check against Smart Links slugPrefix
                    /** @phpstan-ignore-next-line - Dynamic property access on plugin settings */
                    $smartLinksSlugPrefix = property_exists($smartLinksSettings, 'slugPrefix') ? $smartLinksSettings->slugPrefix : 'go';
                    if ($slugPrefix === $smartLinksSlugPrefix) {
                        $conflicts[] = "{$smartLinksPluginName} slug prefix ('{$smartLinksSlugPrefix}')";
                    }

                    // Check against Smart Links qrPrefix
                    /** @phpstan-ignore-next-line - Dynamic property access on plugin settings */
                    $smartLinksQrPrefix = property_exists($smartLinksSettings, 'qrPrefix') ? $smartLinksSettings->qrPrefix : 'qr';
                    if ($slugPrefix === $smartLinksQrPrefix) {
                        $conflicts[] = "{$smartLinksPluginName} QR prefix ('{$smartLinksQrPrefix}')";
                    }
                }
            } catch (\Exception $e) {
                // Silently continue if we can't check smart link plugin
            }
        }

        if (!empty($conflicts)) {
            $suggestions = ['sl', 'link', 'l', 's'];
            $this->addError($attribute, Craft::t('shortlink-manager', 'Slug prefix "{prefix}" conflicts with: {conflicts}. Suggestions: {suggestions}', [
                'prefix' => $slugPrefix,
                'conflicts' => implode(', ', $conflicts),
                'suggestions' => implode(', ', $suggestions),
            ]));
        }
    }

    /**
     * Validate QR prefix to prevent conflicts
     */
    public function validateQrPrefix($attribute, $params, $validator)
    {
        $qrPrefix = trim((string) $this->$attribute, '/');

        if (empty($qrPrefix)) {
            return;
        }

        $conflicts = [];

        // Parse the prefix (supports both "qr" and "s/qr" patterns)
        $segments = explode('/', $qrPrefix);
        $isNested = count($segments) > 1;

        // Check against own slugPrefix
        if (!$isNested && $qrPrefix === $this->slugPrefix) {
            $this->addError($attribute, Craft::t('shortlink-manager', 'QR prefix cannot be the same as your URL segment. Try: {segment}/qr, qr, or q', [
                'segment' => $this->slugPrefix,
            ]));
            return;
        }

        // Check if nested pattern conflicts with own slugPrefix
        if ($isNested) {
            $baseSegment = $segments[0];
            if ($baseSegment !== $this->slugPrefix) {
                $this->addError($attribute, Craft::t('shortlink-manager', 'Nested QR prefix must start with your URL segment "{segment}". Use: {segment}/{qr} or use standalone like "qr"', [
                    'segment' => $this->slugPrefix,
                    'qr' => $segments[1] ?? 'qr',
                ]));
                return;
            }
        }

        // Check against Smart Links if installed
        if (PluginHelper::isPluginInstalled('smartlink-manager')) {
            try {
                $smartLinksPlugin = PluginHelper::getPlugin('smartlink-manager');
                if ($smartLinksPlugin) {
                    $smartLinksSettings = $smartLinksPlugin->getSettings();
                    $smartLinksPluginName = $smartLinksSettings->pluginName ?? 'Smart Links';

                    // Check against Smart Links slugPrefix
                    /** @phpstan-ignore-next-line - Dynamic property access on plugin settings */
                    $smartLinksSlugPrefix = property_exists($smartLinksSettings, 'slugPrefix') ? $smartLinksSettings->slugPrefix : 'go';
                    if (!$isNested && $qrPrefix === $smartLinksSlugPrefix) {
                        $conflicts[] = "{$smartLinksPluginName} link prefix ('{$smartLinksSlugPrefix}')";
                    }

                    // Check against Smart Links qrPrefix
                    /** @phpstan-ignore-next-line - Dynamic property access on plugin settings */
                    $smartLinksQrPrefix = property_exists($smartLinksSettings, 'qrPrefix') ? $smartLinksSettings->qrPrefix : 'qr';
                    if (!$isNested && $qrPrefix === $smartLinksQrPrefix) {
                        $conflicts[] = "{$smartLinksPluginName} QR prefix ('{$smartLinksQrPrefix}')";
                    }
                }
            } catch (\Exception $e) {
                // Silently continue if we can't check smart link plugin
            }
        }

        if (!empty($conflicts)) {
            $suggestions = $this->suggestQrPrefix($qrPrefix);
            $this->addError($attribute, Craft::t('shortlink-manager', 'QR prefix "{prefix}" conflicts with: {conflicts}. Suggestions: {suggestions}', [
                'prefix' => $qrPrefix,
                'conflicts' => implode(', ', $conflicts),
                'suggestions' => implode(', ', $suggestions),
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
     * Validate QR download filename pattern.
     *
     * Allowed tokens: {code}, {size}, {format}
     * Allowed characters outside tokens: letters, numbers, dash, underscore, dot
     * Spaces are not allowed.
     */
    public function validateQrDownloadFilename(string $attribute, mixed $params, mixed $validator): void
    {
        $pattern = (string)($this->$attribute ?? '');
        if ($pattern === '') {
            return;
        }

        if (preg_match('/\s/', $pattern) === 1) {
            $this->addError($attribute, Craft::t('shortlink-manager', 'Download filename pattern cannot contain spaces.'));
            return;
        }

        preg_match_all('/\{[^}]+\}/', $pattern, $matches);
        $tokens = $matches[0];
        $allowedTokens = ['{code}', '{size}', '{format}'];

        foreach ($tokens as $token) {
            if (!in_array($token, $allowedTokens, true)) {
                $this->addError(
                    $attribute,
                    Craft::t(
                        'shortlink-manager',
                        'Unsupported token "{token}". Allowed tokens: {allowed}.',
                        ['token' => $token, 'allowed' => implode(', ', $allowedTokens)]
                    )
                );
                return;
            }
        }

        $staticPart = preg_replace('/\{[^}]+\}/', '', $pattern) ?? '';
        if ($staticPart !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $staticPart) !== 1) {
            $this->addError(
                $attribute,
                Craft::t('shortlink-manager', 'Download filename pattern contains invalid characters. Use only letters, numbers, dash (-), underscore (_), dot (.), and supported tokens.')
            );
        }
    }

    /**
     * Validate shortlink base URL pattern format.
     *
     * @since 5.13.0
     */
    public function validateShortlinkBaseUrlPattern(string $attribute, mixed $params, mixed $validator): void
    {
        $pattern = trim((string) App::parseEnv($this->$attribute));
        if ($pattern === '') {
            return;
        }

        if (!preg_match('/^https?:\/\//i', $pattern)) {
            $this->addError($attribute, Craft::t('shortlink-manager', 'Shortlink base URL pattern must start with http:// or https://'));
            return;
        }

        if (strpos($pattern, '{') !== false && !preg_match('/\{siteHandle\}|\{siteId\}|\{siteUid\}/', $pattern)) {
            $this->addError($attribute, Craft::t('shortlink-manager', 'Unsupported token in shortlink base URL pattern. Supported tokens: {siteHandle}, {siteId}, {siteUid}.'));
        }
    }

    /**
     * Build a public shortlink URL with optional base URL overrides.
     *
     * @param string $path Relative path (without leading slash preferred)
     * @param int|null $siteId Site ID for token expansion and site fallback URLs
     * @param array $params Query parameters
     * @return string
     * @since 5.13.0
     */
    public function buildPublicUrl(string $path, ?int $siteId = null, array $params = []): string
    {
        $relativePath = trim((string) $path);
        $relativePath = preg_replace('#/+#', '/', $relativePath) ?? $relativePath;
        $relativePath = trim($relativePath, '/');
        $siteId = $siteId ?: Craft::$app->getSites()->getCurrentSite()->id;

        $pattern = trim((string) App::parseEnv($this->shortlinkBaseUrlPattern ?? ''));
        if ($pattern !== '') {
            $base = $this->expandShortlinkBasePattern($pattern, $siteId);
            if ($base !== '') {
                $url = rtrim($base, '/');
                if ($relativePath !== '') {
                    $url .= '/' . $relativePath;
                }

                return UrlHelper::urlWithParams($url, $params);
            }
        }

        $baseUrl = trim((string) App::parseEnv($this->shortlinkBaseUrl ?? ''));
        if ($baseUrl !== '') {
            $url = rtrim($baseUrl, '/');
            if ($relativePath !== '') {
                $url .= '/' . $relativePath;
            }

            return UrlHelper::urlWithParams($url, $params);
        }

        return UrlHelper::siteUrl($relativePath, $params, null, $siteId);
    }

    /**
     * Expand supported site tokens in shortlink base pattern.
     *
     * @since 5.13.0
     */
    private function expandShortlinkBasePattern(string $pattern, int $siteId): string
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);
        if (!$site) {
            return $pattern;
        }

        return strtr($pattern, [
            '{siteHandle}' => $site->handle,
            '{siteId}' => (string) $site->id,
            '{siteUid}' => $site->uid,
        ]);
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
            'shortlinkBaseUrl' => Craft::t('shortlink-manager', 'Shortlink Base URL'),
            'shortlinkBaseUrlPattern' => Craft::t('shortlink-manager', 'Shortlink Base URL Pattern'),
            'codeLength' => Craft::t('shortlink-manager', 'Code Length'),
            'reservedCodes' => Craft::t('shortlink-manager', 'Reserved Codes'),
            'defaultQrSize' => Craft::t('shortlink-manager', 'Default QR Code Size'),
            'defaultQrColor' => Craft::t('shortlink-manager', 'Default QR Code Color'),
            'defaultQrBgColor' => Craft::t('shortlink-manager', 'Default QR Background Color'),
            'defaultQrFormat' => Craft::t('shortlink-manager', 'Default QR Code Format'),
            'qrCodeCacheDuration' => Craft::t('shortlink-manager', 'QR Code Cache Duration (seconds)'),
            'cacheStorageMethod' => Craft::t('shortlink-manager', 'Cache Storage Method'),
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
