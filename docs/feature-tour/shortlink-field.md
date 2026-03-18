# ShortLink Field

The ShortLink Field is a custom Craft field type that attaches a short link directly to any element (entries, products, categories, etc.). It appears in the element editor and keeps the short link's destination URL in sync with the element automatically.

## What It Does

When you add a ShortLink Field to an entry's field layout, the entry editor shows a ShortLink panel. Editors can see the short URL, copy it, scan the QR code, and manage the link — all without leaving the entry.

When the entry's URL changes (slug update, section change, draft propagation), ShortLink Manager detects the change and updates the short link's destination URL automatically.

## Adding the Field

1. Go to **Settings → Fields → New Field**
2. Choose **ShortLink** as the field type
3. Configure field settings:
   - **Link Type** — `code` (auto-generated) or `vanity` (custom code)
   - **Default HTTP Code** — `301`, `302`, `307`, or `308`
4. Add the field to the desired field layout via **Settings → [Entry Type] → Field Layout**

## Field Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `linkType` | `code` | Whether the short code is auto-generated or requires a custom code |
| `defaultHttpCode` | `302` | Default redirect status for links created via this field |

## How Destination URL Sync Works

ShortLink Manager listens for `Elements::EVENT_AFTER_SAVE_ELEMENT`. When a non-new element is saved and it has a short link attached (via the field or programmatically), the plugin checks whether the element's URL has changed.

If the URL changed, the short link's destination URL is updated for the element's site. For multisite setups, each site's destination URL resolves from the element's URL on that specific site.

This sync only happens for `shortLinkType = 'auto'` links (links created and managed by the field). Standalone/manual links are not automatically updated.

## Multi-Site Behavior

The ShortLink Field is site-aware. When an element is saved:

- The field creates or retrieves the short link for the current site
- Each site has its own destination URL in the content table
- The short code (slug) is shared across sites

If you translate an entry to multiple sites, each site's short link points to that site's version of the entry.

## Accessing Field Values in Templates

The ShortLink Field stores the short link element. Access it like any element-linked relationship in Twig:

```twig
{# Get the shortlink attached to this entry via the field #}
{% set link = craft.shortLinkManager.get({element: entry}) %}

{% if link %}
    <p>Short URL: <a href="{{ link.url }}">{{ link.url }}</a></p>
    <img src="{{ link.qrCodeDataUri }}" alt="QR Code" width="200">
{% endif %}
```

You can also query by element directly from `ShortLink::find()`:

```twig
{% set link = craft.shortLinkManager.shortLinks({
    elementId: entry.id,
    siteId: currentSite.id,
}).one() %}
```

## Craft Link Field

ShortLink Manager also registers as a link type in Craft's native Link field (Craft CMS 5.3+). See [Integrations](integrations.md) for details.

## Sidebar Panel

When a short link is attached to an entry (via the field or a manual link with `elementId` set), an info panel appears in the entry editor's right sidebar. The panel shows:

- The short URL with a copy button
- QR code preview
- Link status and expiry date (if set)

The sidebar panel only appears when ShortLink Manager is enabled for the entry's current site.

## Limitations

- The sidebar panel currently supports entries only. Support for other element types (categories, assets) is planned.
- Vanity URL codes must be set manually per element — there is no auto-generation of vanity codes from entry slugs.
