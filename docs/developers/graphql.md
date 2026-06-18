# GraphQL @since(5.21.0)

ShortLink Manager exposes GraphQL queries for headless and SPA frontends that need to resolve shortlink codes or list enabled shortlinks for a site.

GraphQL is controlled by Craft's GraphQL schema permissions. There is no plugin-level toggle. Enable the Query ShortLink Manager data schema permission on the GraphQL schema used by your frontend token.

## Schema Permission

| Scope | Purpose |
|-------|---------|
| `shortlinkManager.all:read` | Allows ShortLink Manager GraphQL queries |

## Resolve a Shortlink

Use `shortlinkManagerResolveShortlink` when a frontend needs to resolve a code and perform the same tracking side effects as a real shortlink request.

```graphql
query ResolveShortlink($code: String!, $site: String, $queryString: String) {
  shortlinkManagerResolveShortlink(code: $code, site: $site, queryString: $queryString) {
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
  "site": "default",
  "queryString": "utm_source=app"
}
```

You can pass either `site` or `siteId`. If both are present, `site` wins. Invalid explicit site handles or IDs return no result instead of falling back to another site.

This query behaves like a real shortlink hit:

- A matched, enabled shortlink increments `hits`
- A matched, enabled shortlink records analytics with `source = graphql` when analytics is enabled
- `queryString` is merged into `resolvedDestinationUrl` only when the shortlink or global setting passes query parameters
- Disabled and pending shortlinks return `null`
- Expired shortlinks return the shortlink data with `resolvedDestinationUrl` set to the expired redirect URL when one exists

Because this query intentionally has hit-count and analytics side effects, ShortLink Manager disables Craft's GraphQL result cache for operations that include `shortlinkManagerResolveShortlink`.

## List Shortlinks

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

The list query returns enabled shortlinks for the requested site. It does not increment `hits` and does not write analytics.

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | Shortlink ID |
| `site` | `String` | Site handle |
| `siteId` | `Int` | Site ID |
| `title` | `String` | Shortlink title |
| `code` | `String` | Public short code |
| `slug` | `String` | Normalized slug |
| `linkType` | `String` | `code` or `vanity` |
| `shortLinkType` | `String` | `manual` or `auto` |
| `url` | `String` | Public shortlink URL |
| `qrCodeUrl` | `String` | Public QR code URL |
| `destinationUrl` | `String` | Configured destination URL |
| `resolvedDestinationUrl` | `String` | Destination selected for the current resolve/list response |
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
