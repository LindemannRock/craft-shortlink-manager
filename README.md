# ShortLink Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-shortlink-manager.svg)](https://packagist.org/packages/lindemannrock/craft-shortlink-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0%2B-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.0%2B-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-shortlink-manager.svg)](LICENSE)

Advanced shortlink management with QR codes and analytics for Craft CMS.

## License

This is a commercial plugin licensed under the [Craft License](https://craftcms.github.io/license/). It will be available on the [Craft Plugin Store](https://plugins.craftcms.com) soon. See [LICENSE.md](LICENSE.md) for details.

## ⚠️ Pre-Release

This plugin is in active development and not yet available on the Craft Plugin Store. Features and APIs may change before the initial public release.

## Features

- **Short Links** — Custom element type with auto-generated codes or vanity slugs (e.g., `/s/abc123`, `/s/pricing`)
- **QR Codes** — Styled QR codes with custom colors, module/eye styles, logo overlay, and PNG/SVG export
- **Analytics** — Click tracking with device, browser, OS, country, city, referrer, and bot filtering
- **Direct Redirect** — Optional server-side HTTP redirect for maximum performance
- **Link Expiration** — Expiry dates with custom expired message or redirect URL
- **Query Pass-Through** — Forward query parameters from shortlink to destination
- **Integrations** — SEOmatic (GTM/GA4 events), Redirect Manager (auto-301 on slug change)
- **ShortLink Field** — Custom field type for attaching shortlinks to entries
- **Multi-Site** — Per-site destination URLs, optional custom domain
- **Dashboard Widgets** — Analytics Summary and Top Links widgets

## Requirements

- Craft CMS 5.0+
- PHP 8.2+
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.0+ — optional, install in CP for logs
- [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode) ^2.0

## Installation

### Via Composer

```bash
composer require lindemannrock/craft-shortlink-manager
```

```bash
php craft plugin/install shortlink-manager
```

```bash
php craft shortlink-manager/security/generate-salt
```

### Using DDEV

```bash
ddev composer require lindemannrock/craft-shortlink-manager
```

```bash
ddev craft plugin/install shortlink-manager
```

```bash
ddev craft shortlink-manager/security/generate-salt
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
