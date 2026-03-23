# Troubleshooting

Common issues and solutions for ShortLink Manager.

## Short Link Returns 404 or Redirects to the Wrong Page

**Check the basics first:**

1. **Is the plugin enabled for the correct site?** Go to **ShortLink Manager → Settings → General → Enabled Sites**. An empty list means all sites are enabled. If specific sites are listed, ensure the site serving the short link is included.

2. **Is the short link enabled?** Go to **ShortLink Manager** and check the link's status. A disabled link redirects to `notFoundRedirectUrl` (default: `/`).

3. **Is the short link pending?** If the link has a future post date, it is not yet active.

4. **Is the slug prefix correct?** The short URL pattern is `/{slugPrefix}/{code}`. If you changed `slugPrefix` after creating links, old links are now at the new prefix route — the codes themselves are unchanged.

5. **Is the route registered?** Site routes are registered at plugin init. Clear caches and reload: `php craft clear-caches/all` or `ddev craft clear-caches/all`.

6. **Is there a route conflict?** Another plugin or a Craft section may have a route that conflicts with the short link prefix. ShortLink Manager registers its routes at the beginning of the rules array (highest priority), but check `config/routes.php` for conflicts.

7. **Are both ShortLink Manager and SmartLink Manager in root mode on the same host?** If both plugins have URL prefix disabled and share a host, root routes like `/{slug}` can collide. One plugin may capture requests meant for the other and trigger its own `notFoundRedirectUrl`.

## Short Link Creates a Loop or Doesn't Redirect

If visiting a short link URL doesn't redirect but instead loads a Craft template or error:

- Your site may have a matching Craft URL (section, entry) that intercepts the request before the shortlink route. Check if there's a section or entry with the same slug.
- Clear all caches: Craft route caches can be stale after changing `slugPrefix`.

## Analytics Are Not Recording

1. **Is analytics enabled?** Check `enableAnalytics` is `true` in settings.

2. **Is the link set to track?** Each short link has a **Track Analytics** toggle. Disabled links do not record clicks.

3. **Is the IP hash salt set?** Without `ipHashSalt`, unique visitor tracking is not available, but total click counts still record. Set the salt with:

```bash title="PHP"
php craft shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/security/generate-salt
```

4. **Check plugin logs:** Enable debug logging in `config/shortlink-manager.php` (`'logLevel' => 'debug'`) and check **ShortLink Manager → Logs**.

5. **Check whether Direct Redirect is enabled.** In Direct Redirect mode, analytics only record when the short URL request reaches Craft. If a browser, CDN, Blitz, or platform static cache serves the short URL before PHP runs, repeat-hit analytics can be bypassed.

6. **Check the redirect mode under caching.**
   - If you need analytics-safe redirects under static caching, keep `directRedirect = false`
   - If you need `directRedirect = true`, add cache bypass rules for your shortlink routes

7. **Clear stale redirect caches after changes.** If you changed a link from `301`/`308` to `302`/`307`, or changed the redirect mode, clear browser/CDN/static caches before retesting. Previously cached permanent redirects can keep masking the new behavior.

## Analytics Record Once, Then Stop Under Static Cache

This symptom almost always means the short URL response is being cached before it reaches Craft on later requests.

### Why it happens

- With `directRedirect = true`, the initial short URL request is both the analytics event and the redirect response
- If that response is cached by the browser, CDN, or a static cache layer, later requests can bypass Craft entirely
- When Craft does not run, analytics do not run

### How to fix it

Choose the mode that matches your goal:

1. **Reliable analytics under caching**
   - Keep `directRedirect = false`
   - Let the redirect template route to the internal tracking action before the final redirect

2. **Fastest direct redirect**
   - Use `directRedirect = true`
   - Add cache bypass rules for your shortlink routes if accurate repeat-hit analytics matter

### After changing settings

Always clear:

- Browser cache
- CDN/edge cache
- Any platform static cache

Old `301`/`308` responses can remain cached even after your plugin settings or link settings are updated.

## Geolocation Shows No Country / City Data

1. **Is `enableGeoDetection` set to `true`?** Check **ShortLink Manager → Settings → Analytics**.

2. **Private IP addresses cannot be geolocated.** In local development, IPs like `127.0.0.1` or `192.168.x.x` always fail. Set defaults:

```php
// config/shortlink-manager.php
'defaultCountry' => 'US',
'defaultCity' => 'New York',
```

Or via env vars: `SHORTLINK_MANAGER_DEFAULT_COUNTRY` and `SHORTLINK_MANAGER_DEFAULT_CITY`.

3. **Check plugin logs instead of the queue.** Geolocation now runs inline during the analytics write path, so a queue backlog is not the cause. If geo fields are blank, enable debug logging and inspect the ShortLink Manager logs for hash-salt, provider, or persistence errors.

4. **Check your geo provider rate limits.** Free tiers have request limits (ip-api.com: 45/min, ipapi.co: 1000/day). If you exceed the limit, lookups fail silently. Consider a paid API key or switch providers.

## QR Code Shows as Broken Image

1. **Is the QR prefix configured correctly?** The QR URL pattern is `/{qrPrefix}/{code}`. The default is an empty string (no prefix), but a common configuration is `s/qr`, giving URLs like `/s/qr/abc123`. Check the `qrPrefix` setting.

2. **Is the route registered?** Clear caches and reload after changing `qrPrefix`.

3. **Is the QR code enabled on the link?** Check the **QR Code Enabled** toggle on the short link edit screen.

4. **Check logs for generation errors.** Enable debug logging and inspect the log for QR generation errors (e.g., invalid logo asset, permissions issue on the cache directory).

5. **Check file permissions.** If using file-based QR caching, ensure `storage/runtime/shortlink-manager/qr/` is writable by the web server.

## QR Code Downloads as Wrong File Type

The download format is controlled by the `defaultQrFormat` setting (`png` or `svg`) and can be overridden per link. If you request a format via the URL parameter (e.g., `?format=svg`), ensure the QR URL includes that parameter.

## Custom Short Domain Not Working

1. **Does DNS resolve?** The domain must point to your Craft server.

2. **Is there a vhost entry?** Your web server (Apache/nginx) must have a vhost serving Craft from that domain.

3. **Is `shortlinkBaseUrl` set correctly?** It must include the protocol: `https://short.example.com` (not `short.example.com`).

4. **Does the URL validate?** `shortlinkBaseUrl` must pass URL validation (starts with `http://` or `https://`). Check for trailing slashes or extra whitespace.

## Headless / Decoupled Craft Setup — Wrong Short Link Domain

In a headless setup, the site's `baseUrl` typically points to the frontend application (e.g., `https://frontend.example.com`), not the Craft backend where shortlink routes are served. Without configuration, generated short link and QR code URLs point to the frontend domain — where the `/s/{code}` routes don't exist.

**Fix:** Set `shortlinkBaseUrl` to your Craft backend or dedicated short domain:

```php
// config/shortlink-manager.php
'shortlinkBaseUrl' => App::env('SHORTLINK_BASE_URL'),
```

```bash
# .env
SHORTLINK_BASE_URL=https://links.example.com
```

This only changes generated URLs (copy buttons, QR codes, exports). The actual routes still need to be served by Craft — ensure the short domain points to your Craft installation.

## Multisite Short Links All Resolve to the Same Site

If you use `shortlinkBaseUrl` on a multisite install, all sites produce the same URL (e.g., `https://short.ly/s/abc123` for EN, AR, and FR). When a request arrives, Craft resolves the site from the hostname — which maps to only one site. The other sites' versions of that short link are unreachable.

**Fix:** Use `shortlinkBaseUrl` with `{siteHandle}` instead:

```php
// config/shortlink-manager.php
'shortlinkBaseUrl' => 'https://short.ly/{siteHandle}',
```

This produces site-specific URLs (`https://short.ly/en/s/abc123`, `https://short.ly/fr/s/abc123`) and registers site-aware routes so the redirect controller resolves the correct site from the URL path.

> [!TIP]
> Rule of thumb: Single-site or headless with one site → `shortlinkBaseUrl`. Multisite → tokenized `shortlinkBaseUrl` with `{siteHandle}`.

See [Custom Domain](../feature-tour/custom-domain.md) for full configuration details.

## `shortlinkBaseUrl` Token Validation Error

```text
Unsupported token in shortlink base URL.
```

Only `{siteHandle}`, `{siteId}`, and `{siteUid}` are supported tokens. Check that your `shortlinkBaseUrl` does not use `{siteName}`, `{locale}`, or any other custom tokens.

## SEOmatic Tracking Events Not Firing

SEOmatic tracking requires the redirect template to render. When `directRedirect` is `true` (globally or per link), the template is bypassed and no client-side events fire.

To enable SEOmatic tracking:
- Set `directRedirect` to `false` globally, or
- Set the per-link `directRedirect` to `false` (overrides the global setting)

See [Direct Redirect](../feature-tour/direct-redirect.md) and [Integrations](../developers/integrations.md).

## Custom Redirect Template Still Skips Tracking

If you override `templates/shortlink-manager/redirect.twig`, make sure it redirects to the internal `goUrl` variable, not directly to `destinationUrl`.

For non-direct redirects, the tracked flow is:

1. Render the redirect template
2. Redirect to `goUrl`
3. Let the internal action route record analytics and issue the final redirect

If your custom template redirects straight to `destinationUrl`, the tracking hop is bypassed.

## Redirect Manager Integration Not Creating Redirects

1. **Is the integration enabled?** Check `enabledIntegrations` includes `'redirect-manager'` in settings.

2. **Is Redirect Manager installed?** The integration only activates if Redirect Manager is installed and enabled.

3. **Are the trigger events configured?** Check `redirectManagerEvents`. The default is `['slug-change']`, which creates a redirect when a short link's code is changed. This is currently the only supported event.

## `debug` Log Level Not Working

Debug logging requires `devMode` to be enabled. If you set `logLevel = 'debug'` in config but `devMode` is `false`, the plugin automatically falls back to `'info'` and logs a warning. Enable `devMode` in `config/general.php` for debug logging.

## Slug Prefix Conflicts with Another Plugin

ShortLink Manager validates `slugPrefix` and `qrPrefix` against Smart Links (if installed). If you get a conflict warning, change one of the prefixes in settings to a value the other plugin does not use.

## Performance: Slow Redirects

If redirects feel slow:

- **Enable Direct Redirect** globally or for high-traffic links to skip template rendering
- **Check the queue.** Analytics processing runs synchronously in the redirect flow before the response is sent. If the analytics write is slow, the redirect is slow. Ensure your database is performing well.
- **Enable QR code caching** (`enableQrCodeCache = true`) if QR scans are slow

## Getting Help

- Check plugin logs: **ShortLink Manager → Logs**
- Enable debug logging: `'logLevel' => 'debug'` in `config/shortlink-manager.php` (requires `devMode`)
- Check Craft's general logs: `storage/logs/web.log`
- For persistent issues, include your ShortLink Manager version and relevant log entries when reporting
