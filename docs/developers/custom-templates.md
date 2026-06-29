# Custom templates

ShortLink Manager renders a few front-end pages — the redirect interstitial, the QR code page, and the expired-link page. Each ships with a default template you can override in your own site to control the markup, branding, and behavior. You only need to override the ones you want to change; anything left at its default keeps working.

## Overridable templates

| Template | Default path | Setting | What it renders |
|----------|--------------|---------|-----------------|
| `redirect.twig` | `shortlink-manager/redirect` | `redirectTemplate` | The redirect interstitial shown before the browser navigates to the destination. Fires analytics/SEOmatic tracking, then forwards to the tracked `goUrl`. **Skipped entirely when [Direct Redirect](../feature-tour/direct-redirect.md) is on** — a direct HTTP redirect is issued instead. |
| `qr.twig` | `shortlink-manager/qr` | `qrTemplate` | The QR code display page at `/{qrPrefix}/{code}/view`. |
| `expired.twig` | `shortlink-manager/expired` | `expiredTemplate` | The page shown when a short link has expired. |

## Where to find and copy them

The reference templates ship inside the plugin. Copy the one you want to customize into your own `templates/` folder:

```bash
# Redirect interstitial
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/redirect.twig templates/shortlink-manager/redirect.twig

# QR code page
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/qr.twig templates/shortlink-manager/qr.twig

# Expired page
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/expired.twig templates/shortlink-manager/expired.twig
```

Once a file exists at `templates/shortlink-manager/{name}.twig`, the default path resolves to it automatically — no setting change is needed. The path **settings** only matter if you want the template somewhere else:

- Set `redirectTemplate` / `qrTemplate` / `expiredTemplate` in **Settings → ShortLink Manager** (or `config/shortlink-manager.php`) to point at a different template path.
- Leave a setting empty to use the default path shown above.
- Each path field accepts a `$ENV_VAR` in the Control Panel, or `App::env()` in the config file.
- A value in `config/shortlink-manager.php` overrides the Control Panel field (the CP field is shown disabled with an override warning).

See [Configuration → Template settings](../get-started/configuration.md) for the settings reference.

## Available variables

Each template receives a fixed set of variables from the plugin. Use these instead of querying for the link yourself.

### `redirect.twig`

| Variable | Type | Description |
|----------|------|-------------|
| `shortLink` | `ShortLink` | The resolved short link element. |
| `goUrl` | `string` | The tracked forwarding URL. Send the visitor here (e.g. `window.location.replace(goUrl)`) so the click is recorded before the final redirect — do **not** use `shortLink.url`, which would bypass tracking and loop. |
| `source` | `string` | `direct` or `qr`. |
| `eventType` | `string` | `redirect` or `qr_scan`. |
| `deviceInfo` | `array` | Detected device details for the request. |

The element also exposes `shortLink.renderSeomaticTracking(eventType)` for [SEOmatic](integrations.md) data-layer tracking.

```twig
{# templates/shortlink-manager/redirect.twig #}
<!DOCTYPE html>
<html>
<head>
    {{ shortLink.renderSeomaticTracking(eventType)|raw }}
    <script>window.location.replace({{ goUrl|json_encode|raw }});</script>
</head>
<body>
    <p>Redirecting… <a href="{{ goUrl }}">Continue</a></p>
</body>
</html>
```

> [!TIP]
> In `devMode`, append `?debug=1` to a short link URL to stop the auto-redirect and log the generated `goUrl` in the browser console — useful for verifying custom-domain and multisite URLs. See [Troubleshooting](../resources/troubleshooting.md).

### `qr.twig`

| Variable | Type | Description |
|----------|------|-------------|
| `shortLink` | `ShortLink` | The short link the QR code points to. |
| `siteName` | `string` | Current site name. |
| `currentSite` | `Site` | Current site model. |

Render the QR image with the [template methods](../feature-tour/qr-codes.md#using-qr-codes-in-templates) on `shortLink` (e.g. `shortLink.getQrCodeDataUri()`).

### `expired.twig`

| Variable | Type | Description |
|----------|------|-------------|
| `message` | `string` | The expired message (per-link `expiredMessage`, falling back to the global setting), already translated and sanitized. |
| `shortLink` | `ShortLink` | The expired short link element. |

## What to customize (and what to keep)

- **Customize freely:** layout, branding, copy, styling, the redirect delay, and any extra markup or analytics you want on the page.
- **Keep on the redirect page:** the forward to `goUrl` (and `renderSeomaticTracking()` if you use SEOmatic) — these are what record the click. Replacing `goUrl` with `shortLink.url` skips tracking.
- Redirect templates are standalone pages by default. You can `{% extends %}` your own layout if you prefer, but a minimal page generally redirects faster.

## Related

- [Configuration → Template settings](../get-started/configuration.md)
- [Direct Redirect](../feature-tour/direct-redirect.md) — when the redirect template is bypassed
- [QR codes](../feature-tour/qr-codes.md)
- [SEOmatic integration](integrations.md)
