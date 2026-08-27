# QR codes

Every short link comes with a scannable QR code — no extra setup. Because the QR code encodes the short URL (not the destination), you can update where a link points any time without reprinting.

## What you'll use it for

- **Print campaigns** — place a QR code on a flyer, poster, or business card that still works after you update the destination.
- **Branded scannables** — add your logo to the center of a QR code and match colors to your brand palette.
- **Flexible delivery** — embed QR codes directly in Twig templates as data URIs (useful for email), or link to the hosted image endpoint.
- **Bulk downloads** — generate and download QR codes as PNG or SVG for use in design assets.

## Turn on and customize QR codes

### Enable per link

On the short link edit page, toggle **QR Code Enabled** to activate the QR endpoints for that link. When disabled, both the image and display-page endpoints follow the plugin's configured not-found behavior.

### Set global defaults

Go to **Settings → QR Codes** to configure appearance defaults for all links. A live preview updates as you change the size, colors, and styles, so you can see the result before saving — the same preview also appears on a short link's edit page when you override QR settings per link.

![Settings → QR Codes page showing size, color, module style, and eye style options](../images/qr-codes-settings.webp)

Per-link values left at `null` inherit from these global defaults.

### Choose a visual style

Module style and eye style combine to give your QR codes a distinct look:

![Grid comparing module styles (square, rounded, dots) and eye styles (square, rounded, pointed)](../images/qr-codes-styles.webp)

| Option | Values | Default |
|--------|--------|---------|
| Module style | `'square'`, `'dots'`, `'rounded'` | `'square'` |
| Eye style | `'square'`, `'rounded'`, `'pointed'` | `'square'` |

> [!WARNING]
> The `dots` module style may not scan reliably at very small sizes. Use at least 200 px when choosing `dots`.

### Choose an output format

PNG rendering follows Craft's effective image driver, whether Craft is using Imagick or GD. All supported module and eye styles work with either driver. SVG generation uses its own vector renderer and does not depend on Craft's raster driver.

Use PNG when you need a bitmap or logo overlay. Use SVG when you want a resolution-independent QR code without a logo.

---

## Customization reference

### Appearance settings

| Setting | Type | Default | Description |
|--------|------|---------|-------------|
| `defaultQrSize` | `int` | `256` | Output size in pixels (100–1000) |
| `defaultQrColor` | `string` | `'#000000'` | Module foreground color (hex) |
| `defaultQrBgColor` | `string` | `'#FFFFFF'` | Background color (hex) |
| `defaultQrFormat` | `string` | `'png'` | Output format: `'png'` or `'svg'` |
| `defaultQrMargin` | `int` | `4` | Quiet zone in modules (0–10) |
| `defaultQrErrorCorrection` | `string` | `'M'` | Error correction for PNG and SVG: `'L'` (7%), `'M'` (15%), `'Q'` (25%), `'H'` (30%). The default `M` level is passed explicitly to the Bacon QR Code encoder. |
| `qrModuleStyle` | `string` | `'square'` | Module shape: `'square'`, `'dots'`, `'rounded'` |
| `qrEyeStyle` | `string` | `'square'` | Finder pattern shape: `'square'`, `'rounded'`, `'pointed'` |
| `qrEyeColor` | `?string` | `null` | Eye color override (hex). Falls back to foreground color |

### Logo overlay

Enable `enableQrLogo` in settings to add a brand logo to the center of QR codes. When enabled:

- Set a **default logo** (a Craft asset) applied to all QR codes unless overridden per link.
- Optionally restrict which asset volume logos can come from (`qrLogoVolumeUid`).
- Control the **logo size** as a percentage of the QR code width (10–30%, default 20%).

Per-link logo overrides use the `qrLogoId` field on the short link edit page.

Logo inputs can be readable JPEG, PNG, or GIF Craft Assets. Assets on local and remote volumes are supported through Craft's temporary-copy API. The overlay is PNG-only and is composed with GD after the base QR image is rendered. If the selected logo is missing, inaccessible, corrupt, or unsupported, ShortLink Manager returns the valid unbranded PNG instead.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enableQrLogo` | `bool` | `false` | Enable logo overlay |
| `qrLogoVolumeUid` | `?string` | `null` | Restrict logo selection to this asset volume |
| `defaultQrLogoId` | `?int` | `null` | Default logo asset ID |
| `qrLogoSize` | `int` | `20` | Logo size as percentage of QR code (10–30) |

> [!WARNING]
> A logo reduces the scannable area. `defaultQrErrorCorrection: 'H'` (30% recovery) is appropriate for logo-bearing codes, but you should still scan-test the final code at its intended print or display size.

## QR code URLs

When QR code generation is enabled on a short link, two endpoints become available:

| URL | Returns |
|-----|---------|
| `/{qrPrefix}/{code}` | Raw QR code image (PNG or SVG) |
| `/{qrPrefix}/{code}/view` | Display page with title, image, and download button |

With a common configuration (`qrPrefix` = `s/qr`):

- Image: `https://example.com/s/qr/abc123`
- Display page: `https://example.com/s/qr/abc123/view`

Public image and display URLs always use the short link's saved QR configuration, with global defaults filling any unset per-link values. Styling query parameters are deliberately ignored, including invalid or repeated values, so public URLs remain canonical and cacheable instead of creating a new rendered identity for every query string.

For example, `?size=4096&format=svg&color=ff0000` still returns the saved size, format, and colors. URLs created by older versions with styling parameters continue to work, but now return the same canonical bytes as the clean URL. Parameters such as `preview`, `url`, and `linkId` cannot switch a public code route into a control-panel rendering mode.

The supported public image option is `download=1`, when downloads are enabled. That download uses the saved/default size and appearance; its bytes, MIME type, dimensions, extension, and filename tokens all describe the same canonical output.

Unsaved styling remains available in the authenticated control panel. The QR settings preview uses the selected default size, while edit-page previews render at exactly 150px. Authenticated preview and download responses use private, no-store headers and bypass the persistent QR cache, so unsaved designs do not become shared cache entries.

## Downloading QR codes

When `enableQrDownload` is `true` (default), QR codes can be downloaded. The download filename follows the `qrDownloadFilename` pattern with these tokens:

| Token | Replaced with |
|-------|--------------|
| `{code}` | The short link's code |
| `{size}` | The QR code size in pixels |
| `{format}` | The format: `png` or `svg` |

Default pattern: `{code}-qr-{size}` produces filenames like `abc123-qr-256.png`.

The edit-page and sidebar download menus use an authenticated action rather than the public image URL. Presets are 256px, 512px, 1024px, and 2048px; custom authenticated downloads accept 100–4096px. Saved per-link sizes, global defaults, public output, and direct programmatic rendering remain limited to 100–1000px. Values outside an authenticated export's range are normalized to the nearest boundary, and malformed or non-scalar sizes use the saved/default fallback.

Only PNG and SVG are valid effective formats. The normalized format and size control the rendered bytes, response MIME type, dimensions, filename tokens, and extension together. For large print output, SVG is usually the better choice because it remains sharp without creating an oversized bitmap.

---

## Using QR codes in templates

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

    {# Base64 data URI for email templates #}
    <img src="{{ link.getQrCodeDataUri({size: 150}) }}" alt="QR Code">
{% endif %}
```

| Method | Returns | Description |
|--------|---------|-------------|
| `getQrCodeUrl(options)` @since(5.0.0) | `string` | Canonical public raw-image URL. Style options are ignored; `download` remains supported |
| `getQrCodeDataUri(options)` @since(5.0.0) | `string` | Base64 `data:image/...` URI — use for email or inline embedding |
| `getQrCode(options)` @since(5.0.0) | `string` | Raw binary image data (for programmatic use) |
| `getQrCodeDisplayUrl(options)` @since(5.1.0) | `string` | Canonical public `/view` URL. The options argument is retained for compatibility but does not affect the URL |

`getQrCode()` and `getQrCodeDataUri()` continue to accept trusted server-side rendering options, including size, colors, format, margin, error correction, module and eye styles, and logo. Their service-level size remains 100–1000px. The two public URL helpers intentionally do not put styling parameters into visitor-facing URLs. When called as properties such as `link.qrCodeUrl`, public output uses saved per-link values with global defaults as fallbacks.

## The display page

The `/{qrPrefix}/{code}/view` endpoint renders a page containing the canonical public image and context. Query parameters do not select different QR bytes or create QR cache entries. A custom template can be set via the `qrTemplate` setting.

For the starter template, copy command, and available variables, see [Custom templates](../developers/custom-templates.md#qrtwig).

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
| `cacheStorageMethod` @since(5.3.0) | `'file'` | Persistent cache selection: `'file'`, `'craft'`, or the backward-compatible `'redis'` application-cache token |

Each public short link has one effective QR cache identity for its saved URL and appearance. Repeated anonymous requests reuse that identity; saving an effective option produces a different canonical output. Direct programmatic renders use cache identities based on their normalized rendering options. Authenticated unsaved previews and downloads bypass persistent plugin-file, Craft application-cache, and disabled-storage decisions entirely.

On durable hosts, `'file'` uses ShortLink Manager's plugin-owned file family. On ephemeral hosts, it attempts to use Craft's configured application cache when that cache is suitable for cross-request persistence. Choose `'craft'` to select that suitable Craft application cache explicitly. The older `'redis'` token is retained for backward compatibility and makes the same application-cache selection; it does not configure Redis or prove that Craft's cache component is Redis-backed. When the application cache is unsuitable, persistent QR storage is disabled instead of falling back to a different persistent backend.

ShortLink Manager validates cached PNG/SVG output before serving it. An empty, partial, or wrong-format hit is ignored, and a replacement is written only after fresh generation succeeds. Existing styled public URLs no longer create query-selected cache variants. Cache can be cleared from **Utilities → ShortLink Manager** (requires `shortLinkManager:clearCache` permission), but routine authenticated preview/export work does not require cache clearing.

```php
// config/shortlink-manager.php
return [
    'enableQrCodeCache'   => true,
    'qrCodeCacheDuration' => 86400,
    'cacheStorageMethod'  => 'craft', // Craft's configured suitable application cache
];
```

## Global vs per-link settings

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

> [!TIP]
> QR scans follow the same redirect mode as normal clicks. If `directRedirect` is enabled, repeat scan analytics can be bypassed by browser/CDN/static caching unless those [routes](custom-domain.md#site-aware-routes) bypass cache. If you need analytics-safe QR scans under caching, keep `directRedirect = false`.

## Limitations

- SVG output does not support logo overlays (logos are PNG only).
- The `dots` module style may not scan reliably at very small sizes — use at least 200 px.
- QR codes always encode the short link's public URL — the destination URL cannot be encoded directly.
