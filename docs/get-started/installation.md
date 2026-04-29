# Installation & Setup

> [!NOTE]
> Pre-Release: ShortLink Manager is in active development and not yet available on the Craft Plugin Store. Install via Composer for now.

## Composer

Add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```bash
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```bash title="Composer"
composer require lindemannrock/craft-shortlink-manager && php craft plugin/install shortlink-manager
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-shortlink-manager && ddev craft plugin/install shortlink-manager
```

3. **Optional** — Enable [Logging Library](https://github.com/LindemannRock/craft-logging-library) for log viewing:

> [!NOTE]
> Logging Library is included as a Composer dependency and downloaded automatically. Activate it in Craft to enable log viewing.

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

## Post-Install Setup

After installing, complete these steps to get ShortLink Manager working:

### 1. Enable Sites

Go to **ShortLink Manager → Settings → General** and select which sites should have short link support. Leave empty to enable all sites.

### 2. Generate an IP Hash Salt (Recommended)

If you plan to use analytics with unique visitor tracking, generate a secure salt:

```bash title="PHP"
php craft shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/security/generate-salt
```

This writes `SHORTLINK_MANAGER_IP_SALT` to your `.env` file. Keep the same salt across all environments — changing it resets unique visitor tracking.

### 3. Copy Redirect Templates

The plugin ships default redirect templates. Copy them to your project's `templates/` folder to customize:

```bash
cp -r vendor/lindemannrock/craft-shortlink-manager/src/templates/shortlink-manager templates/
```

The key templates are:
- `templates/shortlink-manager/redirect.twig` — shown briefly before the redirect fires (used for GTM/GA tracking)
- `templates/shortlink-manager/expired.twig` — shown when a link has expired
- `templates/shortlink-manager/qr.twig` — QR code display page

> [!TIP]
> If you enable `directRedirect` globally or per link, the redirect template is bypassed entirely. Keep the template for links where SEOmatic/GTM tracking is needed.

> [!IMPORTANT]
> If you customize `templates/shortlink-manager/redirect.twig`, keep it aligned with the plugin's current redirect flow. In non-direct mode, the template must redirect to `goUrl` so analytics and hit counting can run on the internal tracking hop before the final redirect.

### 4. Review Configuration

See [Configuration](configuration.md) for all available settings. Most can be managed from **ShortLink Manager → Settings** without a config file.
