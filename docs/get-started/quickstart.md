# Quickstart

Get ShortLink Manager running in under 5 minutes. By the end of this guide you'll have a working short link that redirects to a destination URL and a QR code you can scan.

## 1. Install the Plugin

See [Installation](installation.md) for full details including DDEV and manual options.

```bash title="Composer"
composer require lindemannrock/craft-shortlink-manager && php craft plugin/install shortlink-manager
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-shortlink-manager && ddev craft plugin/install shortlink-manager
```

## 2. Create Your First Short Link

1. In the control panel, go to **ShortLink Manager**
2. Click **New Short Link**
3. Enter a destination URL (e.g., `https://example.com/my-page`)
4. Leave the code on **Auto-generated** — the plugin creates a unique code for you
5. Click **Save**

## 3. Test the Redirect

Copy the short URL shown in the sidebar (e.g., `https://yoursite.com/s/abc123XY`) and open it in your browser. You should be redirected to the destination URL.

The QR code is displayed in the sidebar — scan it with your phone to verify it also redirects correctly.

## 4. Check Analytics

Go to **ShortLink Manager → Analytics**. Your test click should appear in the dashboard with device type, referrer, and timestamp.

> [!TIP]
> For unique visitor tracking, generate an IP hash salt: `php craft shortlink-manager/security/generate-salt`

## What's Next

- [Configuration](configuration.md) — customize slug prefix, enable geolocation, set up a custom short domain
- [Feature Tour](../feature-tour/overview.md) — explore QR codes, analytics, direct redirect, and more
