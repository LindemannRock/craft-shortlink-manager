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

When this integration is enabled, ShortLink Manager emits client-side tracking events before the browser navigates away on a redirect. This is compatible with Google Tag Manager (GTM) and Google Analytics (GA) tag setups.

The events fire from the redirect template — which renders briefly before the browser follows the final redirect. The redirect template must be active (i.e., `directRedirect` must be `false`) for events to fire.

### Setup

```php
// config/shortlink-manager.php
return [
    '*' => [
        'enabledIntegrations' => ['seomatic'],
        'seomaticTrackingEvents' => ['redirect', 'qr_scan'],
        'seomaticEventPrefix' => 'shortlink_manager',
    ],
];
```

### Event configuration

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `seomaticTrackingEvents` | `array` | `['redirect', 'qr_scan']` | Which event types to emit. `'redirect'` fires on link clicks, `'qr_scan'` fires on QR code scans |
| `seomaticEventPrefix` | `string` | `'shortlink_manager'` | Prefix added to event names in GTM/GA (lowercase, numbers, underscores only) |

### Event names in GTM/GA

With the default prefix `shortlink_manager`:

| Event type | GTM/GA event name |
|------------|------------------|
| `redirect` | `shortlink_manager_redirect` |
| `qr_scan` | `shortlink_manager_qr_scan` |

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

Use `shortLink.renderQrSeomaticTracking()` in QR display templates. These tracking helpers return SEOmatic-compatible markup, or `null` if SEOmatic is not installed, the integration is disabled, or the matching event is not selected in `seomaticTrackingEvents`.

> [!IMPORTANT]
> Use `shortLink.renderRedirectScript()` for the redirect — it forwards through `goUrl`, the server-side tracking hop that records analytics before issuing the final redirect. Don't redirect directly to `shortLink.destinationUrl` / `shortLink.url`, which bypasses tracking. (For debugging on staging, `renderRedirectScript(true)` lets `?debug=1` work outside `devMode` — see [Custom templates](custom-templates.md).)

### Direct redirect warning

> [!IMPORTANT]
> SEOmatic tracking events cannot fire when `directRedirect` is enabled (globally or per link). The redirect template is never rendered when Direct Redirect is active, so no client-side JavaScript can run before the browser navigates away.

If you rely on SEOmatic/GTM tracking:
- Keep `directRedirect = false` globally
- Or enable Direct Redirect globally and set the per-link `directRedirect` override to `false` for links that still need tracking

See [Direct Redirect](../feature-tour/direct-redirect.md) for more details.

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
