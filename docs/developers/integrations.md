# Integrations

Wire ShortLink Manager to Redirect Manager and SEOmatic to automate link maintenance and enable client-side analytics tracking.

## Redirect Manager

**Integrates with:** `lindemannrock/redirect-manager`

When this integration is enabled, ShortLink Manager automatically creates redirects in Redirect Manager when short links change. This preserves link equity and keeps existing bookmarks and QR code scans working after a short link is updated or removed.

### Setup

```php
// config/shortlink-manager.php
return [
    '*' => [
        'enabledIntegrations' => ['redirect-manager'],
        'redirectManagerEvents' => ['slug-change'],
    ],
];
```

Or manage from the CP: **ShortLink Manager → Settings → Integrations**.

### Trigger events

| Event | When a redirect is created |
|-------|---------------------------|
| `slug-change` | When a short link's code/slug is changed |

### Fallback lookup

When a short link code cannot be resolved (the link was deleted or disabled), the redirect controller queries Redirect Manager for a matching 301/302 rule before falling back to `notFoundRedirectUrl`. This means you can retire a short link and replace it with a Redirect Manager rule without breaking existing URLs.

### Requirements

- `lindemannrock/redirect-manager` must be installed and enabled
- The integration silently deactivates if Redirect Manager is not present

---

## SEOmatic

**Integrates with:** `nystudio107/seomatic`

When this integration is enabled, ShortLink Manager emits client-side tracking events on QR-attributed landing-page arrival and immediately before automatic onward navigation. This is compatible with Google Tag Manager (GTM) and Google Analytics (GA) tag setups.

The events fire from the redirect template — which renders briefly before the browser follows the final redirect. The global Direct Redirect switch must be off, or the link must opt out with `directRedirect = false`, so that this template renders.

### Setup

```php
// config/shortlink-manager.php
return [
    '*' => [
        'enabledIntegrations' => ['seomatic'],
        'seomaticTrackingEvents' => ['redirect', 'qr_scan'],
        'seomaticEventPrefix' => 'short_links',
    ],
];
```

### Event configuration

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `seomaticTrackingEvents` | `array` | `['redirect', 'qr_scan']` | Independent event switches: `redirect` before automatic onward navigation, `qr_scan` on rendered `src=qr` arrival |
| `seomaticEventPrefix` | `string` | `'short_links'` | Prefix added to event names in GTM/GA (lowercase, numbers, underscores only) |

### Event names in GTM/GA

With the default prefix `short_links`:

| Event type | GTM/GA event name |
|------------|------------------|
| `redirect` | `short_links_redirect` |
| `qr_scan` | `short_links_qr_scan` |

Saved and explicit config prefixes are preserved, including the legacy `shortlink_manager`. The new default only applies when no saved or configured value takes precedence.

### In redirect templates

Render the tracking HTML in your redirect template:

```twig
{# templates/shortlink-manager/redirect.twig #}
{% extends '_layout' %}

{% block content %}
    {{ shortLink.renderRedirectSeomaticTracking() }}
    {{ shortLink.renderRedirectScript() }}
{% endblock %}
```

Render the tracking helper before `renderRedirectScript()`. It reads `src` from the browser URL, preserving visitor attribution even when the HTML is cached. A `src=qr` arrival emits `qr_scan` immediately if selected. After its existing 100 ms delay, the navigation helper emits `redirect` independently, just before forwarding to `goUrl`. An allowed debug pause emits no redirect; the QR arrival can still be recorded.

Neither QR image requests nor QR display pages emit browser tracking events. Existing `renderQrSeomaticTracking()` and `renderSeomaticTracking('qr_scan')` calls remain callable and return `null`. Missing/disabled SEOmatic or disabled analytics suppresses the landing helper without blocking navigation. No extra page-view event is emitted; use the page view from your normal analytics setup.

> [!IMPORTANT]
> Use `shortLink.renderRedirectScript()` for the redirect — it forwards through `goUrl`, the server-side tracking hop that records analytics before issuing the final redirect. Don't redirect directly to `shortLink.destinationUrl` / `shortLink.url`, which bypasses tracking. (For debugging on staging, `renderRedirectScript(true)` lets `?debug=1` work outside `devMode` — see [Custom templates](custom-templates.md).)

### Direct redirect warning

> [!IMPORTANT]
> SEOmatic tracking events cannot fire when global `directRedirect` is enabled and the link has not opted out. The redirect template is never rendered when Direct Redirect is active, so no client-side JavaScript can run before the browser navigates away.

If you rely on SEOmatic/GTM tracking:
- Keep `directRedirect = false` globally
- Or enable Direct Redirect globally and set the per-link `directRedirect` override to `false` for links that still need tracking

Direct HTTP mode emits neither browser event, including for `src=qr`. Its existing hit count and enabled internal analytics still run when the request reaches Craft. See [Direct Redirect](../feature-tour/direct-redirect.md) for more details.

### Requirements

- `nystudio107/seomatic` must be installed and enabled
- The integration silently deactivates if SEOmatic is not present

---

## Enabling multiple integrations

Both integrations can be active simultaneously:

```php
return [
    '*' => [
        'enabledIntegrations' => ['redirect-manager', 'seomatic'],
    ],
];
```
