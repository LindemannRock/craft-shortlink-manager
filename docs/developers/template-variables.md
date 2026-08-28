# Template variables

Access short links, create them on the fly, and pull analytics data from any Twig template via `craft.shortLinkManager`. The variable is available globally — no import needed.

## Querying short links

### `shortLinks(criteria)` @since(5.0.0)

Get a `ShortLinkQuery` for querying short links. Supports all standard Craft element query params plus ShortLink-specific filters.

```twig
{# All enabled short links #}
{% set links = craft.shortLinkManager.shortLinks().all() %}

{# With criteria array #}
{% set links = craft.shortLinkManager.shortLinks({
    linkType: 'vanity',
    limit: 10,
    orderBy: 'shortlinkmanager.hits DESC',
}).all() %}

{# Get one by slug #}
{% set link = craft.shortLinkManager.shortLinks({slug: 'summer-sale'}).one() %}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `criteria` | `array` | Element query criteria (optional) |

**Returns:** `ShortLinkQuery` — chainable query object. Call `.one()`, `.all()`, `.count()`, etc.

See [Element queries](element-queries.md) for all available query parameters.

---

### `getAll(criteria)` @since(5.0.0)

Get all short links matching the given criteria.

```twig
{% set links = craft.shortLinkManager.getAll({
    status: 'enabled',
    limit: 20,
}) %}

{% for link in links %}
    <a href="{{ link.url }}">{{ link.code }}</a>
{% endfor %}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `criteria` | `array` | Element query criteria (optional) |

**Returns:** `ShortLink[]` — array of ShortLink elements.

---

## Getting or creating short links

### `get(options)` @since(5.0.0)

Get an existing short link, or create one if it does not exist. The behavior depends on which key is present in `options`:

```twig
{# Get by linked element (creates if not found) #}
{% set link = craft.shortLinkManager.get({element: entry}) %}

{# Get by code/slug (returns null if not found) #}
{% set link = craft.shortLinkManager.get({code: 'summer-sale'}) %}
{% set link = craft.shortLinkManager.get({slug: 'abc123'}) %}

{# Get by ID #}
{% set link = craft.shortLinkManager.get({id: 42}) %}

{# With site override #}
{% set link = craft.shortLinkManager.get({
    element: entry,
    siteId: currentSite.id,
}) %}
```

**Resolution order:**

1. If `element` is provided: looks for an existing link attached to that element. If none found, creates one automatically.
2. If `code` or `slug` is provided: looks up the link by slug.
3. If `id` is provided: looks up the link by the ShortLink's own element ID (not the linked element's ID).

| Option | Type | Description |
|--------|------|-------------|
| `element` | `ElementInterface` | Craft element to link to (get or create) |
| `code` / `slug` | `string` | Short code or slug to look up |
| `id` | `int` | ShortLink element ID |
| `siteId` | `int` | Site ID for lookup (optional) |

**Returns:** `ShortLink|null`

---

### `create(options)` @since(5.0.0)

Create a new short link.

```twig
{# Create a link for an entry #}
{% set link = craft.shortLinkManager.create({
    element: entry,
    httpCode: 302,
}) %}

{# Create a standalone link to any URL #}
{% set link = craft.shortLinkManager.create({
    url: 'https://example.com/long/path',
    linkType: 'vanity',
    code: 'launch',
    httpCode: 301,
    dateExpired: '2025-12-31',
}) %}
```

| Option | Type | Description |
|--------|------|-------------|
| `element` | `ElementInterface` | Craft element to link to |
| `url` / `destinationUrl` | `string` | Destination URL (for standalone links) |
| `code` / `slug` | `string` | Custom code (required for vanity links) |
| `linkType` | `string` | `'code'` or `'vanity'` (default: `'code'`) |
| `shortLinkType` | `string` | `'auto'` or `'manual'` (default: `'manual'`) |
| `httpCode` | `int` | HTTP redirect status (default: from settings) |
| `siteId` | `int` | Site ID (default: current site) |
| `enabled` | `bool` | Whether the link is enabled (default: `true`) |
| `dateExpired` | `string\|DateTime` | Expiry date |
| `expiredRedirectUrl` | `string` | Where to redirect when expired |
| `qrCodeEnabled` | `bool` | QR code enabled (default: `true`) |
| `qrCodeSize` | `int` | QR code size. When omitted, the new link uses the effective `defaultQrSize` setting (100–1000) |
| `qrCodeColor` | `string` | QR foreground color |
| `qrCodeBgColor` | `string` | QR background color |

**Returns:** `ShortLink|null` — `null` if creation failed (validation error or duplicate slug).

---

## Analytics

### `getAnalytics(linkId, filters)` @since(5.0.0)

Get click statistics for a specific short link.

```twig
{% set link = craft.shortLinkManager.get({element: entry}) %}

{% if link %}
    {% set stats = craft.shortLinkManager.getAnalytics(link.id) %}
    {% set stats = craft.shortLinkManager.getAnalytics(link.id, {
        dateFrom: '2024-01-01',
        dateTo: '2024-12-31',
    }) %}

    <p>Total clicks: {{ link.hits }}</p>
{% endif %}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `linkId` | `int` | ShortLink element ID |
| `filters` | `array` | Optional date range filters: `dateFrom`, `dateTo` |

**Returns:** `array` — click statistics data from `AnalyticsService::getClickStats()`.

---

## Complete example

```twig
{# Display a short link for the current entry #}
{% set link = craft.shortLinkManager.get({element: entry}) %}

{% if link %}
    <div class="shortlink-panel">
        <h3>Short Link</h3>
        <div class="shortlink-url">
            <a href="{{ link.url }}" target="_blank">{{ link.url }}</a>
            <button onclick="navigator.clipboard.writeText('{{ link.url }}')">Copy</button>
        </div>

        {% if link.qrCodeEnabled %}
            <img src="{{ link.qrCodeDataUri }}" alt="QR Code" width="{{ link.qrCodeSize }}">
        {% endif %}

        <dl>
            <dt>Status</dt><dd>{{ link.status }}</dd>
            <dt>Clicks</dt><dd>{{ link.hits }}</dd>
            {% if link.dateExpired %}
                <dt>Expires</dt><dd>{{ link.dateExpired|date('Y-m-d') }}</dd>
            {% endif %}
        </dl>
    </div>
{% else %}
    <p>No short link for this entry.</p>
{% endif %}
```
