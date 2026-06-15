# ShortLink Field

Attach a short link directly to any entry, product, or category — and have its destination URL stay in sync with that element automatically.

When you add a ShortLink Field to a field layout, editors can see the short URL, copy it, and access the QR code from inside the element editor, without opening ShortLink Manager separately. When the element's URL changes, the short link's destination updates to match.

![ShortLink Field panel inside an entry editor](images/shortlink-field-entry.webp)

## What you'll use it for

- Automatically create a trackable short link for every new product, article, or landing page
- Keep short link destinations current when entries are reorganized or slugs change
- Let editors copy the short URL or scan the QR code while editing, without switching apps
- Use a QR code in print materials that always resolves to the current version of the page

## Adding the field

1. Go to **Settings → Fields → New Field**
2. Choose **ShortLink** as the field type
3. Configure the field settings (see below)
4. Add the field to the desired field layout via **Settings → [Entry Type] → Field Layout**

## Field settings

| Setting | Default | Description |
|---------|---------|-------------|
| `linkType` | `'code'` | Whether the short code is auto-generated (`code`) or requires a custom code (`vanity`) |
| `defaultHttpCode` | `302` | Default redirect status for links created via this field |

## How destination URL sync works

When an element with the ShortLink Field is saved, the field's `afterElementSave` hook fires. If the element already has a short link attached, the plugin updates the destination URL to match the element's current URL.

Sync runs for:
- Elements with a URL (requires the element's site to have URLs enabled)
- Canonical saves (not drafts, revisions, or propagation saves from Craft's multi-site sync)

For `linkType = 'vanity'`, a code must be set before the first save — the field will not create the link if no code is provided. For `linkType = 'code'`, the short code is auto-generated on first save.

Sync only applies to `shortLinkType = 'auto'` links — links created and managed by the field. Standalone links you create manually in ShortLink Manager are not affected.

## Multi-site behavior

The ShortLink Field is site-aware. When an element is saved:

- The field creates or retrieves the short link for the current site
- Each site has its own destination URL in the content table
- The short code (slug) is shared across sites

If you translate an entry to multiple sites, each site's short link points to that site's version of the entry.

## Accessing field values in templates

The ShortLink Field stores the short link element. Access it by querying for the element's attached short link:

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

## Sidebar panel

When a short link is attached to an entry (via the field or a manual link with `elementId` set), an info panel appears in the entry editor's right sidebar. The panel shows:

- The short URL with a copy button
- QR code preview
- Link status and expiry date (if set)

The sidebar panel only appears when ShortLink Manager is enabled for the entry's current site.

## Limitations

- The sidebar panel currently supports entries only. Support for other element types (categories, assets) is planned.
- Vanity URL codes must be set manually per element — there is no auto-generation of vanity codes from entry slugs.
