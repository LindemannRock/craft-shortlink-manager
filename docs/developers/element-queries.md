# ShortLink Queries

ShortLink Manager registers a custom element type (`ShortLink`) that supports Craft's standard element query API. Use `ShortLink::find()` in PHP or `craft.shortLinkManager.shortLinks()` in Twig to query short links.

## Basic Queries

```twig
{# All enabled short links #}
{% set links = craft.shortLinkManager.shortLinks().all() %}

{# Get one link by code #}
{% set link = craft.shortLinkManager.shortLinks({slug: 'summer-sale'}).one() %}

{# Get the first link linked to an entry #}
{% set link = craft.shortLinkManager.shortLinks({elementId: entry.id}).one() %}

{# Count all expired links #}
{% set count = craft.shortLinkManager.shortLinks().status('expired').count() %}
```

## Query Parameters

### Standard Craft Parameters

All standard `ElementQuery` parameters work:

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | `int\|int[]` | Element ID(s) |
| `siteId` | `int\|int[]` | Filter by site |
| `status` | `string\|string[]` | `enabled`, `disabled`, `pending`, `expired` |
| `limit` | `int\|null` | Number of results |
| `offset` | `int` | Offset for pagination |
| `orderBy` | `string` | Column to sort by |

### ShortLink-Specific Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `slug` | `string\|string[]` | Short code slug(s) |
| `linkType` | `string\|string[]` | `'code'` (auto-generated) or `'vanity'` |
| `shortLinkType` | `string\|string[]` | `'auto'` (field-managed) or `'manual'` (standalone) |
| `elementId` | `int\|int[]` | Linked element ID(s) |
| `expired` | `bool` | Filter expired links (`true` / `false`) |
| `httpCode` | `int\|int[]` | Redirect status code(s) |
| `trackAnalytics` | `bool` | Filter by analytics tracking enabled |

## Status Values

| Status | Description |
|--------|-------------|
| `enabled` | Active — post date passed, not expired, element enabled |
| `disabled` | Element is disabled |
| `pending` | Post date is in the future |
| `expired` | Expiry date has passed |

```twig
{# Get all expired links #}
{% set expired = craft.shortLinkManager.shortLinks().status('expired').all() %}

{# Get links expiring soon (use PHP for date logic) #}
{% set links = craft.shortLinkManager.shortLinks().status(['enabled', 'expired']).all() %}
```

## Filtering by Link Type

```twig
{# Only auto-generated links #}
{% set autoLinks = craft.shortLinkManager.shortLinks({linkType: 'code'}).all() %}

{# Only vanity URLs #}
{% set vanityLinks = craft.shortLinkManager.shortLinks({linkType: 'vanity'}).all() %}
```

## Filtering by Linked Element

```twig
{# Get the short link for this entry #}
{% set link = craft.shortLinkManager.shortLinks({
    elementId: entry.id,
    siteId: currentSite.id,
}).one() %}
```

## Ordering

```twig
{# Most clicked first #}
{% set topLinks = craft.shortLinkManager.shortLinks()
    .orderBy('shortlinkmanager.hits DESC')
    .limit(10)
    .all() %}

{# Expiring soonest first #}
{% set expiringSoon = craft.shortLinkManager.shortLinks()
    .status('enabled')
    .orderBy('shortlinkmanager.dateExpired ASC')
    .limit(5)
    .all() %}
```

Available order columns: `slug`, `shortlinkmanager.hits`, `shortlinkmanager.dateExpired`, `shortlinkmanager.postDate`, `elements.dateCreated`, `elements.dateUpdated`, `elements.id`.

## Pagination

```twig
{% set query = craft.shortLinkManager.shortLinks().orderBy('elements.dateCreated DESC') %}
{% set pageInfo = craft.app.request.getParam('p') %}

{% paginate query.limit(20) as pageInfo, links %}
    {% for link in links %}
        <div>{{ link.url }}</div>
    {% endfor %}

    {% if pageInfo.prevUrl %}<a href="{{ pageInfo.prevUrl }}">Previous</a>{% endif %}
    {% if pageInfo.nextUrl %}<a href="{{ pageInfo.nextUrl }}">Next</a>{% endif %}
{% endpaginate %}
```

## ShortLink Element Properties

Properties available on a `ShortLink` element in Twig:

| Property | Type | Description |
|----------|------|-------------|
| `id` | `int` | Element ID |
| `authorId` | `int\|null` | Author user ID |
| `code` | `string` | The short code (as entered) |
| `slug` | `string` | The URL slug (sanitized code) |
| `linkType` | `string` | `'code'` or `'vanity'` |
| `shortLinkType` | `string` | `'auto'` or `'manual'` |
| `destinationUrl` | `string` | The redirect destination (site-specific) |
| `expiredRedirectUrl` | `string\|null` | Where to redirect when expired |
| `expiredMessage` | `string\|null` | Custom expired message |
| `elementId` | `int\|null` | Linked element ID |
| `elementType` | `string\|null` | Linked element class |
| `dateExpired` | `DateTime\|null` | Expiry date |
| `postDate` | `DateTime\|null` | Publish date |
| `httpCode` | `int` | Redirect status code |
| `trackAnalytics` | `bool` | Whether analytics is tracked |
| `passQueryParams` | `bool\|null` | Pass query params override |
| `directRedirect` @since(5.12.0) | `bool\|null` | Direct redirect override |
| `hits` | `int` | Total click count |
| `qrCodeEnabled` | `bool` | QR code enabled |
| `qrCodeSize` | `int` | QR code size in pixels |
| `qrCodeColor` | `string\|null` | QR foreground color |
| `qrCodeBgColor` | `string\|null` | QR background color |
| `qrCodeEyeColor` | `string\|null` | QR eye color |
| `qrCodeFormat` | `string\|null` | QR format override |
| `qrLogoId` | `int\|null` | QR logo asset ID (overrides default) |
| `status` | `string` | Current status |
| `url` | `string` | The full short link URL |

## ShortLink Element Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getUrl()` | `string` | Full public short link URL |
| `getQrCodeUrl(options)` | `string` | URL to the raw QR image |
| `getQrCodeDisplayUrl(options)` @since(5.1.0) | `string` | URL to the QR display page |
| `getQrCodeDataUri(options)` | `string` | Base64 data URI for inline embedding |
| `getQrCode(options)` | `string` | Raw QR code binary data |
| `getLinkedElement()` | `ElementInterface\|null` | The linked Craft element |
| `getAuthor()` | `User\|null` | The author user |
| `isExpired()` | `bool` | Whether the link is expired |
| `getAnalytics(filters)` | `array` | Click statistics |
| `renderSeomaticTracking(eventType)` @since(5.1.0) | `Markup\|null` | SEOmatic tracking HTML |

## PHP Example

```php
use lindemannrock\shortlinkmanager\elements\ShortLink;

// All enabled links
$links = ShortLink::find()->status('enabled')->all();

// Get link by slug
$link = ShortLink::find()->slug('summer-sale')->one();

// Top 10 most clicked
$topLinks = ShortLink::find()
    ->orderBy('shortlinkmanager.hits DESC')
    ->limit(10)
    ->all();
```
