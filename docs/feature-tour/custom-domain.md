# Custom Domain

By default, ShortLink Manager generates short link URLs using each site's base URL. You can override this with a dedicated custom domain. Configuration differs depending on whether you have a single site or a multisite setup.

## Single-Site URLs

Use `shortlinkBaseUrl` to serve all short links from your custom domain:

```php
// config/shortlink-manager.php
use craft\helpers\App;

return [
    '*' => [
        'shortlinkBaseUrl' => App::env('SHORTLINK_BASE_URL'),
    ],
];
```

```bash
# .env
SHORTLINK_BASE_URL=https://short.example.com
```

With this configuration, a link with code `abc123` generates URLs like:

```
https://short.example.com/s/abc123
https://short.example.com/s/qr/abc123
```

This overrides the site's own base URL when generating shortlink URLs, but does **not** require a separate Craft site. Your existing Craft site handles the routing — `shortlinkBaseUrl` only changes what URL is displayed and encoded in QR codes.

## Multisite Site-Aware URLs

For a Craft multisite where each site needs its own URL path segment, use `shortlinkBaseUrlPattern` with a site token:

```php
// config/shortlink-manager.php
return [
    '*' => [
        'shortlinkBaseUrlPattern' => App::env('SHORTLINK_BASE_URL_PATTERN'),
    ],
];
```

```bash
# .env
SHORTLINK_BASE_URL_PATTERN=https://short.example.com/{siteHandle}
```

**Supported tokens:**

| Token | Replaced with |
|-------|--------------|
| `{siteHandle}` | The site's handle (e.g., `en`, `de`) |
| `{siteId}` | The site's numeric ID |
| `{siteUid}` | The site's UID |

With the pattern `https://short.example.com/{siteHandle}`, links generate URLs like:

- English site: `https://short.example.com/en/s/abc123`
- German site: `https://short.example.com/de/s/abc123`

> [!NOTE]
> `shortlinkBaseUrlPattern` takes precedence over `shortlinkBaseUrl` when both are set.

## Site-Aware Routes

When a `{siteHandle}` token is present in `shortlinkBaseUrlPattern`, ShortLink Manager automatically registers site-aware routes in addition to the standard routes:

| Route | Controller |
|-------|-----------|
| `/{slugPrefix}/{code}` | Redirect controller |
| `/{siteHandle}/{slugPrefix}/{code}` | Redirect controller (site-aware) |
| `/{qrPrefix}/{code}` | QR code controller |
| `/{siteHandle}/{qrPrefix}/{code}` | QR code controller (site-aware) |

The site-aware routes allow the redirect controller to resolve which Craft site to look up the short link in, based on the `{siteHandle}` in the URL path.

## How URLs Are Built

The `Settings::buildPublicUrl()` method resolves the correct base URL in this order:

1. If `shortlinkBaseUrlPattern` is set and non-empty — expand site tokens and use as base
2. Else if `shortlinkBaseUrl` is set and non-empty — use as base
3. Else — use `UrlHelper::siteUrl()` with the short link's `siteId`

This method is called when generating `ShortLink::getUrl()`, `getQrCodeUrl()`, and `getQrCodeDisplayUrl()`.

## Server Configuration

Your web server must point `short.example.com` to your Craft installation. The plugin handles routing internally — no additional server config beyond a standard Craft vhost is needed.

If you use a true separate domain (not a subdomain of your main site), ensure:
- The domain resolves to the same server as your Craft installation
- Your server vhost serves Craft from that domain
- SSL is configured for the domain

## Validation

`shortlinkBaseUrl` must be a valid URL starting with `http://` or `https://`.

`shortlinkBaseUrlPattern` must also start with `http://` or `https://`, and any `{...}` tokens must use one of the supported tokens. Using `{siteName}` or any other unsupported token triggers a validation error.

## Multi-Environment Example

```php
// config/shortlink-manager.php
use craft\helpers\App;

return [
    '*' => [
        // Use a custom domain in production
        'shortlinkBaseUrl' => App::env('SHORTLINK_BASE_URL'),
    ],

    'dev' => [
        // On local dev, use the default site URL (no override needed)
        'shortlinkBaseUrl' => null,
    ],
];
```
