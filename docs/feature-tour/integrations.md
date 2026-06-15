# Integrations

Connect ShortLink Manager to SEOmatic for GTM/GA4 tracking events, to Redirect Manager to preserve old slugs automatically, and to Craft's native Link field so editors can pick a short link anywhere a URL field appears.

ShortLink Manager integrates with SEOmatic, Redirect Manager, and Craft's native Link field. The SEOmatic and Redirect Manager integrations can be enabled or disabled in **Settings → Integrations**. The Craft Link Field integration registers automatically — no settings toggle required.

## What you'll use them for

- Fire Google Tag Manager / GA4 data layer events every time a short link is clicked or a QR code is scanned
- Automatically create a 301 redirect in Redirect Manager whenever a short link's slug changes, so existing bookmarks and QR codes keep working
- Let editors pick a short link as the target of any Craft Link field — in matrix blocks, global sets, or any other context where a URL is needed

![Integration settings screen showing SEOmatic and Redirect Manager toggles](images/integrations-settings.webp)

## SEOmatic integration

When SEOmatic is installed and the integration is enabled, ShortLink Manager pushes structured data layer events to the GTM/GA4 data layer whenever a short link is clicked or a QR code endpoint is accessed.

### Event types

Two event types are dispatched to the data layer:

| Event name | When it fires |
|------------|--------------|
| `shortlink_manager_redirect` | A visitor follows the redirect URL |
| `shortlink_manager_qr_scan` | A visitor accesses the QR code endpoint |

### Data layer structure

Each event pushes the following payload:

```json
{
    "event": "shortlink_manager_redirect",
    "shortlink": {
        "code": "abc123",
        "title": "My Campaign",
        "destination_url": "https://example.com/landing",
        "source": "redirect",
        "device_type": "smartphone",
        "os": "iOS",
        "os_version": "17.0",
        "browser": "Safari",
        "browser_version": "17.0",
        "is_mobile": true,
        "is_tablet": false,
        "country": "US",
        "city": "New York"
    }
}
```

### Configuration

Enable the integration in **Settings → Integrations → SEOmatic**. The integration is automatically detected — if SEOmatic is not installed, the option is not shown.

> [!WARNING]
> SEOmatic tracking events cannot fire when [Direct Redirect](direct-redirect.md) is enabled. The redirect template is skipped, so no JavaScript runs before the browser navigates away. Use per-link Direct Redirect overrides to keep tracking for important links.

### Using `renderSeomaticTracking()` in templates

To fire a tracking event when a user interacts with a specific element on your page, call `renderSeomaticTracking()` in your template:

```twig
<a href="{{ shortLink.getRedirectUrl() }}" {{ shortLink.renderSeomaticTracking('redirect')|raw }}>
    Visit Link
</a>
```

This renders the necessary `data-gtm-*` attributes or inline tracking markup that SEOmatic uses to push the event to the data layer.

## Redirect Manager integration

When Redirect Manager is installed and the integration is enabled, ShortLink Manager automatically creates a 301 redirect whenever a short link's slug is changed.

### How it works

1. You edit a short link and change its slug from `my-campaign` to `my-campaign-v2`
2. ShortLink Manager detects the slug change on save
3. A 301 redirect is registered in Redirect Manager: `/s/my-campaign` → `/s/my-campaign-v2`
4. Any existing links or QR codes using the old slug continue to work

This prevents broken links when you need to rename or reorganize your short links.

### Configuration

Enable the integration in **Settings → Integrations → Redirect Manager**. The integration requires Redirect Manager to be installed.

> [!NOTE]
> The redirect is created using the `slugPrefix` setting. If you change the prefix, existing redirects created under the old prefix will still point to the old prefix path.

## Craft Link Field integration @since(5.2.0)

ShortLink Manager registers itself as a link type option for Craft's native [Link field](https://craftcms.com/docs/5.x/reference/field-types/link.html) (available in Craft CMS 5.3+).

Users can select a short link as the link target in any Link field — anywhere a URL, entry, category, or asset would normally appear. No settings toggle is required; the link type registers automatically when Link field is installed.

### Value format

When a Link field contains a ShortLink selection, the field value resolves to the short link's public redirect URL (`/{slugPrefix}/{slug}`), so it works transparently in templates:

```twig
{# In a matrix block with a Link field named 'ctaLink' #}
<a href="{{ block.ctaLink.url }}">{{ block.ctaLink.text }}</a>
```

If the short link is disabled or expired, the URL returns `null` — the same behavior as a disabled entry in a Link field.

### ShortLinkType

The `ShortLinkType` class is registered automatically when Link Field is installed. There is no additional configuration required.

## Integration requirements

| Integration | Required plugin |
|-------------|----------------|
| SEOmatic | `nystudio107/craft-seomatic` |
| Redirect Manager | `lindemannrock/craft-redirect-manager` |
| Craft Link Field | Craft CMS 5.3+ (native Link field) |

All integrations are detected automatically. If the required plugin is not installed, the integration option is hidden in settings and no integration code runs.

For the `IntegrationInterface` API reference, see [Integrations API](../developers/integrations.md).
