# Quickstart

Create a working short link and scan its QR code after installation setup is complete.

## Before you start

Complete [Installation & Setup](installation.md#post-install-setup) first. The setup page should show that the IP hash salt is configured and all starter templates are present before you test public short links or QR landing pages.

## 1. Create your first short link

1. In the Control Panel, go to **ShortLink Manager**
2. Click **New ShortLink**
3. Enter a destination URL (e.g., `https://example.com/my-page`)
4. Leave the code on **Auto-generated** — the plugin creates a unique code for you
5. Click **Save**

![The new short link form with destination URL filled in and code set to Auto-generated](images/quickstart-new-link.webp)

The short URL and its QR code appear in the sidebar immediately after saving.

## 2. Test the redirect

Copy the short URL shown in the sidebar (e.g., `https://yoursite.com/s/abc123XY`) and open it in your browser. You should land on the destination URL.

Scan the QR code with your phone to confirm it also redirects correctly.

## 3. Check analytics

Go to **ShortLink Manager → Analytics**. Your test click appears with device type, referrer, and timestamp.

## What's next

- [Configuration](configuration.md) — customize the slug prefix, enable geolocation, or point links at a custom short domain
- [Custom templates](../developers/custom-templates.md) — customize the redirect, expired-link, and QR landing pages
- [Feature Tour](../feature-tour/overview.md) — explore QR code styles, analytics, direct redirect, folders, and more
