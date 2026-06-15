# Quickstart

Create a working short link and scan its QR code — entirely in the Control Panel, no code required.

## 1. Install the plugin

See [Installation](installation.md) for full details.

```bash title="Composer"
composer require lindemannrock/craft-shortlink-manager && php craft plugin/install shortlink-manager
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-shortlink-manager && ddev craft plugin/install shortlink-manager
```

## 2. Create your first short link

1. In the Control Panel, go to **ShortLink Manager**
2. Click **New Short Link**
3. Enter a destination URL (e.g., `https://example.com/my-page`)
4. Leave the code on **Auto-generated** — the plugin creates a unique code for you
5. Click **Save**

![The new short link form with destination URL filled in and code set to Auto-generated](images/quickstart-new-link.webp)

The short URL and its QR code appear in the sidebar immediately after saving.

## 3. Test the redirect

Copy the short URL shown in the sidebar (e.g., `https://yoursite.com/s/abc123XY`) and open it in your browser. You should land on the destination URL.

Scan the QR code with your phone to confirm it also redirects correctly.

## 4. Check analytics

Go to **ShortLink Manager → Analytics**. Your test click appears with device type, referrer, and timestamp.

> [!TIP]
> For unique visitor tracking, generate an IP hash salt. The command writes `SHORTLINK_MANAGER_IP_SALT` to your `.env` file automatically.

```bash title="PHP"
php craft shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/security/generate-salt
```

## What's next

- [Configuration](configuration.md) — customize the slug prefix, enable geolocation, or point links at a custom short domain
- [Feature Tour](../feature-tour/overview.md) — explore QR code styles, analytics, direct redirect, folders, and more
