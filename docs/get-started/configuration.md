# Configuration

Most settings can be managed from **ShortLink Manager → Settings** in the Control Panel — no config file needed for typical setups. A config file gives you per-environment overrides and lets you lock settings so they cannot be changed from the CP.

## Config file

Create `config/shortlink-manager.php` in your project (copy the sample template to start):

```bash
cp vendor/lindemannrock/craft-shortlink-manager/src/config.php config/shortlink-manager.php
```

The file supports Craft's standard multi-environment key convention (`'*'`, `'dev'`, `'production'`, etc.). Settings in the config file always take priority over the database. When a setting is overridden by a config file, the CP field shows a warning and is disabled.

## Settings reference

Settings are grouped below by their functional area, matching the CP settings pages.

---

### General

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `pluginName` | `string` | `'ShortLink Manager'` | Plugin display name shown in the control panel |

---

### Site settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabledSites` | `array` | `[]` | Site IDs where short links are enabled. Empty = all sites enabled |

---

### URL settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `usePrefix` | `bool` | `true` | Whether short links include `slugPrefix` in generated URLs (`true` => `/s/abc123`, `false` => `/abc123`) |
| `slugPrefix` | `string` | `'s'` | URL prefix for generated short links (e.g., `'s'` creates `/s/abc123`) |
| `shortlinkBaseUrl` | `string\|null` | `null` | Optional absolute base URL for generated shortlink and QR URLs (e.g., `https://short.example.com`). Supports tokens `{siteHandle}`, `{siteId}`, `{siteUid}` and env vars. Leave empty to use each site's base URL. |
| `qrPrefix` | `string` | `''` | URL prefix for QR code pages. When empty, the runtime route falls back to `qr` (QR URLs are `/qr/{code}`), while the CP auto-suggests `{slugPrefix}/qr` (e.g., `s/qr`). Supports standalone (`'qr'`) or nested (`'s/qr'`) patterns |
| `codeLength` | `int` | `8` | Length of generated short codes (min: 4, max: 32) |
| `reservedCodes` | `array` | `['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings']` | Codes that cannot be used for short links |

> [!NOTE]
> If `shortlinkBaseUrl` includes `{siteHandle}`, site-aware routes (e.g., `/{siteHandle}/s/{code}`) are registered automatically alongside the standard `s/{code}` routes.

---

### Template settings

These templates must exist in your site's `templates/` folder. Copy the reference templates from `vendor/lindemannrock/craft-shortlink-manager/src/templates/` to `templates/shortlink-manager/` and customize as needed.

All template paths support environment variables via Craft's `$ENV_VAR` syntax in the CP field, or via `App::env()` in the config file.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `redirectTemplate` | `string\|null` | `null` | Custom template path for redirects. Default: `shortlink-manager/redirect` |
| `expiredTemplate` | `string\|null` | `null` | Custom template path for expired links. Default: `shortlink-manager/expired` |
| `qrTemplate` | `string\|null` | `null` | Custom template path for QR code pages. Default: `shortlink-manager/qr` |
| `expiredMessage` | `string` | `'This link has expired'` | Message shown on the expired page when no custom redirect URL is set |

> [!NOTE]
> When `directRedirect` is enabled (globally or per link), the redirect template is bypassed entirely — a direct HTTP redirect is issued instead.

---

### Redirect behavior

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `defaultHttpCode` | `int` | `302` | Default HTTP status code for redirects. Options: `301`, `302`, `307`, `308` |
| `passQueryParams` @since(5.11.0) | `bool` | `false` | Pass query parameters from the shortlink URL to the destination URL. Can be overridden per link (null = use global) |
| `directRedirect` @since(5.12.0) | `bool` | `false` | Perform a direct server-side HTTP redirect without rendering a template. Disables SEOmatic client-side tracking (GTM/GA events). Can be overridden per link (null = use global). |
| `notFoundRedirectUrl` | `string` | `'/'` | Where to redirect when a short link is not found or disabled. Supports env vars |

> [!TIP]
> Use `directRedirect` for maximum performance or when you do not need SEOmatic client-side tracking events. The redirect template is still useful when you want GTM/GA events to fire before the browser navigates away.

> [!IMPORTANT]
> `directRedirect` is the fastest path, but it only records server-side analytics when the short URL request reaches Craft/PHP. If a browser, CDN, or static cache serves that URL before Craft runs, repeat-hit analytics can be bypassed. Keep `directRedirect = false` for analytics-safe redirect pages under caching, or add cache bypass rules for your shortlink routes when direct mode must be used.

> [!TIP]
> `302` is the default because it is the safest general-purpose status code for short links. `301` and `308` remain available, but they are much more likely to be cached aggressively by browsers and edge caches.

---

### Analytics

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enableAnalytics` | `bool` | `true` | Master switch for click tracking and analytics |
| `analyticsRetention` | `int` | `90` | Analytics retention in days. `0` = keep forever |
| `anonymizeIpAddress` | `bool` | `false` | Anonymize IP addresses with subnet masking before hashing (e.g., `192.168.1.123` → `192.168.1.0`) |
| `ipHashSalt` | `string\|null` | `null` | Secret salt for IP hashing. Reads from `SHORTLINK_MANAGER_IP_SALT` env var if not set in config |
| `enableGeoDetection` | `bool` | `false` | Enable geolocation lookup from IP addresses for analytics |
| `geoProvider` | `string` | `'ip-api.com'` | Geo IP lookup provider. Options: `'ip-api.com'`, `'ipapi.co'`, `'ipinfo.io'` |
| `geoApiKey` @since(5.9.0) | `string\|null` | `null` | API key for paid geo provider tiers |
| `defaultCountry` | `string\|null` | `null` | Default country code for local dev when IP is private (e.g., `'US'`). Reads from `SHORTLINK_MANAGER_DEFAULT_COUNTRY` env var |
| `defaultCity` | `string\|null` | `null` | Default city for local dev when IP is private. Reads from `SHORTLINK_MANAGER_DEFAULT_CITY` env var |

---

### QR codes

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `defaultQrSize` | `int` | `256` | Default QR code size in pixels (100–1000) |
| `defaultQrColor` | `string` | `'#000000'` | Default QR code foreground color (hex) |
| `defaultQrBgColor` | `string` | `'#FFFFFF'` | Default QR code background color (hex) |
| `defaultQrFormat` | `string` | `'png'` | Default QR code format. Options: `'png'`, `'svg'` |
| `defaultQrErrorCorrection` | `string` | `'M'` | Error correction level. Options: `'L'` (~7%), `'M'` (~15%), `'Q'` (~25%), `'H'` (~30%) |
| `defaultQrMargin` | `int` | `4` | Quiet zone (margin) in modules (0–10) |
| `qrModuleStyle` | `string` | `'square'` | Module shape. Options: `'square'`, `'rounded'`, `'dots'` |
| `qrEyeStyle` | `string` | `'square'` | Eye/finder pattern style. Options: `'square'`, `'rounded'`, `'leaf'` |
| `qrEyeColor` | `string\|null` | `null` | Eye color override (hex). `null` = match module color |
| `enableQrLogo` | `bool` | `false` | Enable logo overlay in the center of QR codes |
| `qrLogoVolumeUid` | `string\|null` | `null` | Asset volume UID allowed for QR logos. `null` = all volumes |
| `defaultQrLogoId` | `int\|null` | `null` | Default QR logo asset ID |
| `qrLogoSize` | `int` | `20` | Logo size as a percentage of the QR code (10–30) |
| `enableQrDownload` | `bool` | `true` | Allow QR code downloads |
| `qrDownloadFilename` | `string` | `'{code}-qr-{size}'` | Download filename pattern. Tokens: `{code}`, `{size}`, `{format}` |

---

### Cache

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enableQrCodeCache` | `bool` | `true` | Cache generated QR codes |
| `qrCodeCacheDuration` | `int` | `86400` | QR code cache duration in seconds (default: 24 hours) |
| `cacheStorageMethod` @since(5.3.0) | `string` | `'file'` | Cache storage method. Options: `'file'` (single server), `'redis'` (load-balanced/multi-server) |
| `cacheDeviceDetection` | `bool` | `true` | Cache device detection results |
| `deviceDetectionCacheDuration` | `int` | `3600` | Device detection cache duration in seconds (default: 1 hour) |

---

### Integrations

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabledIntegrations` | `array` | `['redirect-manager']` | Enabled integration handles |
| `redirectManagerEvents` | `array` | `['slug-change']` | Redirect Manager events that trigger automatic link updates |
| `seomaticTrackingEvents` | `array` | `['redirect', 'qr_scan']` | SEOmatic event types to emit for GTM/GA tracking |
| `seomaticEventPrefix` | `string` | `'shortlink_manager'` | Event name prefix for SEOmatic/GTM events (lowercase, numbers, underscores only) |

---

### Interface

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `itemsPerPage` | `int` | `50` | Items per page in element indexes (10–500) |
| `logLevel` | `string` | `'error'` | Log level. Options: `'error'`, `'warning'`, `'info'`, `'debug'` (debug requires devMode) |

---

## Environment variables

Several settings read from environment variables automatically, with no config file entry required:

| Env Variable | Setting | Notes |
|--------------|---------|-------|
| `SHORTLINK_MANAGER_IP_SALT` | `ipHashSalt` | Auto-loaded if `ipHashSalt` is not set in config |
| `SHORTLINK_MANAGER_DEFAULT_COUNTRY` | `defaultCountry` | Auto-loaded if `defaultCountry` is not set in config |
| `SHORTLINK_MANAGER_DEFAULT_CITY` | `defaultCity` | Auto-loaded if `defaultCity` is not set in config |
| `SHORTLINK_MANAGER_GEO_API_KEY` | `geoApiKey` | **Not** auto-loaded — pass via `App::env('SHORTLINK_MANAGER_GEO_API_KEY')` in your config file |

The `shortlinkBaseUrl`, `notFoundRedirectUrl`, `redirectTemplate`, `expiredTemplate`, and `qrTemplate` settings also support Craft's env var syntax (`$ENV_VAR`) when set via the CP autosuggest fields, or via `App::env()` in the config file.

```bash
# .env
SHORTLINK_MANAGER_IP_SALT=your-64-char-salt-here
SHORTLINK_BASE_URL=https://short.example.com
```

```php
// config/shortlink-manager.php
use craft\helpers\App;

return [
    '*' => [
        'shortlinkBaseUrl' => App::env('SHORTLINK_BASE_URL'),
    ],
];
```

---

## Example configuration

A complete multi-environment config file:

```php
<?php
// config/shortlink-manager.php
use craft\helpers\App;

return [
    '*' => [
        // General
        'pluginName' => 'ShortLink Manager',

        // IP privacy — generate with: php craft shortlink-manager/security/generate-salt
        'ipHashSalt' => App::env('SHORTLINK_MANAGER_IP_SALT'),

        // URL settings
        'slugPrefix' => 's',
        'qrPrefix' => 's/qr',
        'codeLength' => 8,
        'reservedCodes' => ['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings'],

        // Optional custom short domain (single domain, all sites)
        // 'shortlinkBaseUrl' => App::env('SHORTLINK_BASE_URL'),

        // Optional multisite short domains ({siteHandle} is replaced with site handle)
        // 'shortlinkBaseUrl' => 'https://short.example.com/{siteHandle}',

        // Redirect behavior
        'defaultHttpCode' => 302,
        'passQueryParams' => false,
        'directRedirect' => false,
        'notFoundRedirectUrl' => '/',

        // Analytics
        'enableAnalytics' => true,
        'analyticsRetention' => 90,
        'anonymizeIpAddress' => false,
        'enableGeoDetection' => false,

        // Logging
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
        'cacheStorageMethod' => 'redis',
        'qrCodeCacheDuration' => 604800, // 7 days
        'deviceDetectionCacheDuration' => 7200,
    ],
];
```

---

## Settings precedence

Settings are resolved in this order (later sources override earlier ones):

1. Plugin defaults (code)
2. Database-stored settings (saved from the CP)
3. Config file settings (`config/shortlink-manager.php`)
4. Environment-specific config overrides

---

## Custom short domain

To serve all short links from a dedicated short domain (e.g., `https://short.example.com`), use the `shortlinkBaseUrl` setting:

```php
// config/shortlink-manager.php
return [
    '*' => [
        'shortlinkBaseUrl' => App::env('SHORTLINK_BASE_URL'),
    ],
];
```

```bash
# .env
SHORTLINK_BASE_URL=https://short.example.com
```

This overrides the base URL used when generating shortlink and QR URLs, without requiring a separate Craft site.

### Multisite short domains

For a Craft multisite where each site needs its own short domain, use `shortlinkBaseUrl` with a `{siteHandle}` token:

```php
// config/shortlink-manager.php
return [
    '*' => [
        'shortlinkBaseUrl' => 'https://short.example.com/{siteHandle}',
    ],
];
```

This generates URLs like `https://short.example.com/en/s/abc123` for the `en` site and `https://short.example.com/de/s/abc123` for the `de` site.

When a `{siteHandle}` token is configured, ShortLink Manager registers additional site-aware routes (`/{siteHandle}/s/{code}`) alongside the standard `s/{code}` routes, and the redirect controller resolves the target site from the route handle automatically.

Supported tokens: `{siteHandle}`, `{siteId}`, `{siteUid}`.

> [!NOTE]
> `shortlinkBaseUrl` supports optional tokens `{siteHandle}`, `{siteId}`, and `{siteUid}` for multisite URL generation.

## Translations

ShortLink Manager includes translations for 12 languages. See [Translations](../resources/translations.md) for the full list and override instructions.
