# Installation & Setup

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
> Logging Library is required by Composer. Install or activate it in Craft to enable log viewing.

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

## Post-Install Setup

After installing, open **ShortLink Manager → Setup** in the Control Panel before creating public short links or QR landing pages. The setup page checks the required privacy salt and starter templates.

Until both checks pass, every ShortLink Manager screen shows a **"Setup incomplete"** notice with an **Open setup** button — that's expected, not an error. Complete the two steps below and the notice disappears everywhere at once.

### Generate an IP hash salt

Generate a secure salt for analytics privacy and unique visitor tracking:

```bash title="PHP"
php craft shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/security/generate-salt
```

This writes `SHORTLINK_MANAGER_IP_SALT` to your `.env` file. When `.env` already exists, ShortLink Manager writes and verifies a temporary file beside it, preserves its file mode, and only then promotes the completed file. If any step fails, the original remains unchanged and the command prints the generated assignment for you to add manually. Keep the same salt across all environments — changing it resets unique visitor tracking.

### Copy starter templates

Copy the bundled starter templates into your site's `templates/` folder:

```bash title="PHP"
php craft shortlink-manager/setup/copy-templates
```

```bash title="DDEV"
ddev craft shortlink-manager/setup/copy-templates
```

The command checks template readiness for every site enabled in ShortLink Manager. Template settings saved as `$ENV_VAR` expressions are resolved first, while the raw expression remains stored and visible in the Control Panel. If any enabled site cannot resolve the resulting configured path, the command copies one global fallback into `templates/`; existing site-specific overrides in `templates/{siteHandle}/` continue to take priority. If every enabled site already resolves the template through an override or the global fallback, the command skips it. Review and customize copied templates before going live.

If an effective redirect, expired, or QR template path is empty, unresolved, or contains parent-directory traversal (`..`), the affected copy fails before any destination is built or written. `--overwrite` does not bypass this check.

> [!TIP]
> If you enable `directRedirect` globally or per link, the redirect template is bypassed entirely. Keep the template for links where SEOmatic/GTM tracking is needed.

> [!IMPORTANT]
> If you customize `templates/shortlink-manager/redirect.twig`, keep it aligned with the plugin's current redirect flow. In non-direct mode, the template must redirect to `goUrl` so analytics and hit counting can run on the internal tracking hop before the final redirect.

For the full template reference, available variables, and manual copy paths, see [Custom templates](../developers/custom-templates.md).

### Review configuration

Go to **ShortLink Manager → Settings → General** and select which sites should have short link support. Leave empty to enable all sites.

See [Configuration](configuration.md) for all available settings. Most can be managed from **ShortLink Manager → Settings** without a config file.
