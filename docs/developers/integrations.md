# Integrations

@since(5.1.0)

ShortLink Manager integrates with Redirect Manager and SEOmatic to automate link maintenance and enable client-side analytics tracking.

## Redirect Manager

**Integrates with:** `lindemannrock/redirect-manager`

When the Redirect Manager integration is enabled, ShortLink Manager can automatically create redirects in Redirect Manager when short links change. This preserves SEO link equity and keeps existing bookmarks and QR code scans working after a short link is updated or removed.

### Setup

```php
// config/shortlink-manager.php
return [
    '*' => [
        'enabledIntegrations' => ['redirect-manager'],
        'redirectManagerEvents' => ['slug-change'], // also: 'expire', 'delete'
    ],
];
```

Or manage from the CP: **ShortLink Manager → Settings → Integrations**.

### Trigger Events

| Event | When a redirect is created |
|-------|---------------------------|
| `slug-change` | When a short link's code/slug is changed |
| `expire` | When a short link's expiry date passes |
| `delete` | When a short link is deleted |

### Fallback Lookup

When a short link code cannot be resolved (the link was deleted or disabled), the redirect controller queries Redirect Manager for a matching 301/302 rule before falling back to `notFoundRedirectUrl`. This means you can retire a short link and replace it with a Redirect Manager rule without breaking existing URLs.

### Requirements

- `lindemannrock/redirect-manager` must be installed and enabled
- The integration silently deactivates if Redirect Manager is not present

---

## SEOmatic

**Integrates with:** `nystudio107/seomatic`

When the SEOmatic integration is enabled, ShortLink Manager emits client-side tracking events before the browser navigates away on a redirect. This is compatible with Google Tag Manager (GTM) and Google Analytics (GA) tag setups.

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

### Event Configuration

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `seomaticTrackingEvents` | `array` | `['redirect', 'qr_scan']` | Which event types to emit. `'redirect'` fires on link clicks, `'qr_scan'` fires on QR code scans |
| `seomaticEventPrefix` | `string` | `'shortlink_manager'` | Prefix added to event names in GTM/GA (lowercase, numbers, underscores only) |

### Event Names in GTM/GA

With the default prefix `shortlink_manager`:

| Event Type | GTM/GA Event Name |
|------------|------------------|
| `redirect` | `shortlink_manager_redirect` |
| `qr_scan` | `shortlink_manager_qr_scan` |

### In Redirect Templates

Render the tracking HTML in your redirect template:

```twig
{# templates/shortlink-manager/redirect.twig #}
{% extends '_layout' %}

{% block content %}
    {{ shortLink.renderSeomaticTracking('redirect')|raw }}

    <meta http-equiv="refresh" content="0; url={{ shortLink.destinationUrl }}">
{% endblock %}
```

The `renderSeomaticTracking(eventType)` @since(5.1.0) method returns SEOmatic-compatible tracking markup, or `null` if SEOmatic is not installed or the event type is not in `seomaticTrackingEvents`.

### Direct Redirect Warning

> [!IMPORTANT]
> SEOmatic tracking events cannot fire when `directRedirect` is enabled (globally or per link). The redirect template is never rendered when Direct Redirect is active, so no client-side JavaScript can run before the browser navigates away.

If you rely on SEOmatic/GTM tracking:
- Keep `directRedirect = false` globally
- Use the per-link `directRedirect` override to enable it only on links where tracking is not needed

See [Direct Redirect](../feature-tour/direct-redirect.md) for more details.

### Requirements

- `nystudio107/seomatic` must be installed and enabled
- The integration silently deactivates if SEOmatic is not present

---

## Enabling Multiple Integrations

Both integrations can be active simultaneously:

```php
return [
    '*' => [
        'enabledIntegrations' => ['redirect-manager', 'seomatic'],
    ],
];
```
