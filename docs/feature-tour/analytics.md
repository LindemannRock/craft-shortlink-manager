# Analytics

ShortLink Manager tracks every click on your short links with device detection, geolocation, and referrer data — no external analytics service required.

## What Gets Tracked

Every click records:

- **Short link** (which link was clicked)
- **Timestamp** (exact date and time)
- **Device type** — Desktop, Tablet, or Mobile, detected from the user-agent string
- **OS / Browser** — Parsed from the user-agent
- **Referrer** — The page the visitor came from (if the browser sends it)
- **Country and City** — From IP geolocation (if `enableGeoDetection` is enabled)
- **IP Hash** — A privacy-preserving one-way hash of the visitor's IP for unique visitor counting. Requires `ipHashSalt` to be set.

> [!NOTE]
> Clicks where `trackAnalytics` is `false` on the short link are not recorded. QR code scans are tracked the same as regular clicks (since the QR code redirects through the same short URL).

## Enabling Analytics

Analytics is enabled by default (`enableAnalytics = true`). Disable it globally if you don't need tracking:

```php
// config/shortlink-manager.php
return [
    '*' => [
        'enableAnalytics' => false,
    ],
];
```

Individual links can have tracking disabled via the **Track Analytics** toggle on the link edit screen.

## Analytics Dashboard

Go to **ShortLink Manager → Analytics** to see the analytics dashboard. The dashboard shows:

- **Total Clicks** — All-time and for the selected date range
- **Unique Visitors** — Based on IP hashing (requires `ipHashSalt`)
- **Clicks Over Time** — Time-series chart
- **Top Links** — Most-clicked links in the date range
- **Device Breakdown** — Desktop / Tablet / Mobile split
- **Top Countries** — Requires geolocation
- **Top Referrers** — Most common referring pages

Date range options: Last 7 days, Last 30 days, Last 90 days, This year, Custom range.

### Exporting Analytics @since(5.5.0)

Analytics can be exported to CSV or JSON from the export button (requires `shortLinkManager:exportAnalytics` permission).

## Unique Visitor Tracking

ShortLink Manager counts unique visitors by hashing each visitor's IP address with a secret salt. This lets you see how many different people clicked a link, without storing raw IP addresses.

**To enable unique visitor tracking:**

1. Generate a salt:

```bash title="PHP"
php craft shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/security/generate-salt
```

2. This writes `SHORTLINK_MANAGER_IP_SALT` to your `.env` file.

> [!IMPORTANT]
> Use the same salt across all environments (dev, staging, production). Changing the salt resets unique visitor counts — existing click records retain their old hashes.

## IP Anonymization

For stricter privacy compliance, enable subnet masking before hashing:

```php
return [
    '*' => [
        'anonymizeIpAddress' => true, // e.g. 192.168.1.123 → 192.168.1.0
    ],
];
```

This replaces the last octet of IPv4 addresses with `0` before hashing. The resulting hash is still consistent across clicks from the same subnet, so unique visitor counting still works at the subnet level.

## Geolocation

Geolocation maps IP addresses to countries and cities. It runs asynchronously as a queue job so it does not slow down the redirect response.

```php
return [
    '*' => [
        'enableGeoDetection' => true,
        'geoProvider' => 'ip-api.com', // or 'ipapi.co', 'ipinfo.io'
        'geoApiKey' => \craft\helpers\App::env('SHORTLINK_MANAGER_GEO_API_KEY'), // for paid tiers
    ],
];
```

**Available providers:**

| Provider | Free Tier | HTTPS |
|----------|-----------|-------|
| `ip-api.com` | Yes (45 req/min) | Paid only |
| `ipapi.co` | Yes (1,000 req/day) | Paid only |
| `ipinfo.io` | Yes (50,000 req/month) | Yes |

> [!TIP]
> For local development, private IP addresses (127.0.0.1, 192.168.x.x) cannot be geolocated. Set defaults:
> ```php
> 'defaultCountry' => 'US',
> 'defaultCity' => 'New York',
> ```
> Or use the `SHORTLINK_MANAGER_DEFAULT_COUNTRY` and `SHORTLINK_MANAGER_DEFAULT_CITY` env vars.

## Data Retention

Analytics data is automatically cleaned up after the configured retention period:

```php
return [
    '*' => [
        'analyticsRetention' => 90, // days, 0 = keep forever
    ],
];
```

The cleanup runs as a self-rescheduling queue job every 24 hours. It schedules itself when the plugin initializes (if no existing cleanup job is queued).

You can manually trigger cleanup from **ShortLink Manager → Settings → Cleanup Analytics** in the CP, or clear all analytics data from **Utilities → ShortLink Manager** (requires `shortLinkManager:clearAnalytics` permission).

## Device Detection Cache

Parsing user-agent strings is CPU-intensive. ShortLink Manager caches parsed device detection results:

```php
return [
    '*' => [
        'cacheDeviceDetection' => true,
        'deviceDetectionCacheDuration' => 3600, // 1 hour
    ],
];
```

In development, you may want to disable this cache to avoid stale results during testing:

```php
'dev' => [
    'cacheDeviceDetection' => false,
],
```

## Dashboard Widgets

Two Craft dashboard widgets provide at-a-glance analytics. Add them via **Dashboard → New Widget**.

**ShortLink Manager - Analytics**: Shows click totals, unique visitors, top links, and device breakdown for a configurable date range (default: last 7 days).

**ShortLink Manager - Top Links**: Shows the most-clicked links for a configurable date range, with configurable limit (1–20 links, default 5).

Both widgets require `shortLinkManager:viewAnalytics` permission.

## Analytics in Twig

Access analytics for a specific link from Twig:

```twig
{% set link = craft.shortLinkManager.get({element: entry}) %}

{% if link %}
    {# Get click stats via the template variable #}
    {% set stats = craft.shortLinkManager.getAnalytics(link.id) %}

    {# Or directly from the link element #}
    {% set stats = link.getAnalytics() %}

    <p>Total clicks: {{ link.hits }}</p>
{% endif %}
```

The `getAnalytics()` method accepts a `filters` array for date filtering. See [Template Variables](../developers/template-variables.md) for details.
