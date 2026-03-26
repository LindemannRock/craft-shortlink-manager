# Features Overview

ShortLink Manager is a full-featured URL shortener for Craft CMS with built-in QR code generation, click analytics, and integrations for Redirect Manager and SEOmatic.

> [!TIP]
> New to ShortLink Manager? Start with [Installation](../get-started/installation.md), then come back here for a tour of the features.

## What It Does

ShortLink Manager creates short, memorable URLs that redirect visitors to any destination. Every short link also gets a QR code. Clicks are tracked with device, location, and referrer data. The plugin integrates into Craft's native element system, so short links behave like any other element — they support statuses, multi-site, field layouts, and element queries.

## Core Capabilities

- **[Short Links](shortlinks.md)** — Create auto-generated codes (`/s/abc123XY`) or vanity URLs (`/s/summer-sale`). Set per-link HTTP codes (301, 302, 307, 308), expiry dates, and post dates. Link directly to Craft elements or any URL.

- **[QR Codes](qr-codes.md)** — Every short link gets a QR code. Customize size, colors, module style (square, rounded, dots), eye style, and logo overlay. Download as PNG or SVG.

- **[Analytics](analytics.md)** — Click tracking with device detection (desktop, tablet, mobile), geolocation (country, city), referrer, and unique visitor counting via IP hashing. Dashboard widgets for at-a-glance stats.

- **[Custom Domain](custom-domain.md)** — Serve all short links from a dedicated domain (e.g., `https://short.example.com`) with site-aware URL patterns for multisite setups.

- **[Direct Redirect](direct-redirect.md)** — Bypass the redirect template for maximum performance. Can be set globally or overridden per link. Analytics still work.

- **[ShortLink Field](shortlink-field.md)** — A custom field type that attaches a short link directly to any element. The destination URL syncs automatically when the element URL changes.

- **[Integrations](integrations.md)** — SEOmatic pushes GTM/GA4 data layer events on redirect and QR scan. Redirect Manager auto-creates 301s when slugs change. Craft Link Field lets editors pick short links in any Link field.

- **Folders & Tags** — Organize short links using plugin-internal folders (one per link) and tags (many per link). Manage them at **ShortLink Manager → Folders & Tags**. Use bulk actions on the element index to assign or clear folders and tags across multiple links at once.

- **Import / Export** — Bulk-import short links from CSV via **ShortLink Manager → Import/Export**. Map columns in the browser before committing, then review a per-row preview (valid, duplicate, error) before importing. Export all short links to CSV at any time. Import history is logged for auditing.

## Dashboard Widgets

ShortLink Manager registers two Craft dashboard widgets. Add them via **Dashboard → New Widget**.

| Widget | Description |
|--------|-------------|
| **ShortLink Manager - Analytics** | Click totals, unique visitors, top referrers for a configurable date range |
| **ShortLink Manager - Top Links** | Most-clicked links for a date range. Configurable limit (1–20) |

Both widgets require the `shortLinkManager:viewAnalytics` permission.

## CP Utility

ShortLink Manager adds a utility at **Utilities → ShortLink Manager** with system stats (total links, active, pending, expired) and maintenance actions (clear cache, cleanup analytics).

## Element Index

The main element index at **ShortLink Manager** lists all short links with sortable columns for Code, Type, Destination, Status, Interactions, and Date Created. Filter by status, link type, or search by code or destination URL.

**Bulk actions:** Enable, Disable, Delete, Duplicate, Set Folder, Clear Folder, Add Tags, Remove Tags, Clear Tags.

**Link sources:** All Links, Auto-generated, Vanity URLs.

## Multi-Site Support

Short links are localized. Each site can have its own destination URL for the same short code, which is especially useful with the ShortLink Field — when an entry is saved, each site's destination URL is resolved from that site's entry URL automatically.

The `enabledSites` setting controls which sites have short link support. An empty array enables all sites.

## Next Steps

1. [Install the plugin](../get-started/installation.md)
2. [Configure it](../get-started/configuration.md)
3. [Create your first short link](shortlinks.md)
4. [Set up QR codes](qr-codes.md)
5. [Enable analytics](analytics.md)
