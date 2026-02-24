# Short Links

Short links are the core element of ShortLink Manager. Every short link has a short code that maps to a destination URL and redirects visitors with your chosen HTTP status code.

## Link Types

ShortLink Manager has two link types:

| Type | How the code is created | Example |
|------|------------------------|---------|
| **Auto-generated** | Random alphanumeric code (length controlled by `codeLength`) | `/s/abc123XY` |
| **Vanity URL** | You provide a human-readable code | `/s/summer-sale` |

The link type is set when creating a short link and cannot be changed afterwards.

## Creating Short Links

### From the Control Panel

Go to **ShortLink Manager → New Link**.

**Required fields:**

- **Title / Code** — The short code. For auto-generated links, leave blank and one will be generated. For vanity URLs, enter your custom code.
- **Destination** — The URL visitors are redirected to. Enter any full URL (e.g., `https://example.com/long-path`) or a relative path (e.g., `/products`). Alternatively, link to a Craft element — the destination URL syncs automatically when that element's URL changes.

**Optional fields:**

- **HTTP Code** — Redirect status. `301` (permanent), `302` (temporary), `307` (temp, method preserved), `308` (perm, method preserved). Defaults to the `defaultHttpCode` setting.
- **Pass Query Params** — When enabled, query parameters appended to the short URL are forwarded to the destination. Overrides the global `passQueryParams` setting. Set to null to use the global setting.
- **Direct Redirect** @since(5.12.0) — Bypass the redirect template for an immediate server-side HTTP redirect. Overrides the global `directRedirect` setting. See [Direct Redirect](direct-redirect.md).
- **Track Analytics** — Whether click tracking is recorded for this link. Enabled by default.
- **Post Date** — When the link becomes active. Visitors clicking before this date get redirected to `notFoundRedirectUrl`.
- **Expiry Date** — When the link stops working. Expired links redirect to the expired template (or `expiredRedirectUrl` if set).
- **Expired Redirect URL** — Where to send visitors when this specific link has expired. Overrides the global expired template.
- **Expired Message** — Custom message shown on the expired page. Overrides the global `expiredMessage` setting.

### Programmatically

Create a short link from PHP or Twig using the `ShortLinksService` or the Twig variable. See [Template Variables](../developers/template-variables.md) for the Twig API.

## Link Statuses

| Status | Meaning |
|--------|---------|
| **Enabled** | Active and redirecting visitors |
| **Disabled** | Not active — visitors are sent to `notFoundRedirectUrl` |
| **Pending** | Post date is in the future — not yet active |
| **Expired** | Expiry date has passed — shows expired template or `expiredRedirectUrl` |

Statuses can be changed in bulk from the element index.

## Short Link URL Structure

Short links follow this pattern:

```
{siteBaseUrl}/{slugPrefix}/{code}
```

With default settings (`slugPrefix = 's'`):

```
https://example.com/s/abc123XY
```

When a custom short domain is set:

```
https://short.example.com/s/abc123XY
```

The `slugPrefix` can be any alphanumeric string (e.g., `go`, `l`, `link`). See [Custom Domain](custom-domain.md) for domain configuration.

## Code Uniqueness

Codes are stored as slugs (lowercased, spaces replaced with hyphens). A code `Summer Sale` becomes slug `summer-sale`. If a slug already exists, the CP shows a validation error.

Reserved codes (configurable via `reservedCodes`) cannot be used as vanity codes: `admin`, `api`, `login`, `logout`, `cp`, `dashboard`, `settings`.

## Vanity Code Rules

Vanity codes support letters, numbers, underscores, and hyphens. Spaces are allowed and converted to hyphens in the URL. Maximum length is not enforced by the plugin, but keep codes short for usability.

## Linking to Craft Elements

Instead of entering a destination URL manually, you can link a short link to any Craft element (entry, product, category, etc.). When the linked element's URL changes (slug update, section change, etc.), the short link's destination URL updates automatically.

To link to a Craft element, use the element picker in the edit screen. The destination URL field is populated automatically and kept in sync.

For multisite setups, each site's destination URL resolves from the linked element's URL on that site. This means a single short code can redirect to different URLs per site.

## Editing Short Links in the Element Sidebar

When ShortLink Manager is enabled for a site, an entry's edit screen shows a sidebar panel with its attached short link (if any). This panel displays the short URL, copy button, and QR code preview — without leaving the entry editor.

## Bulk Actions

From the element index, select links and use the action menu:

- **Enable / Disable** — Toggle active status
- **Delete** — Permanently delete selected links
- **Duplicate** — Clone links with new auto-generated codes

## Sorting and Filtering

The element index supports sorting by:
- Code/Slug
- Interactions (click count)
- Expiry Date
- Date Created
- Date Updated

Filter by status using the status toolbar (All, Enabled, Disabled, Pending, Expired).

Three link sources appear in the sidebar: **All Links**, **Auto-generated**, **Vanity URLs**.
