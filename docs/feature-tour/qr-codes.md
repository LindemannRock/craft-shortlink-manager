# QR Codes

Every short link in ShortLink Manager can have a QR code. Each QR code encodes the short link's public URL, so if you update the destination, the QR code continues working without reprinting. Scanning triggers the same redirect flow as clicking — including analytics tracking.

> [!TIP]
> QR scans follow the same redirect mode as normal clicks. If `directRedirect` is enabled, repeat scan analytics can be bypassed by browser/CDN/static caching unless those routes bypass cache. If you need analytics-safe QR scans under caching, keep `directRedirect = false`.

## How It Works

QR codes are generated dynamically by the `bacon/bacon-qr-code` library and cached to avoid regenerating on every request. Each QR code encodes the short link's public URL (e.g., `https://example.com/s/qr/abc123`).

When QR code generation is enabled on a short link, two endpoints become available:

| URL | Returns |
|-----|---------|
| `/{qrPrefix}/{code}` | Raw QR code image (PNG or SVG) |
| `/{qrPrefix}/{code}/view` | Display page with title, image, and download button |

## Enabling QR Codes Per Link

On the short link edit page, toggle **QR Code Enabled** to activate QR endpoints for that link. When disabled, both endpoints return a 404.

## Customization Options

QR code appearance is set globally in **Settings → QR Codes** and can be overridden per link on the short link edit page. Per-link values left at `null` inherit from the global defaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `defaultQrSize` | `int` | `256` | Output size in pixels (50–2000) |
| `defaultQrColor` | `string` | `'#000000'` | Module foreground color (hex) |
| `defaultQrBgColor` | `string` | `'#FFFFFF'` | Background color (hex) |
| `defaultQrFormat` | `string` | `'png'` | Output format: `'png'` or `'svg'` |
| `defaultQrMargin` | `int` | `4` | Quiet zone in modules (0–50) |
| `defaultQrErrorCorrection` | `string` | `'M'` | Error correction: `'L'` (7%), `'M'` (15%), `'Q'` (25%), `'H'` (30%) |
| `qrModuleStyle` | `string` | `'square'` | Module shape: `'square'`, `'dots'`, `'rounded'` |
| `qrEyeStyle` | `string` | `'square'` | Finder pattern shape: `'square'`, `'rounded'`, `'leaf'` |
| `qrEyeColor` | `?string` | `null` | Eye color override (hex). Falls back to foreground color |

### Module Styles

| Value | Appearance |
|-------|-----------|
| `square` | Classic square modules (default) |
| `dots` | Circular dots |
| `rounded` | Rounded square corners |

### Eye Styles

| Value | Appearance |
|-------|-----------|
| `square` | Classic square finder pattern (default) |
| `rounded` | Rounded finder pattern |
| `leaf` | Leaf-shaped finder pattern |

## Logo Overlay

Enable `enableQrLogo` in settings to add a brand logo to the center of QR codes. When enabled:

- Set a **default logo** (a Craft asset) applied to all QR codes unless overridden per link
- Optionally restrict which asset volume logos can come from (`qrLogoVolumeUid`)
- Control the **logo size** as a percentage of the QR code width (5–50%, default 20%)

Per-link logo overrides use the `qrLogoId` field on the short link edit page.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enableQrLogo` | `bool` | `false` | Enable logo overlay |
| `qrLogoVolumeUid` | `?string` | `null` | Restrict logo selection to this asset volume |
| `defaultQrLogoId` | `?int` | `null` | Default logo asset ID |
| `qrLogoSize` | `int` | `20` | Logo size as percentage of QR code (5–50) |

> [!WARNING]
> A logo reduces the scannable area. Use `defaultQrErrorCorrection: 'H'` (30% recovery) when adding a logo to ensure reliable scanning.

## QR Code URLs

QR codes are available at two URL patterns:

| URL | Description |
|-----|-------------|
| `/{qrPrefix}/{code}` | Raw QR image (PNG or SVG) returned directly |
| `/{qrPrefix}/{code}/view` | Display page showing the QR code with title and download |

With a common configuration (`qrPrefix` = `s/qr`):

- Image: `https://example.com/s/qr/abc123`
- Display page: `https://example.com/s/qr/abc123/view`

The QR image URL accepts query parameters to customize on the fly:

| Parameter | Example | Description |
|-----------|---------|-------------|
| `size` | `?size=512` | QR code size in pixels |
| `color` | `?color=ff0000` | Foreground color (hex without `#`) |
| `bg` | `?bg=ffffff` | Background color (hex without `#`) |
| `format` | `?format=svg` | Output format |
| `margin` | `?margin=2` | Quiet zone modules |
| `moduleStyle` | `?moduleStyle=dots` | Module shape |
| `eyeStyle` | `?eyeStyle=rounded` | Eye shape |
| `eyeColor` | `?eyeColor=0000ff` | Eye color override |
| `download` | `?download=1` | Trigger file download instead of inline display |

## Downloading QR Codes

When `enableQrDownload` is `true` (default), QR codes can be downloaded. The download filename follows the `qrDownloadFilename` pattern with these tokens:

| Token | Replaced with |
|-------|--------------|
| `{code}` | The short link's code |
| `{slug}` | The short link's slug |
| `{size}` | The QR code size in pixels |
| `{format}` | The format: `png` or `svg` |

Default pattern: `{code}-qr-{size}` produces filenames like `abc123-qr-256.png`.

## In Templates

The `ShortLink` element provides methods for embedding QR codes in Twig templates:

```twig
{% set link = craft.shortLinkManager.get({element: entry}) %}

{% if link %}
    {# Inline data URI — embed directly in <img> #}
    <img src="{{ link.qrCodeDataUri }}" alt="QR Code" width="256" height="256">

    {# URL to the raw QR image #}
    <img src="{{ link.qrCodeUrl }}" alt="QR Code">

    {# Link to the QR display page #}
    <a href="{{ link.qrCodeDisplayUrl }}">View QR Code</a>

    {# Custom options via method call #}
    <img src="{{ link.getQrCodeUrl({size: 512, format: 'svg'}) }}" alt="QR Code">

    {# Base64 data URI for email templates #}
    <img src="{{ link.getQrCodeDataUri({size: 150}) }}" alt="QR Code">
{% endif %}
```

| Method | Returns | Description |
|--------|---------|-------------|
| `getQrCodeUrl(options)` @since(5.0.0) | `string` | URL to the raw QR image — most efficient for web use |
| `getQrCodeDataUri(options)` @since(5.0.0) | `string` | Base64 `data:image/...` URI — use for email or inline embedding |
| `getQrCode(options)` @since(5.0.0) | `string` | Raw binary image data (for programmatic use) |
| `getQrCodeDisplayUrl(options)` @since(5.1.0) | `string` | URL to the `/view` display page |

All methods accept an `options` array with any of the customization options listed above. When called without arguments (or as properties like `link.qrCodeUrl`), global defaults are used.

## The Display Page

The `/{qrPrefix}/{code}/view` endpoint renders a styled page containing the QR code with context. A custom template can be set via the `qrTemplate` setting.

The following variables are available in the display template:

| Variable | Type | Description |
|----------|------|-------------|
| `shortLink` | `ShortLink` | The short link element |
| `siteName` | `string` | The current site's name |
| `currentSite` | `Site` | The current Craft site object |

## Caching

Generated QR codes are cached to avoid regenerating on every request.

| Setting | Default | Description |
|---------|---------|-------------|
| `enableQrCodeCache` | `true` | Enable QR code caching |
| `qrCodeCacheDuration` | `86400` | Cache TTL in seconds (24 hours) |
| `cacheStorageMethod` @since(5.3.0) | `'file'` | `'file'` (single server) or `'redis'` (multi-server) |

The cache key includes the URL and all rendering options, so changing any option automatically generates a fresh QR code. Cache can be cleared from **Utilities → ShortLink Manager** (requires `shortLinkManager:clearCache` permission).

```php
// config/shortlink-manager.php
return [
    'enableQrCodeCache'   => true,
    'qrCodeCacheDuration' => 86400,
    'cacheStorageMethod'  => 'file',
];
```

## Global vs Per-Link Settings

Global QR defaults are set in **Settings → QR Codes**. Per-link overrides are set on the short link edit page. Any per-link option left `null` inherits from the global setting.

```php
// config/shortlink-manager.php
return [
    'defaultQrSize'             => 400,
    'defaultQrColor'            => '#1a1a2e',
    'defaultQrBgColor'          => '#FFFFFF',
    'defaultQrFormat'           => 'png',
    'qrModuleStyle'             => 'rounded',
    'qrEyeStyle'               => 'rounded',
    'qrEyeColor'               => null,
    'defaultQrMargin'           => 2,
    'defaultQrErrorCorrection'  => 'H',
];
```

## Limitations

- Logo overlays require the Imagick PHP extension
- SVG output does not support logo overlays (logos are PNG only)
- The `dots` module style may not scan reliably at very small sizes — use at least 200px
- QR codes always encode the short link's public URL — the destination URL cannot be encoded directly
