# Short links

Shorten any URL in the Control Panel — no code required. Every short link gets a code, a redirect, optional expiry, and click tracking out of the box.

## What you'll use it for

- Share short, readable URLs in emails, social posts, or print — instead of long slugs nobody types.
- Create vanity links for campaigns: `/s/summer-sale` instead of `/s/abc123XY`.
- Attach a short link to an entry, category, asset, or Commerce product so its destination stays in sync when the element URL changes.
- Schedule links with post and expiry dates: a Black Friday link that goes live at midnight and expires the next day.
- Track per-link clicks with analytics, or disable tracking for specific links.

## Create your first short link

Go to **ShortLink Manager → New ShortLink** in the Control Panel.

![New short link form showing code, destination, and optional fields](images/shortlinks-new-link.webp)

**Required fields:**

- **Title / Code** — the short code. Leave blank for an auto-generated code, or type your own vanity code.
- **Destination** — the URL visitors are redirected to. Enter any full URL (e.g., `https://example.com/long-path`) or a relative path (e.g., `/products`). Alternatively, pick a Craft element — the destination URL then stays in sync with that element.

**Optional fields:**

- **HTTP Code** — redirect status: `301` (permanent), `302` (temporary), `307` (temp, method preserved), `308` (perm, method preserved). Defaults to the `defaultHttpCode` setting (`302` by default).
- **Pass Query Params** — when enabled, query parameters appended to the short URL are forwarded to the destination. Overrides the global `passQueryParams` setting. Set to `null` to use the global setting.
- **Direct Redirect** @since(5.12.0) — bypass the redirect template for an immediate server-side HTTP redirect. Overrides the global `directRedirect` setting. Fastest path, but repeat-hit analytics can be affected by browser/CDN/static caching because the short URL request itself becomes the tracked redirect request. See [Direct Redirect](direct-redirect.md).
- **Track Analytics** — whether click tracking is recorded for this link. Enabled by default.
- **Post Date** — when the link becomes active. Visitors clicking before this date are sent to `notFoundRedirectUrl`.
- **Expiry Date** — when the link stops working. Expired links redirect to the expired template (or `expiredRedirectUrl` if set).
- **Expired Redirect URL** — where to send visitors when this specific link has expired. Overrides the global expired template.
- **Expired Message** — custom message shown on the expired page. Overrides the global `expiredMessage` setting.
- **Folder** — assign the link to an organizational folder (one folder per link). Folders are managed at **ShortLink Manager → Folders & Tags**.
- **Tags** — assign one or more tags to the link (comma-separated). Tags are managed at **ShortLink Manager → Folders & Tags**.

The entry sidebar also shows an attached short link without leaving the editor:

![ShortLink Manager sidebar panel on an entry edit screen, showing the short URL, copy button, and QR code preview](images/shortlinks-element-sidebar.webp)

---

## Link types

ShortLink Manager has two link types:

| Type | How the code is created | Example |
|------|------------------------|---------|
| **Auto-generated** | Random alphanumeric code (length controlled by `codeLength`) | `/s/abc123XY` |
| **Vanity URL** | You provide a human-readable code | `/s/summer-sale` |

The link type is set when creating a short link and cannot be changed afterwards.

## Link statuses

| Status | Meaning |
|--------|---------|
| **Enabled** | Active and redirecting visitors |
| **Disabled** | Not active — visitors are sent to `notFoundRedirectUrl` |
| **Pending** | Post date is in the future — not yet active |
| **Expired** | Expiry date has passed — shows expired template or `expiredRedirectUrl` |

Statuses can be changed in bulk from the element index.

## Short link URL structure

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

## Code uniqueness and vanity rules

Codes are stored as slugs (lowercased, spaces replaced with hyphens). A code `Summer Sale` becomes slug `summer-sale`. If a slug already exists, the CP shows a validation error.

Vanity codes support letters, numbers, underscores, and hyphens. Spaces are allowed and converted to hyphens in the URL. Maximum length is not enforced by the plugin, but keep codes short for usability.

Reserved codes (configurable via `reservedCodes`) cannot be used as vanity codes: `admin`, `api`, `login`, `logout`, `cp`, `dashboard`, `settings`.

## Linking to Craft elements

Instead of entering a destination URL manually, you can link a short link to a Craft element. The Control Panel picker supports entries, categories, and assets. When Craft Commerce is installed, it also supports products and variants. When the linked element's URL changes (slug update, section change, product URL update, etc.), the short link's destination URL updates automatically.

To link to a Craft element, use the element picker in the edit screen. The destination URL field is populated automatically and kept in sync.

For multisite setups, each site's destination URL resolves from the linked element's URL on that site. This means a single short code can redirect to different URLs per site.

## Bulk actions

From the element index, select links and use the action menu:

- **Enable / Disable** — toggle active status
- **Delete** — permanently delete selected links
- **Duplicate** — clone links with new auto-generated codes
- **Set Folder** — move selected links into a chosen folder (replaces any existing folder assignment)
- **Clear Folder** — remove the folder assignment from selected links
- **Add Tags** — append tags to selected links without removing existing tags
- **Remove Tags** — remove specific tags from selected links
- **Clear Tags** — remove all tags from selected links

## Sorting and filtering

The element index supports sorting by:
- Code/Slug
- Interactions (click count)
- Expiry Date
- Date Created
- Date Updated

Filter by status using the status toolbar (All, Enabled, Disabled, Pending, Expired).

Three link sources appear in the sidebar: **All Links**, **Auto-generated**, **Vanity URLs**.

---

## Creating short links programmatically

Create a short link from PHP or Twig using `ShortLinksService` or the Twig variable. See [Template Variables](../developers/template-variables.md) for the Twig API.
