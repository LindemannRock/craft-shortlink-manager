# GraphQL @since(5.21.0)

Use ShortLink Manager from a headless frontend without building a separate API. GraphQL can resolve a shortlink the same way a browser hit would, list enabled shortlinks for a site, and expose field-managed shortlinks directly from entry queries.

There are three GraphQL surfaces:

- `shortlinkManagerResolveShortlink` for SPA route handling and analytics-aware resolution
- `shortlinkManagerShortlinks` for read-only lists of enabled shortlinks
- field output for the ShortLink Manager field and Craft's native Link field integration

## Before you query

GraphQL access is controlled by Craft schemas. ShortLink Manager does not have a separate GraphQL toggle.

Enable these on the schema used by your frontend token:

| Area | Required access |
|---|---|
| Sites | The site the frontend queries, for example `en` |
| ShortLink Manager | `Query ShortLink Manager data` |
| Entries | Only needed when querying entries that contain ShortLink fields or Craft Link fields |

The ShortLink Manager scope is:

| Scope | Purpose |
|---|---|
| `shortlinkManager.all:read` | Allows ShortLink Manager GraphQL queries |

For public frontend requests, use the GraphQL token for a schema that has the site and ShortLink Manager permission enabled. A logged-in Control Panel user may see different results in GraphiQL than an external client using a token.

## Resolve a shortlink

Use `shortlinkManagerResolveShortlink` when a frontend receives a short code and needs the destination URL.

At minimum, pass the code you want to resolve:

```graphql
{
  shortlinkManagerResolveShortlink(code: "summer-sale") {
    resolvedDestinationUrl
  }
}
```

You can also pass a site ID:

```graphql
{
  shortlinkManagerResolveShortlink(code: "summer-sale", siteId: 1) {
    resolvedDestinationUrl
  }
}
```

Or pass a site handle:

```graphql
{
  shortlinkManagerResolveShortlink(code: "summer-sale", site: "en") {
    resolvedDestinationUrl
  }
}
```

```graphql
query ResolveShortlink($code: String!, $site: String, $queryString: String) {
  shortlinkManagerResolveShortlink(
    code: $code
    site: $site
    queryString: $queryString
  ) {
    id
    code
    slug
    url
    destinationUrl
    resolvedDestinationUrl
    httpCode
    status
    site
    siteId
    hits
  }
}
```

Variables:

```json
{
  "code": "summer-sale",
  "site": "en",
  "queryString": "utm_source=app"
}
```

You can pass either `site` or `siteId`. If both are present, `site` wins. Invalid explicit site handles or IDs return no result instead of falling back to another site.

When no shortlink matches, `null` is returned:

```json
{
  "data": {
    "shortlinkManagerResolveShortlink": null
  }
}
```

When a shortlink matches, the requested fields are returned:

```json
{
  "data": {
    "shortlinkManagerResolveShortlink": {
      "resolvedDestinationUrl": "https://example.com/summer-sale"
    }
  }
}
```

This query behaves like a real shortlink hit:

- Matched, enabled shortlinks increment `hits`
- Matched, enabled shortlinks record analytics with `source = graphql` when analytics is enabled
- `queryString` is merged into `resolvedDestinationUrl` only when the shortlink or global setting passes query parameters
- `src`, `debug`, and `p` are removed from the passed query string before merging
- Disabled and pending shortlinks return `null`
- Expired shortlinks return the shortlink data with `resolvedDestinationUrl` set to the expired redirect URL when one exists

Because this query intentionally has hit-count and analytics side effects, ShortLink Manager disables Craft's GraphQL result cache for operations that include `shortlinkManagerResolveShortlink`.

### Arguments

```graphql
shortlinkManagerResolveShortlink(
  code: "summer-sale"
  siteId: 1
  site: "en"
  queryString: "utm_source=app"
)
```

| Argument | Type | Required | Description |
|---|---|---|---|
| `code` | `String` | Yes | Shortlink code or slug to resolve |
| `siteId` | `Int` | No | Site ID to resolve against |
| `site` | `String` | No | Site handle to resolve against |
| `queryString` | `String` | No | Query string from the original frontend URL |

## List shortlinks

Use `shortlinkManagerShortlinks` when a frontend needs a read-only list of enabled shortlinks for a site.

```graphql
query Shortlinks($siteId: Int, $limit: Int) {
  shortlinkManagerShortlinks(siteId: $siteId, limit: $limit) {
    id
    code
    slug
    url
    destinationUrl
    resolvedDestinationUrl
    qrCodeUrl
    status
    site
    siteId
  }
}
```

The list query:

- returns enabled shortlinks for the requested site
- accepts `site` or `siteId`
- defaults `limit` to 100 when omitted, and caps it at 500
- does not increment `hits`
- does not write analytics

### Arguments

```graphql
shortlinkManagerShortlinks(siteId: 1, site: "en", limit: 20)
```

| Argument | Type | Required | Description |
|---|---|---|---|
| `siteId` | `Int` | No | Site ID to list shortlinks for |
| `site` | `String` | No | Site handle to list shortlinks for |
| `limit` | `Int` | No | Maximum number of shortlinks to return. Defaults to 100 when omitted; capped at 500 |

## Query a ShortLink field

The ShortLink Manager field resolves to the same shortlink object type used by the plugin queries. This is read-only field output and does not increment hits or write analytics.

```graphql
query EntryShortlink {
  entries(section: "mySection", site: "en", limit: 1) {
    title
    ... on mySection_Entry {
      myShortLinkField {
        id
        code
        slug
        url
        destinationUrl
        resolvedDestinationUrl
        qrCodeUrl
        shortLinkType
        hits
      }
    }
  }
}
```

Replace `mySection`, `mySection_Entry`, and `myShortLinkField` with your section handle, concrete entry GraphQL type, and ShortLink Manager field handle.

For field-managed auto shortlinks, the field resolves the generated shortlink linked to the source entry and site. That means entry GraphQL still returns the shortlink object even when the stored field value is empty.

## Query a Craft Link field

ShortLink Manager also integrates with Craft's native Link field. When a Craft Link field allows ShortLink Manager links and GraphQL mode is set to full data, Craft exposes its standard `LinkData` object. The selected ShortLink element is available through the nested `shortLink` field.

```graphql
query EntryNativeLinkField {
  entries(section: "mySection", site: "en", limit: 1) {
    title
    ... on mySection_Entry {
      myLinkField {
        type
        value
        label
        defaultLabel
        url
        link
        target
        title
        class
        ariaLabel
        elementId
        elementSiteId
        elementTitle
        shortLink {
          id
          title
          code
          slug
          url
          destinationUrl
          hits
        }
      }
    }
  }
}
```

Replace `mySection`, `mySection_Entry`, and `myLinkField` with your section handle, concrete entry GraphQL type, and Craft Link field handle.

The top-level fields are Craft's standard `LinkData` output:

- `label`, `target`, `title`, `class`, `ariaLabel`, and URL suffix values come from the Craft Link field value saved on the entry
- `url` includes the Link field URL suffix
- `link` is Craft's rendered anchor tag
- `shortLink` returns ShortLink Manager's shortlink object for frontend code that needs shortlink-specific data

If full GraphQL data is not enabled for the Craft Link field, Craft exposes the field as its default string value instead of the object shape above.

## Field reference

The ShortLink Manager object exposes these fields in resolver queries, list queries, ShortLink field output, and nested Craft Link field output.

| Field | Type | Description |
|---|---|---|
| `id` | `Int` | Shortlink ID |
| `site` | `String` | Site handle |
| `siteId` | `Int` | Site ID |
| `title` | `String` | Shortlink title |
| `code` | `String` | Public short code |
| `slug` | `String` | Normalized slug |
| `linkType` | `String` | `code` or `vanity` |
| `shortLinkType` | `String` | `manual` or `auto` |
| `url` | `String` | Public shortlink URL |
| `qrCodeUrl` | `String` | Canonical public QR image URL using saved/default styling |
| `destinationUrl` | `String` | Configured destination URL |
| `resolvedDestinationUrl` | `String` | Destination selected for the current response |
| `expiredRedirectUrl` | `String` | Expired redirect URL |
| `expiredMessage` | `String` | Expired message when no redirect URL is configured |
| `status` | `String` | Shortlink status |
| `httpCode` | `Int` | HTTP status code |
| `enabled` | `Boolean` | Whether the shortlink is enabled |
| `trackAnalytics` | `Boolean` | Whether per-link analytics tracking is enabled |
| `passQueryParams` | `Boolean` | Per-link query parameter override, or `null` to use the global setting |
| `directRedirect` | `Boolean` | Per-link direct redirect override, or `null` to use the global setting |
| `hits` | `Int` | Number of matched hits |
| `dateExpired` | `String` | Expiry datetime |

## Troubleshooting

### `Cannot query field "entries" on type "Query"`

The schema does not allow entry queries. Enable the relevant entry section on the GraphQL schema, then retry a small query such as:

```graphql
query {
  entries(section: "mySection", site: "en", limit: 1) {
    title
  }
}
```

### `Schema doesn't have access to the site`

Enable the requested site on the GraphQL schema. This applies to both token-based requests and the public/default schema.

### `Cannot query field "myField" on type "EntryInterface"`

Custom fields usually live on the concrete entry type, not the generic `EntryInterface`. First query `__typename`, then use an inline fragment.

```graphql
query {
  entries(section: "mySection", site: "en", limit: 1) {
    __typename
    title
  }
}
```

Then replace `mySection_Entry` in the examples with the returned `__typename`.

### A Craft Link field returns a string

Set the Craft Link field's GraphQL mode to full data. In URL-only mode, Craft returns the rendered URL/string shape for backwards compatibility.

### A ShortLink field returns `null`

Check that the source entry has been saved and that a field-managed shortlink exists for the entry and site. For auto-generated field-managed links, the stored field value can be empty while the linked shortlink still exists.
