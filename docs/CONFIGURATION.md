# ShortLink Manager Configuration

## Configuration File

You can override plugin settings by creating a `shortlink-manager.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/craft-shortlink-manager/src/config.php` to `config/shortlink-manager.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
use craft\helpers\App;

return [
    // General Settings
    'pluginName' => 'ShortLink Manager',
    'ipHashSalt' => App::env('SHORTLINK_MANAGER_IP_SALT'),

    // URL Settings
    'slugPrefix' => 's',
    'qrPrefix' => 'sqr',
    'codeLength' => 8,
    'customDomain' => '',
    'reservedCodes' => ['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings'],

    // Redirect Settings
    'defaultHttpCode' => 301,
    'expiredMessage' => 'This link has expired',
    'expiredTemplate' => null,
    'notFoundRedirectUrl' => '/',

    // Redirect Manager Integration
    'integrateRedirectManager' => true,
    'autoRedirectOnExpire' => true,
    'autoRedirectOnDelete' => true,

    // Logging
    'logLevel' => 'error',

    // QR Code Settings
    'enableQrCodes' => true,
    'defaultQrSize' => 256,
    'defaultQrFormat' => 'png',
    'defaultQrColor' => '#000000',
    'defaultQrBgColor' => '#FFFFFF',
    'defaultQrMargin' => 4,
    'qrModuleStyle' => 'square',
    'qrEyeStyle' => 'square',
    'qrEyeColor' => null,

    // QR Logo Settings
    'enableQrLogo' => false,
    'qrLogoSize' => 20,

    // QR Technical
    'defaultQrErrorCorrection' => 'M',

    // QR Downloads
    'enableQrDownload' => true,
    'qrDownloadFilename' => '{code}-qr-{size}',

    // Analytics Settings
    'enableAnalytics' => true,
    'enableGeoDetection' => false,
    'anonymizeIpAddress' => false,
    'analyticsRetention' => 90,

    // Interface Settings
    'itemsPerPage' => 50,

    // Cache Settings
    'enableQrCodeCache' => true,
    'qrCodeCacheDuration' => 86400,
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 3600,
];
```

### Multi-Environment Configuration

```php
<?php
return [
    '*' => [
        'pluginName' => 'ShortLink Manager',
        'enableAnalytics' => true,
        'logLevel' => 'error',
    ],

    'dev' => [
        'logLevel' => 'debug',
        'analyticsRetention' => 30,
        'cacheDeviceDetection' => false,
        'enableQrCodeCache' => false,
    ],

    'staging' => [
        'logLevel' => 'info',
        'analyticsRetention' => 90,
        'qrCodeCacheDuration' => 3600,
    ],

    'production' => [
        'logLevel' => 'error',
        'analyticsRetention' => 365,
        'qrCodeCacheDuration' => 604800,
    ],
];
```

### Setting Descriptions

#### General Settings

##### pluginName
Display name for the plugin in Craft CP navigation.
- **Type:** `string`
- **Default:** `'ShortLink Manager'`

##### ipHashSalt
Secure salt for IP address hashing (required for analytics).
- **Type:** `string`
- **Default:** From `.env` file (`SHORTLINK_MANAGER_IP_SALT`)
- **Generate:** `php craft shortlink-manager/security/generate-salt`

#### URL Settings

##### slugPrefix
URL prefix for shortlinks (e.g., 's' creates /s/ABC123).
- **Type:** `string`
- **Default:** `'s'`

##### qrPrefix
URL prefix for QR code pages (e.g., 'sqr' creates /sqr/ABC123/view). Supports standalone ('qr') or nested ('s/qr') patterns.
- **Type:** `string`
- **Default:** `'sqr'`

##### codeLength
Length of generated shortlink codes.
- **Type:** `int`
- **Default:** `8`

##### customDomain
Optional custom domain for shortlinks.
- **Type:** `string`
- **Default:** `''` (empty)

##### reservedCodes
Array of codes that cannot be used for shortlinks.
- **Type:** `array`
- **Default:** `['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings']`

#### Redirect Settings

##### defaultHttpCode
Default HTTP redirect code for shortlinks.
- **Type:** `int`
- **Default:** `301`

##### expiredMessage
Message shown when shortlink has expired.
- **Type:** `string`
- **Default:** `'This link has expired'`

##### expiredTemplate
Custom expired page template path (e.g., 'shortlink-manager/expired').
- **Type:** `string|null`
- **Default:** `null`

##### notFoundRedirectUrl
Where to redirect for invalid/disabled shortlinks.
- **Type:** `string`
- **Default:** `'/'`

#### Redirect Manager Integration

##### integrateRedirectManager
Enable integration with Redirect Manager plugin.
- **Type:** `bool`
- **Default:** `true`

##### autoRedirectOnExpire
Automatically create redirects when shortlinks expire.
- **Type:** `bool`
- **Default:** `true`

##### autoRedirectOnDelete
Automatically create redirects when shortlinks are deleted.
- **Type:** `bool`
- **Default:** `true`

#### QR Code Settings

##### enableQrCodes
Enable QR code generation for shortlinks.
- **Type:** `bool`
- **Default:** `true`

##### defaultQrSize
Default size in pixels for generated QR codes.
- **Type:** `int`
- **Range:** `100-1000`
- **Default:** `256`

##### defaultQrFormat
Output format for QR codes.
- **Type:** `string`
- **Options:** `'png'`, `'svg'`
- **Default:** `'png'`

##### defaultQrColor
Foreground color for QR codes.
- **Type:** `string` (hex color)
- **Default:** `'#000000'`

##### defaultQrBgColor
Background color for QR codes.
- **Type:** `string` (hex color)
- **Default:** `'#FFFFFF'`

##### defaultQrMargin
White space margin around QR code.
- **Type:** `int`
- **Range:** `0-10` modules
- **Default:** `4`

##### qrModuleStyle
QR code module shape.
- **Type:** `string`
- **Options:** `'square'`, `'rounded'`, `'dots'`
- **Default:** `'square'`

##### qrEyeStyle
QR code eye/finder pattern style.
- **Type:** `string`
- **Options:** `'square'`, `'rounded'`, `'leaf'`
- **Default:** `'square'`

##### qrEyeColor
Custom color for eye patterns.
- **Type:** `string|null` (hex color)
- **Default:** `null` (uses main color)

##### enableQrLogo
Enable logo overlay in center of QR codes.
- **Type:** `bool`
- **Default:** `false`

##### qrLogoSize
Logo size as percentage of QR code.
- **Type:** `int`
- **Range:** `10-30`%
- **Default:** `20`

##### defaultQrErrorCorrection
Error correction level for QR codes.
- **Type:** `string`
- **Options:** `'L'` (~7%), `'M'` (~15%), `'Q'` (~25%), `'H'` (~30%)
- **Default:** `'M'`

##### enableQrDownload
Allow users to download QR codes.
- **Type:** `bool`
- **Default:** `true`

##### qrDownloadFilename
Filename pattern for QR code downloads.
- **Type:** `string`
- **Placeholders:** `{code}`, `{size}`, `{format}`
- **Default:** `'{code}-qr-{size}'`

#### Analytics Settings

##### enableAnalytics
Master switch for click tracking and analytics.
- **Type:** `bool`
- **Default:** `true`

##### enableGeoDetection
Detect visitor location from IP addresses.
- **Type:** `bool`
- **Default:** `false`

##### anonymizeIpAddress
Mask IP addresses before hashing (subnet masking).
- **Type:** `bool`
- **Default:** `false`
- **IPv4:** Masks last octet (192.168.1.123 → 192.168.1.0)
- **IPv6:** Masks last 80 bits

##### analyticsRetention
Number of days to retain analytics data (0 = unlimited).
- **Type:** `int`
- **Default:** `90`

#### Interface Settings

##### itemsPerPage
Number of shortlinks per page in CP.
- **Type:** `int`
- **Range:** `10-500`
- **Default:** `50`

#### Cache Settings

##### enableQrCodeCache
Cache generated QR codes for better performance.
- **Type:** `bool`
- **Default:** `true`

##### qrCodeCacheDuration
QR code cache duration in seconds.
- **Type:** `int`
- **Default:** `86400` (24 hours)

##### cacheDeviceDetection
Cache device detection results.
- **Type:** `bool`
- **Default:** `true`

##### deviceDetectionCacheDuration
Device detection cache duration in seconds.
- **Type:** `int`
- **Default:** `3600` (1 hour)

### IP Privacy Protection Setup

Analytics requires a secure salt for IP hashing:

1. Generate salt: `php craft shortlink-manager/security/generate-salt`
2. Command automatically adds `SHORTLINK_MANAGER_IP_SALT` to your `.env` file
3. **Manually copy** the salt value to staging/production `.env` files
4. **Never regenerate** the salt in production

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database-stored settings (from CP)
3. Config file settings
4. Environment-specific config settings

**Note:** Config file settings always override database settings.

### Using Environment Variables

```php
use craft\helpers\App;

return [
    'enableAnalytics' => (bool)App::env('SHORTLINK_ANALYTICS') ?: true,
    'analyticsRetention' => (int)App::env('SHORTLINK_RETENTION') ?: 90,
    'customDomain' => App::env('SHORTLINK_CUSTOM_DOMAIN') ?: '',
];
```

### Production Best Practices

```php
'production' => [
    'logLevel' => 'error',
    'analyticsRetention' => 365,
    'enableQrCodeCache' => true,
    'qrCodeCacheDuration' => 604800,  // 7 days
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 7200,
],
```
