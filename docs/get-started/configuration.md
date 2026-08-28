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
> Site-aware routes (e.g., `/{siteHandle}/s/{code}`) are always registered alongside the standard `s/{code}` routes — independent of `shortlinkBaseUrl`. They let the redirect controller resolve the correct site when a `{siteHandle}` segment is in the URL, which is what makes the `{siteHandle}` token in `shortlinkBaseUrl` work.

---

### Template settings

ShortLink Manager renders the redirect, expired-link, and QR landing pages from your site's `templates/` folder. Complete [Installation & Setup](installation.md#post-install-setup) first so the starter templates exist before public links render.

The fields below only change where ShortLink Manager looks for those templates. Leave them empty to use the default paths, or point them at custom paths after you have placed templates there. For bundled template locations, manual copy commands, and the variables each template receives, see [Custom templates](../developers/custom-templates.md).

On multisite installations, Craft checks `templates/{siteHandle}/{path}` first and then falls back to `templates/{path}`. Setup is ready when every site enabled in ShortLink Manager can resolve all three configured templates; sites do not need their own template directories when the global templates exist.

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

### Logging

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `logLevel` | `string` | `'error'` | Log level. Options: `'error'`, `'warning'`, `'info'`, `'debug'` (debug requires devMode) |

---

### Redirect behavior

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `defaultHttpCode` | `int` | `302` | Default HTTP status code for redirects. Options: `301`, `302`, `307`, `308` |
| `passQueryParams` @since(5.11.0) | `bool` | `false` | Pass query parameters from the shortlink URL to the destination URL. Can be overridden per link (null = use global) |
| `directRedirect` @since(5.12.0) | `bool` | `false` | Perform a direct server-side HTTP redirect without rendering a template. Disables SEOmatic client-side tracking (GTM/GA events). Can be overridden per link (null = use global). |
| `notFoundRedirectUrl` | `string` | `'/'` | Where to redirect when a short link is not found or disabled. Supports env vars |

> [!TIP]
> `302` is the default because it is the safest general-purpose status code for short links. `301` and `308` remain available, but they are much more likely to be cached aggressively by browsers and edge caches.

> [!TIP]
> Use `directRedirect` for maximum performance or when you do not need SEOmatic client-side tracking events. The redirect template is still useful when you want GTM/GA events to fire before the browser navigates away.

> [!IMPORTANT]
> `directRedirect` is the fastest path, but it only records server-side analytics when the short URL request reaches Craft/PHP. If a browser, CDN, or static cache serves that URL before Craft runs, repeat-hit analytics can be bypassed. Keep `directRedirect = false` for analytics-safe redirect pages under caching, or add cache bypass rules for your [shortlink routes](../feature-tour/custom-domain.md#site-aware-routes) when direct mode must be used.

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
| `defaultCountry` | `string\|null` | `null` | Default country code for local dev when IP is private (e.g., `'US'`). Reads from `SHORTLINK_MANAGER_DEFAULT_COUNTRY` env var. Requires `defaultCity`; otherwise private/local IP geo fields stay empty |
| `defaultCity` | `string\|null` | `null` | Default city for local dev when IP is private. Reads from `SHORTLINK_MANAGER_DEFAULT_CITY` env var. Requires `defaultCountry`; otherwise private/local IP geo fields stay empty |

---

### QR codes

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `defaultQrSize` | `int` | `256` | Initializes supported new links when no per-link size is supplied, and remains the saved/default, public, and direct programmatic fallback (100–1000). Authenticated exports separately support 100–4096 |
| `defaultQrColor` | `string` | `'#000000'` | Default QR code foreground color (hex) |
| `defaultQrBgColor` | `string` | `'#FFFFFF'` | Default QR code background color (hex) |
| `defaultQrFormat` | `string` | `'png'` | Default QR code format. Options: `'png'`, `'svg'` |
| `defaultQrErrorCorrection` | `string` | `'M'` | Error correction level for PNG and SVG. Options: `'L'` (~7%), `'M'` (~15%), `'Q'` (~25%), `'H'` (~30%). Invalid configured values safely fall back to `'M'`. |
| `defaultQrMargin` | `int` | `4` | Quiet zone (margin) in modules (0–10) |
| `qrModuleStyle` | `string` | `'square'` | Module shape. Options: `'square'`, `'rounded'`, `'dots'` |
| `qrEyeStyle` | `string` | `'square'` | Eye/finder pattern style. Options: `'square'`, `'rounded'`, `'pointed'` |
| `qrEyeColor` | `string\|null` | `null` | Eye color override (hex). `null` = match module color |
| `enableQrLogo` | `bool` | `false` | Enable logo overlay in the center of QR codes |
| `qrLogoVolumeUid` | `string\|null` | `null` | Asset volume UID allowed for QR logos. `null` = all volumes |
| `defaultQrLogoId` | `int\|null` | `null` | Default QR logo asset ID |
| `qrLogoSize` | `int` | `20` | Logo size as a percentage of the QR code (10–30) |
| `enableQrDownload` | `bool` | `true` | Allow canonical public downloads and authenticated CP exports |
| `qrDownloadFilename` | `string` | `'{code}-qr-{size}'` | Download filename pattern. Tokens: `{code}`, normalized `{size}`, and normalized `{format}` |

When a supported CP, Twig/service, ShortLink-field, or CSV creation path creates a link without an explicit QR size, the new link is initialized from the effective validated `defaultQrSize`. An explicit per-link value still wins, and changing the default does not rewrite existing saved links.

Public QR image and `/view` routes ignore styling query parameters and use saved per-link values with global fallbacks. The QR settings preview uses the selected default size, while edit-page previews are fixed at 150px. Authenticated export presets are 256, 512, 1024, and 2048px, and custom exports accept 100–4096px. Authenticated previews and exports are private/no-store and do not read or write persistent QR cache entries.

---

### Cache

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enableQrCodeCache` | `bool` | `true` | Cache canonical public and direct programmatic QR renders. Authenticated unsaved previews/exports bypass persistent caching |
| `qrCodeCacheDuration` | `int` | `86400` | QR code cache duration in seconds (default: 24 hours) |
| `cacheStorageMethod` @since(5.3.0) | `string` | `'file'` | Persistent cache selection. Options: `'file'`, `'craft'`, or the backward-compatible `'redis'` application-cache token |
| `cacheDeviceDetection` | `bool` | `true` | Cache device detection results |
| `deviceDetectionCacheDuration` | `int` | `3600` | Device detection cache duration in seconds (default: 1 hour) |

On a durable host, `'file'` stores ShortLink Manager's QR and device-detection cache families in plugin-owned files. On an ephemeral host, the same token attempts to use Craft's configured application cache when that cache is suitable for cross-request persistence.

Use `'craft'` when you want to select Craft's suitable application cache explicitly. The older `'redis'` token is retained for compatibility and makes the same application-cache selection; it does not configure Redis or guarantee that Craft's cache component is Redis-backed. If the selected application cache is not suitable for cross-request persistence, ShortLink Manager disables persistent storage for these families instead of silently switching to another backend.

Authenticated QR previews and exports bypass persistent storage regardless of the selected token.

---

### Integrations

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabledIntegrations` | `array` | `['redirect-manager']` | Enabled integration handles |
| `redirectManagerEvents` | `array` | `['slug-change']` | Redirect Manager events that trigger automatic link updates |
| `seomaticTrackingEvents` | `array` | `['redirect', 'qr_scan']` | SEOmatic event types to emit for GTM/GA tracking |
| `seomaticEventPrefix` | `string` | `'shortlink_manager'` | Event name prefix for SEOmatic/GTM events (lowercase, numbers, underscores only) |

Add `'seomatic'` to `enabledIntegrations` to activate both SEOmatic tracking and the SEOmatic Content SEO source for ShortLinks. When it is not enabled, ShortLink Manager does not register ShortLinks in SEOmatic.

---

### Interface

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `itemsPerPage` | `int` | `100` | Items per page in element indexes (10–500) |

---

### Base display and export overrides

The **Settings → Interface** screen also includes base-owned display and export controls after **Items per page**. Leave these unset to inherit from `config/lindemannrock-base.php`; set them in `config/shortlink-manager.php` only when ShortLink Manager should override the global base value.

Each of these settings resolves in three steps: a value in `config/shortlink-manager.php` wins and locks the setting (the matching CP field is disabled with an override warning); otherwise a specific value picked in the Control Panel (anything other than **Use global default**) is saved with the plugin's settings and applies; otherwise the setting cascades from `config/lindemannrock-base.php` (or the built-in default when that file doesn't set it either). In practice you'll usually just pick a value in the CP — reach for the config file only when the value should be locked per environment.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `timeFormat` | `string\|null` | `null` | Time display override: `'12'` (AM/PM) or `'24'` |
| `monthFormat` | `string\|null` | `null` | Month display override: `'numeric'`, `'short'`, or `'long'` |
| `dateOrder` | `string\|null` | `null` | Date order override: `'dmy'`, `'mdy'`, or `'ymd'` |
| `dateSeparator` | `string\|null` | `null` | Date separator override: `'/'`, `'-'`, or `'.'` |
| `showSeconds` | `bool\|null` | `null` | Whether timestamps include seconds |
| `defaultDateRange` | `string\|null` | `null` | Default date range for analytics, logs, dashboard widgets, and other date-filtered views. Values: `today`, `yesterday`, `thisWeek`, `lastWeek`, `last7days`, `last14days`, `last30days`, `last90days`, `thisMonth`, `lastMonth`, `thisQuarter`, `lastQuarter`, `thisYear`, `lastYear`, `last12months`, `all` |
| `exports` | `array\|null` | `null` | Export format overrides, e.g. `['csv' => true, 'json' => true, 'excel' => true]` |

> [!NOTE]
> `exports` is the config-file shape only. In the Control Panel these appear as three separate dropdowns (CSV, JSON, Excel) on **Settings → Interface**, stored internally as individual settings — there is no single "Exports" field.

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

        // Optional base-setting overrides for this plugin only
        // Leave unset to inherit from config/lindemannrock-base.php.
        // 'timeFormat' => '24',
        // 'monthFormat' => 'short',
        // 'dateOrder' => 'dmy',
        // 'dateSeparator' => '/',
        // 'showSeconds' => false,
        // 'defaultDateRange' => 'last7days',
        // 'exports' => [
        //     'csv' => true,
        //     'json' => true,
        //     'excel' => true,
        // ],
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
        // Explicitly use Craft's configured suitable application cache.
        'cacheStorageMethod' => 'craft',
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

The base-owned settings in [Base display and export overrides](#base-display-and-export-overrides) add one extra layer: when neither a CP value nor `config/shortlink-manager.php` sets them, they inherit from `config/lindemannrock-base.php` before falling back to the built-in default.

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
