# ShortLink Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-shortlink-manager.svg)](https://packagist.org/packages/lindemannrock/craft-shortlink-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0%2B-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.0%2B-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-shortlink-manager.svg)](LICENSE)

Advanced shortlink management with QR codes and analytics for Craft CMS.

## Features

### 🔗 Flexible Shortlink Creation
- **Auto-generated codes**: Randomly generated short codes (e.g., `/s/abc123`)
- **Vanity URLs**: Custom memorable slugs (e.g., `/s/pricing`)
- **Element linking**: Automatically create shortlinks for Craft entries, assets, etc.
- **Custom field type**: Add shortlink fields directly to your entry types

### 📊 Comprehensive Analytics
- **Device Detection** - Powered by Matomo DeviceDetector for accurate device, browser, and OS identification
- **Geographic Detection** - Track visitor location (country, city) via ip-api.com
- **Bot Filtering** - Identify and filter bot traffic (GoogleBot, BingBot, etc.)
- **Rich Dashboard** - Interactive charts for devices, browsers, OS, geographic distribution
- **Date Range Filtering** - View analytics for today, last 7/30/90 days, or all time
- **Referrer Tracking** - See where visitors are coming from
- **CSV Export** - Export comprehensive analytics including device and geo data
- **Privacy-First** - IP hashing with salt, optional subnet masking, GDPR-friendly
- **Performance Caching** - File-based caching for device detection results
- **Automatic Cleanup** - Configurable retention period (0-3650 days)

### 📱 QR Code Generation
- Automatic QR code generation for every shortlink
- Customizable colors, sizes, and styles (square, rounded, dots)
- Logo overlay support
- Multiple formats (PNG, SVG)
- Downloadable QR codes
- File-based caching for performance

### ⚙️ Advanced Features
- Link expiration with custom redirect URLs
- Configurable HTTP status codes (301, 302, 307, 308)
- Reserved codes protection
- IP anonymization for GDPR compliance
- Multi-site support
- Database-backed settings
- Config file overrides
- Comprehensive logging

### 🔄 Redirect Manager Integration (Optional)
- **Automatic redirect creation** when shortlink slugs change
- **Centralized redirect management** - view all redirects in one place
- **Analytics tracking** - track views of changed, expired, or deleted shortlinks
- **Smart undo detection** - prevents flip-flop redirects within configurable time window (30-240 minutes)
- **Loop prevention** - automatically detects and prevents circular redirects
- **Persistent redirects** - redirects persist even after shortlink deletion
- **User notifications** - shows "Redirect created" or "Slug change undone" messages

**Requires:** [LindemannRock Redirect Manager](https://github.com/LindemannRock/craft-redirect-manager) plugin (optional)

When enabled, the plugin automatically creates permanent redirects in Redirect Manager when:
- **Slug changes:** `/s/summer-sale` → `/s/fall-sale` (creates redirect)
- **Slug undo:** `/s/test` → `/s/test2` → `/s/test` (deletes redirect if within undo window)
- **Link expires:** Redirects to its Expired Redirect URL (if set)
- **Link deleted:** Redirects to fallback URL (only if link has traffic)

Configure in: **ShortLink Manager → Settings → Integrations**

The undo window is configured in **Redirect Manager → Settings → General → Undo Window** (applies to all plugins)

### 📊 SEOmatic Integration (Optional)

When [SEOmatic](https://plugins.craftcms.com/seomatic) is installed, Shortlink Manager can push click events to Google Tag Manager's data layer for tracking in GTM and Google Analytics.

**Setup:**
1. Install and configure SEOmatic plugin with GTM or Google Analytics
2. Navigate to **Settings → Shortlink Manager → Integrations**
3. Enable **SEOmatic Integration**
4. Select which events to track (redirects, QR scans)
5. Customize the event prefix if needed (default: `shortlink_manager`)
6. Save settings

**GTM Event Structure:**

Events are pushed to `window.dataLayer` with the following structure:

```javascript
{
  event: "shortlink_manager_redirect",
  shortlink: {
    code: "ABC123",
    title: "My Shortlink",
    destination_url: "https://example.com",
    source: "qr",                 // qr or direct
    device_type: "mobile",        // mobile, tablet, desktop
    os: "iOS 17",
    browser: "Safari",
    country: "United States",
    city: "New York"
  }
}
```

**Event Types:**
- `shortlink_manager_redirect` - Standard shortlink redirects
- `shortlink_manager_qr_scan` - QR code scans (accessed via `?src=qr` parameter)

**GTM Trigger Setup:**

Create triggers in Google Tag Manager to listen for these events:

1. **Trigger Type**: Custom Event
2. **Event Name**: `shortlink_manager_redirect` (or your custom prefix)
3. **Use regex matching** to catch all Shortlink Manager events: `shortlink_manager_.*`

**GA4 Event Example:**

Forward Shortlink Manager events to Google Analytics 4:

```
Event Name: shortlink_click
Parameters:
  - link_code: {{shortlink.code}}
  - link_source: {{shortlink.source}}
  - device_type: {{shortlink.device_type}}
```

**Configuration via Config File:**

```php
// config/shortlink-manager.php
return [
    'enabledIntegrations' => ['seomatic'],
    'seomaticTrackingEvents' => ['redirect', 'qr_scan'],
    'seomaticEventPrefix' => 'shortlink_manager',
];
```

**Important Notes:**
- Events are only sent when analytics tracking is enabled (globally and per-link)
- Requires SEOmatic plugin to be installed and enabled
- GTM or Google Analytics must be configured in SEOmatic
- Events include all analytics data Shortlink Manager already tracks
- No additional external API calls or performance impact
- Template-based redirect adds ~100ms delay for tracking (imperceptible to users)

## Requirements

- PHP 8.2+
- Craft CMS 5.0+
- LindemannRock Logging Library ^5.0 (installed automatically)
- bacon/bacon-qr-code ^2.0 (installed automatically)
- matomo/device-detector ^6.4 (installed automatically)

## Installation

### Via Composer

```bash
cd /path/to/project
```

```bash
composer require lindemannrock/craft-shortlink-manager
```

```bash
./craft plugin/install shortlink-manager
```

### Using DDEV

```bash
cd /path/to/project
```

```bash
ddev composer require lindemannrock/craft-shortlink-manager
```

```bash
ddev craft plugin/install shortlink-manager
```

### Via Control Panel

In the Control Panel, go to Settings → Plugins and click "Install" for ShortLink Manager.

### ⚠️ Required Post-Install Step

**IMPORTANT:** After installation, you MUST generate the IP hash salt for analytics to work:

```bash
php craft shortlink-manager/security/generate-salt
```

**What happens if you skip this:**
- ❌ Analytics tracking will fail with error: `IP hash salt not configured`
- ❌ Shortlinks will still redirect, but won't track clicks
- ✅ You can generate the salt later, but no analytics will be collected until you do

**Quick Start:**
```bash
# After plugin installation:
php craft shortlink-manager/security/generate-salt

# The command will automatically add SHORTLINK_MANAGER_IP_SALT to your .env file
# Copy this value to staging/production .env files manually
```

### Optional: Copy Config File

```bash
cp vendor/lindemannrock/craft-shortlink-manager/src/config.php config/shortlink-manager.php
```

### Important: IP Privacy Protection

ShortLink Manager uses **privacy-focused IP hashing** with a secure salt:

- ✅ **Rainbow-table proof** - Salted SHA256 prevents pre-computed attacks
- ✅ **Unique visitor tracking** - Same IP = same hash
- ✅ **Geo-location preserved** - Country/city extracted BEFORE hashing
- ✅ **Maximum privacy** - Original IPs never stored, unrecoverable

**Setup Instructions:**
1. Generate salt: `php craft shortlink-manager/security/generate-salt`
2. Command automatically adds `SHORTLINK_MANAGER_IP_SALT` to your `.env` file
3. **Manually copy** the salt value to staging/production `.env` files
4. **Never regenerate** the salt in production

**How It Works:**
- Plugin automatically reads salt from `.env` (no config file needed!)
- Config file can override if needed: `'ipHashSalt' => App::env('SHORTLINK_MANAGER_IP_SALT')`
- If no salt found, error banner shown in settings

**Security Notes:**
- Never commit the salt to version control
- Store salt securely (password manager recommended)
- Use the SAME salt across all environments (dev, staging, production)
- Changing the salt will break unique visitor tracking history

### Local Development: Analytics Location Override

When running locally (DDEV, localhost), analytics will **default to Dubai, UAE** because local IPs can't be geolocated. To set your actual location for testing:

```bash
# Add to your .env file:
SHORTLINK_MANAGER_DEFAULT_COUNTRY=US
SHORTLINK_MANAGER_DEFAULT_CITY=New York
```

**Supported locations:**
- **US**: New York, Los Angeles, Chicago, San Francisco
- **GB**: London, Manchester
- **AE**: Dubai, Abu Dhabi (default: Dubai)
- **SA**: Riyadh, Jeddah
- **DE**: Berlin, Munich
- **FR**: Paris
- **CA**: Toronto, Vancouver
- **AU**: Sydney, Melbourne
- **JP**: Tokyo
- **SG**: Singapore
- **IN**: Mumbai, Delhi

**Note:** This only affects local/private IPs (127.0.0.1, localhost, etc.). Production analytics will use real IP geolocation via ip-api.com.

## Multi-Site Management

ShortLink Manager supports restricting functionality to specific sites in multi-site installations.

### Site Selection

Configure which sites ShortLink Manager should be enabled for:

**Via Control Panel:**
- Go to **Settings → Plugins → ShortLink Manager → General**
- Under "Site Settings", check the sites where ShortLink Manager should be available
- Leave empty to enable for all sites

**Via Configuration File:**
```php
// config/shortlink-manager.php
return [
    'enabledSites' => [1, 2], // Only enable for sites 1 and 2

    // Environment-specific overrides
    'dev' => [
        'enabledSites' => [1], // Only main site in development
    ],
    'production' => [
        'enabledSites' => [1, 2, 3], // All sites in production
    ],
];
```

**Behavior:**
- **CP Navigation**: ShortLink Manager only appears in sidebar for enabled sites
- **Site Switcher**: Only enabled sites appear in the site dropdown
- **Access Control**: Direct access to disabled sites returns 403 Forbidden
- **Backwards Compatibility**: Empty selection enables all sites

**Important Notes:**
- If the primary site is not included in `enabledSites`, ShortLink Manager will not appear in the main CP navigation at all, as the navigation uses the primary site context. Ensure you include your primary site ID if you want ShortLink Manager accessible from the main menu.
- You can still access ShortLink Manager on enabled non-primary sites via direct URLs, but the main navigation will be hidden.

## Usage

### Creating Shortlinks via Control Panel

1. Navigate to **ShortLink Manager** → **Links**
2. Click **New shortlink**
3. Choose between auto-generated code or custom vanity URL
4. Enter destination URL
5. Configure options (HTTP code, expiration, QR codes, analytics)
6. Save

### Creating Shortlinks via Twig

**Get or create for an element:**
```twig
{% set link = craft.shortLinkManager.get({ element: entry }) %}
{{ link.getUrl() }}
```

**Create a custom vanity URL:**
```twig
{% set link = craft.shortLinkManager.create({
  code: 'pricing',
  url: 'https://example.com/pricing',
  type: 'vanity',
  httpCode: 301
}) %}
```

**QR Code Methods:**
```twig
{# 1. Get QR code URL - Returns URL string (most efficient for templates) #}
link.getQrCodeUrl()
link.getQrCodeUrl({size: 500, color: 'FF0000', bg: '00FF00'})

{# 2. Get QR code as data URI - Returns base64 data URI (for inline/email) #}
link.getQrCodeDataUri()
link.getQrCodeDataUri({size: 300})

{# 3. Get QR code binary data - Returns PNG/SVG bytes (for downloads/API) #}
link.getQrCode()
link.getQrCode({format: 'svg'})

{# Get QR code display page URL #}
link.getQrCodeDisplayUrl()

{# Render SEOmatic tracking script (returns Twig\Markup or null) #}
link.renderSeomaticTracking('qr_scan')
{# Event types: 'direct_click' for redirect pages, 'qr_scan' for QR code pages #}
```

**QR Code Method Usage:**
- Use **getQrCodeUrl()** for regular templates (browser fetches image via URL)
- Use **getQrCodeDataUri()** for emails or when you need inline base64 data
- Use **getQrCode()** when you need raw binary data (downloads, API responses, file saving)

**QR Code Options:**
- `size`: Image size in pixels (100-4096)
- `color`: Foreground hex color (without #)
- `bg`: Background hex color (without #)
- `format`: 'png' or 'svg'
- `margin`: Quiet zone around QR code (0-10)
- `eyeColor`: Custom color for position markers
- `logo`: Asset ID for logo overlay

All options are optional and fall back to the shortlink's settings or global defaults.

**Frontend QR Code URLs:**

Two URL patterns for QR codes:

1. **QR Code Image** (direct PNG/SVG):
   ```
   /s/qr/{code}
   Example: /s/qr/abc123
   ```
   Returns just the image (for embedding, emails, APIs)

2. **QR Code Page** (branded template):
   ```
   /s/qr/{code}/view
   Example: /s/qr/abc123/view
   ```
   Returns full HTML page with SEO, branding, and tracking

The QR prefix (`s/qr` in examples above) is configurable in Settings → General → QR Code URL Prefix. Supports both standalone (`qr`) and nested patterns (`s/qr`).

**Check expiration:**
```twig
{% if link.isExpired() %}
  <p>This link has expired!</p>
{% endif %}
```

### Using the ShortLink Field

1. Create a new field of type "Short Link"
2. Add it to your entry type
3. Configure field settings (link type, QR codes, expiration, etc.)
4. Editors can now add shortlinks directly in entries

## Configuration

### Config File

Create a `config/shortlink-manager.php` file to override default settings:

```bash
cp vendor/lindemannrock/craft-shortlink-manager/src/config.php config/shortlink-manager.php
```

Example configuration:

```php
return [
    'pluginName' => 'ShortLink Manager',

    // Site settings
    'enabledSites' => [],  // Array of site IDs (empty = all sites)

    // URL settings
    'slugPrefix' => 's',
    'qrPrefix' => 's/qr',  // QR code URL prefix (standalone or nested like 's/qr')
    'codeLength' => 8,

    // QR Code settings
    'defaultQrSize' => 256,
    'defaultQrColor' => '#000000',
    'defaultQrBgColor' => '#FFFFFF',
    'enableQrCodeCache' => true,
    'qrCodeCacheDuration' => 86400,  // 24 hours

    // Template settings
    'redirectTemplate' => null,  // e.g., 'shortlink-manager/redirect'
    'expiredTemplate' => null,   // e.g., 'shortlink-manager/expired'
    'qrTemplate' => null,        // e.g., 'shortlink-manager/qr'

    // Analytics settings
    'enableAnalytics' => true,
    'analyticsRetention' => 90, // days
    'enableGeoDetection' => false,  // Track visitor location
    'anonymizeIpAddress' => false,  // Subnet masking for privacy
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 3600,  // 1 hour

    // Redirect settings
    'defaultHttpCode' => 301,
    'notFoundRedirectUrl' => '/',

    // Integrations (optional)
    'enabledIntegrations' => ['seomatic', 'redirect-manager'],  // Enable integrations

    // SEOmatic Integration
    'seomaticTrackingEvents' => ['redirect', 'qr_scan'],  // Event types to track
    'seomaticEventPrefix' => 'shortlink_manager',  // GTM event prefix

    // Redirect Manager Integration
    'redirectManagerEvents' => ['slug-change', 'expire', 'delete'],  // Which events create redirects
    // Available events: 'slug-change', 'expire', 'delete'
    // Examples:
    // ['slug-change'] - Only slug changes create redirects
    // ['slug-change', 'delete'] - Slug changes and deletions
    // [] - Integration enabled but no redirects created

    // Display
    'itemsPerPage' => 50,
];
```

See [Configuration Documentation](docs/CONFIGURATION.md) for all available options.

### Custom Template Paths

ShortLink Manager uses templates for redirect pages, expired links, and QR code display pages.

#### Default Template Paths

When template settings are not configured (set to `null`), the plugin uses its built-in templates:
- **Redirect page:** `plugins/shortlink-manager/src/templates/redirect.twig`
- **Expired page:** `plugins/shortlink-manager/src/templates/expired.twig`
- **QR code page:** `plugins/shortlink-manager/src/templates/qr.twig`

#### Quick Start: Copy Example Templates

To customize templates, copy them to your project's `templates/` directory:

```bash
# Create templates directory
mkdir -p templates/shortlink-manager

# Copy example templates
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/redirect.twig templates/shortlink-manager/
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/expired.twig templates/shortlink-manager/
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/qr.twig templates/shortlink-manager/

# Customize the templates to match your site's design
```

#### Configure Custom Template Paths

**Via Config File:**
```php
// config/shortlink-manager.php
return [
    'redirectTemplate' => 'my-custom/redirect',
    'expiredTemplate' => 'my-custom/expired',
    'qrTemplate' => 'my-custom/qr-display',
];
```

**Via Control Panel:**
- Settings → General → Template Settings

### Integration Settings

**Enabled Integrations** (`enabledIntegrations`):
- Array of plugin handles to integrate with
- Current options: `['seomatic', 'redirect-manager']`
- Leave empty `[]` to disable all integrations

**SEOmatic Integration Settings:**

**SEOmatic Tracking Events** (`seomaticTrackingEvents`):
- Array of event types to track
- `'redirect'` - Standard shortlink redirects
- `'qr_scan'` - QR code scans (via `?src=qr` parameter)
- Example: `['redirect', 'qr_scan']` - Track both event types

**SEOmatic Event Prefix** (`seomaticEventPrefix`):
- String prefix for GTM event names (lowercase, numbers, underscores only)
- Default: `'shortlink_manager'`
- Creates events like `shortlink_manager_redirect`, `shortlink_manager_qr_scan`
- Example: `'my_brand'` creates `my_brand_redirect`, `my_brand_qr_scan`

**Redirect Manager Integration Settings:**

**Redirect Manager Events** (`redirectManagerEvents`):
- Array of events that trigger redirect creation
- `'slug-change'` - Creates redirect when shortlink slug changes
- `'expire'` - Creates redirect when shortlink expires (requires expiredRedirectUrl)
- `'delete'` - Creates redirect when shortlink deleted with traffic (hits > 0)
- Example: `['slug-change', 'delete']` - Only slug changes and deletions create redirects

### Environment-Specific Configuration

```php
return [
    '*' => [
        'enableAnalytics' => true,
    ],
    'dev' => [
        'analyticsRetention' => 30,
        'cacheDeviceDetection' => false,  // Disable cache in dev
    ],
    'production' => [
        'analyticsRetention' => 365,
        'enableGeoDetection' => true,
        'cacheDeviceDetection' => true,
    ],
];
```

## Permissions

- **View shortlinks**: Can view shortlinks in CP
- **Create shortlinks**: Can create new shortlinks
- **Edit shortlinks**: Can edit existing shortlinks
- **Delete shortlinks**: Can delete shortlinks
- **View analytics**: Can view analytics dashboard
- **Export analytics**: Can export analytics data
- **View logs**: Can view plugin logs
- **Manage settings**: Can change plugin settings

## API Reference

### ShortLink Model

**Properties:**
- `id` - ShortLink ID
- `code` - The short code
- `destinationUrl` - Target URL
- `enabled` - Active status
- `expiresAt` - Expiration date/time
- `hits` - Click count

**Methods:**
- `getUrl()` - Get full shortlink URL
- `getQrCode(options)` - Get QR code binary data
- `getQrCodeDataUri(options)` - Get QR code as data URI
- `isExpired()` - Check if expired
- `getElement()` - Get linked element
- `getAnalytics(filters)` - Get analytics data

### Twig Variable

```twig
{# Get shortlink #}
craft.shortLinkManager.get({ code: 'abc123' })
craft.shortLinkManager.get({ id: 5 })
craft.shortLinkManager.get({ element: entry })

{# Create shortlink #}
craft.shortLinkManager.create({ url: '...', type: 'code' })

{# Get analytics #}
craft.shortLinkManager.getAnalytics(linkId, { days: 30 })

{# Get all shortlinks #}
craft.shortLinkManager.getAll({ enabled: true, limit: 10 })
```

## Events

The plugin triggers several events you can listen to:

```php
use lindemannrock\shortlinkmanager\ShortLinkManager;

Event::on(
    ShortLinkManager::class,
    ShortLinkManager::EVENT_BEFORE_SAVE_SHORTLINK,
    function(ShortLinkEvent $event) {
        // Modify $event->shortLink before save
    }
);
```

## Logging

ShortLink Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized logging.

### Log Levels
- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

### Configuration
```php
// config/shortlink-manager.php
return [
    'logLevel' => 'error', // error, warning, info, or debug
];
```

**Note:** Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

### Log Files
- **Location**: `storage/logs/shortlink-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at ShortLink Manager → Logs

### Log Management
Access logs through the Control Panel:
1. Navigate to ShortLink Manager → Logs
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

**Requires:** `lindemannrock/craft-logging-library` plugin (installed automatically as dependency)

See [docs/LOGGING.md](docs/LOGGING.md) for detailed logging documentation.

## Support

- **Documentation**: [https://github.com/LindemannRock/craft-shortlink-manager](https://github.com/LindemannRock/craft-shortlink-manager)
- **Issues**: [https://github.com/LindemannRock/craft-shortlink-manager/issues](https://github.com/LindemannRock/craft-shortlink-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Credits

Created by [LindemannRock](https://lindemannrock.com)
