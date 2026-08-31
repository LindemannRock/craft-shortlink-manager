![ShortLink Manager](docs/images/hero.webp)

# ShortLink Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-shortlink-manager.svg)](https://packagist.org/packages/lindemannrock/craft-shortlink-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.10%2B-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.18.2%2B-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-shortlink-manager.svg)](LICENSE.md)

Advanced shortlink management with QR codes and analytics for Craft CMS.

## Features

- **Short Links** — Custom element type with auto-generated codes or vanity slugs (e.g., `/s/abc123`, `/s/pricing`)
- **QR Codes** — Canonical public QR codes with custom colors, module/eye styles, logo overlay, and authenticated PNG/SVG export
- **Analytics** — Click tracking with device, browser, OS, country, city, referrer, and bot filtering
- **Direct Redirect** — Optional server-side HTTP redirect for maximum performance
- **Link Expiration** — Expiry dates with custom expired message or redirect URL
- **Element Destinations** — Link directly to entries, categories, assets, and optional Commerce products/variants
- **Query Pass-Through** — Forward query parameters from shortlink to destination
- **GraphQL** — Resolve/list shortlinks and expose field-managed links for headless or SPA frontends
- **Integrations** — SEOmatic (Content SEO source and GTM/GA4 events), Redirect Manager (auto-301 on slug change)
- **ShortLink Field** — Custom field type for attaching shortlinks to entries
- **Custom Fields** — Add editor-managed fields to ShortLink elements via a configurable field layout
- **Multi-Site** — Per-site destination URLs, optional custom domain
- **Dashboard Widgets** — Analytics Summary and Top Links widgets
- **Folders & Tags** — Organize short links with plugin-internal folders and tags; bulk-assign via element index actions
- **Import / Export** — Bulk-import from CSV with column mapping and row-level preview; export all links to CSV

## Requirements

- Craft CMS 5.10+
- PHP 8.2+
- [Base](https://github.com/LindemannRock/craft-plugin-base) 5.38.2+ (required)
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.18.2+ (required by Composer; install in CP for log viewing)
- [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode) ^3.0

## Installation

### Via Composer

```bash
composer require lindemannrock/craft-shortlink-manager && php craft plugin/install shortlink-manager && php craft shortlink-manager/security/generate-salt
```

### Using DDEV

```bash
ddev composer require lindemannrock/craft-shortlink-manager && ddev craft plugin/install shortlink-manager && ddev craft shortlink-manager/security/generate-salt
```

## Documentation

Full documentation is available in the [docs](docs/) folder.

## Support

- **Issues**: [GitHub Issues](https://github.com/LindemannRock/craft-shortlink-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/). See [LICENSE.md](LICENSE.md) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)
