# Custom templates

ShortLink Manager renders a few front-end pages — the redirect interstitial, the QR code page, and the expired-link page. Each ships with a starter template that you copy into your site's `templates/` folder. Keep the copied global starter for pages you do not customize, and add site-specific overrides only where a site needs different markup, branding, or behavior.

## Overridable templates

| Template | Default path | Setting | What it renders |
|----------|--------------|---------|-----------------|
| `redirect.twig` | `shortlink-manager/redirect` | `redirectTemplate` | The redirect interstitial shown before the browser navigates to the destination. Fires analytics/SEOmatic tracking, then forwards to the tracked `goUrl`. **Skipped entirely when [Direct Redirect](../feature-tour/direct-redirect.md) is on** — a direct HTTP redirect is issued instead. |
| `qr.twig` | `shortlink-manager/qr` | `qrTemplate` | The QR code display page at `/{qrPrefix}/{code}/view`. |
| `expired.twig` | `shortlink-manager/expired` | `expiredTemplate` | The page shown when a short link has expired. |

## Where to find and copy them

The reference templates ship inside the plugin. The setup command copies any missing starter templates into the paths configured in settings:

```bash title="PHP"
php craft shortlink-manager/setup/copy-templates
```

```bash title="DDEV"
ddev craft shortlink-manager/setup/copy-templates
```

Use `--template=redirect`, `--template=expired`, or `--template=qr` to copy one template, and `--overwrite` when you intentionally want to replace an existing destination.

### Multisite resolution and global fallbacks

For each site enabled in ShortLink Manager, Craft looks for `templates/{siteHandle}/{configuredPath}` first and then `templates/{configuredPath}`. ShortLink Manager uses the same order for setup readiness. A global template is enough for every site, so you do not need to create site-handle directories unless a site needs different markup.

The setup command always manages the global fallback. If only some sites have overrides, it creates the global template for the remaining sites while leaving every override untouched. If all enabled sites already resolve the template—through any combination of overrides and a global fallback—it skips the copy.

Configured paths with an explicit extension keep that destination exactly: `custom/redirect.html` copies to `templates/custom/redirect.html`. An extensionless path such as `custom/redirect` uses `templates/custom/redirect.twig` as the starter destination.

You can also copy files manually:

**Redirect interstitial**

```bash
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/redirect.twig templates/shortlink-manager/redirect.twig
```

**QR code page**

```bash
cp vendor/lindemannrock/craft-shortlink-manager/src/templates/qr.twig templates/shortlink-manager/qr.twig
```

**Expired page**

```bash
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
| `goUrl` | `string` | The tracked forwarding URL — the `shortlink-manager/redirect/go` action hop that records the click before issuing the final redirect. `renderRedirectScript()` forwards here automatically; also use it for the manual fallback link. Do **not** redirect to `shortLink.url`, which bypasses tracking and loops. |
| `source` | `string` | `direct` or `qr`. |
| `deviceInfo` | `array` | Detected device details for the request. |

The element exposes these template helpers:

- `shortLink.renderRedirectScript()` @since(5.23.0) — the tracked client-side redirect script. It forwards to `goUrl` (recording the click) and handles `?debug=1`. **Debug is devMode-only by default**; pass `renderRedirectScript(true)` to allow `?debug=1` outside devMode (see the tip below).
- `shortLink.renderRedirectSeomaticTracking()` @since(5.24.0) — [SEOmatic](integrations.md) data-layer tracking for the redirect page (returns nothing when SEOmatic/the event is unavailable).

```twig
{# templates/shortlink-manager/redirect.twig #}
<!DOCTYPE html>
<html>
<head>
    {{ shortLink.renderRedirectSeomaticTracking() }}
</head>
<body>
    <p>Redirecting… <a href="{{ goUrl|e('html_attr') }}">Continue</a></p>

    {# Tracked client-side redirect (forwards to goUrl; ?debug=1 in devMode) #}
    {{ shortLink.renderRedirectScript() }}
</body>
</html>
```

> [!TIP]
> **Debugging the redirect.** Two behaviors, depending on how you call the helper:
>
> - `{{ shortLink.renderRedirectScript() }}` — default, safe. `?debug=1` is honored **only in `devMode`**; on staging/production it does nothing and the redirect runs normally.
> - `{{ shortLink.renderRedirectScript(true) }}` — opt-in override (custom templates only). Allows `?debug=1` **even with `devMode` off**, so you can stop the redirect on staging and log the generated `goUrl` in the browser console. Use it intentionally for staging validation and revert it for production.
>
> When you can't (or don't want to) enable debug — e.g. the shipped template on production — diagnose from the response headers instead (see [Troubleshooting](../resources/troubleshooting.md#diagnosing-on-staging-or-production-no-devmode)).

### `qr.twig`

| Variable | Type | Description |
|----------|------|-------------|
| `shortLink` | `ShortLink` | The short link the QR code points to. |
| `siteName` | `string` | Current site name. |
| `currentSite` | `Site` | Current site model. |

Render the QR image with the [template methods](../feature-tour/qr-codes.md#using-qr-codes-in-templates) on `shortLink` (e.g. `shortLink.getQrCodeDataUri()`).

If SEOmatic tracking is enabled, use `shortLink.renderQrSeomaticTracking()` on QR display pages. Do not pass event type strings in templates; the plugin maps redirect and QR page intent to the configured tracking events.

### `expired.twig`

| Variable | Type | Description |
|----------|------|-------------|
| `message` | `string` | The expired message (per-link `expiredMessage`, falling back to the global setting), already translated and sanitized. |
| `shortLink` | `ShortLink` | The expired short link element. |

## What to customize (and what to keep)

- **Customize freely:** layout, branding, copy, styling, the redirect delay, and any extra markup or analytics you want on the page.
- **Keep on the redirect page:** `{{ shortLink.renderRedirectScript() }}` (the tracked forward to `goUrl`) and `{{ shortLink.renderRedirectSeomaticTracking() }}` if you use SEOmatic — these are what record the click. Redirecting to `shortLink.url` instead of `goUrl` skips tracking and loops.
- Redirect templates are standalone pages by default. You can `{% extends %}` your own layout if you prefer, but a minimal page generally redirects faster.

## Related

- [Configuration → Template settings](../get-started/configuration.md)
- [Direct Redirect](../feature-tour/direct-redirect.md) — when the redirect template is bypassed
- [QR codes](../feature-tour/qr-codes.md)
- [SEOmatic integration](integrations.md)
