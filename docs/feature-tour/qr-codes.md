# QR Codes

Every short link in ShortLink Manager automatically gets a QR code. QR codes point to the short link URL — so if you update the destination, the QR code continues working without reprinting.

## How It Works

QR codes are generated dynamically by the `bacon/bacon-qr-code` library. Generated codes are cached to avoid regenerating on every request. The cache can be file-based or Redis-based depending on your `cacheStorageMethod` setting.

Each QR code encodes the short link's public URL (e.g., `https://short.example.com/s/abc123`). Scanning the QR code triggers the same redirect flow as clicking the short link — including analytics tracking.

## Default Appearance

Defaults are set globally in **ShortLink Manager → Settings → QR Codes** and can be overridden per link.

| Setting | Default | Description |
|---------|---------|-------------|
| Size | 256px | Width and height of the generated image (100–1000px) |
| Format | PNG | `png` or `svg` |
| Foreground color | `#000000` | Module color |
| Background color | `#FFFFFF` | Background color |
| Error correction | M (~15%) | `L`, `M`, `Q`, or `H` |
| Margin | 4 modules | Quiet zone around the code (0–10) |
| Module style | Square | Shape of the data modules: `square`, `rounded`, `dots` |
| Eye style | Square | Shape of the finder pattern eyes: `square`, `rounded`, `leaf` |
| Eye color | (matches modules) | Override the finder eye color separately |

## Per-Link Overrides

Each short link can override the default size, foreground color, background color, eye color, and format. If a per-link value matches the global default, it is not stored separately (the global default is used).

Overrides are set in the QR Code tab on the short link edit screen.

## Logo Overlay

Enable `enableQrLogo` in settings to allow a logo image in the center of QR codes. When enabled:

- Set a **default logo** (an asset from your asset volumes) — applied to all QR codes unless overridden per link
- Optionally restrict which asset volume logos can come from (`qrLogoVolumeUid`)
- Control the **logo size** as a percentage of the QR code (10–30%, default 20%)

> [!NOTE]
> Logo overlays reduce the effective data capacity of the QR code. Use error correction level `H` (~30%) when using logos to ensure reliable scanning, especially with complex logos.

Per-link logo overrides use the `qrLogoId` field on the ShortLink element.

## Downloading QR Codes

When `enableQrDownload` is `true` (default), a download button appears in the CP. The download filename follows the `qrDownloadFilename` pattern with these tokens:

| Token | Replaced with |
|-------|--------------|
| `{code}` | The short link's code/slug |
| `{size}` | The QR code size in pixels |
| `{format}` | The format: `png` or `svg` |

Default pattern: `{code}-qr-{size}` → produces filenames like `abc123-qr-256.png`.

## QR Code URLs

QR codes are available at two URL patterns:

| URL | Description |
|-----|-------------|
| `/{qrPrefix}/{code}` | Raw QR image (PNG or SVG) returned directly |
| `/{qrPrefix}/{code}/view` | Frontend template page showing the QR code |

With default settings (`qrPrefix = 's/qr'`):
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

## In Templates

The `ShortLink` element provides methods for embedding QR codes in your Twig templates:

```twig
{# Get a shortlink #}
{% set link = craft.shortLinkManager.get({element: entry}) %}

{% if link %}
    {# Inline data URI — embed directly in <img> #}
    <img src="{{ link.qrCodeDataUri }}" alt="QR Code" width="256" height="256">

    {# URL to the raw QR image #}
    <img src="{{ link.qrCodeUrl }}" alt="QR Code">

    {# URL to the QR display page (the view template) #}
    <a href="{{ link.qrCodeDisplayUrl }}">View QR Code</a>

    {# Custom size via options #}
    <img src="{{ link.getQrCodeUrl({size: 512, format: 'svg'}) }}" alt="QR Code">
{% endif %}
```

| Method | Returns | Description |
|--------|---------|-------------|
| `getQrCodeDataUri(options)` | `string` | Base64 data URI for inline embedding |
| `getQrCodeUrl(options)` | `string` | URL to the raw QR image |
| `getQrCodeDisplayUrl(options)` @since(5.1.0) | `string` | URL to the QR display page |

## Caching

Generated QR codes are cached to avoid regenerating on every request.

| Setting | Default | Description |
|---------|---------|-------------|
| `enableQrCodeCache` | `true` | Enable QR code caching |
| `qrCodeCacheDuration` | `86400` | Cache TTL in seconds (24 hours) |
| `cacheStorageMethod` | `'file'` | `'file'` or `'redis'` |

Cache can be cleared from **Utilities → ShortLink Manager** or via **Settings → Utilities → Clear Caches** (if the user has `shortLinkManager:clearCache` permission).

## Limitations

- SVG QR codes do not support logo overlays (logos are PNG only)
- The `dots` module style may not scan reliably at very small sizes — use at least 200px
- Logo overlays require an image asset stored in Craft's asset system
