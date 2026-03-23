# Direct Redirect @since(5.12.0)

By default, ShortLink Manager renders a redirect template before issuing the final HTTP redirect. The template fires SEOmatic tracking events and gives any GTM/GA JavaScript a chance to run before the browser navigates away.

Direct Redirect skips the template entirely and issues an immediate server-side HTTP redirect — for maximum performance or when you don't need client-side tracking.

## How It Works

**Without Direct Redirect (default):**

1. Browser requests a short URL (for example `https://example.com/s/abc123`, or `https://example.com/abc123` when prefix is disabled)
2. ShortLink Manager loads the redirect template (`shortlink-manager/redirect`)
3. The template renders — fires SEOmatic events, GTM/GA runs
4. The template redirects to an internal uncached action route
5. That action records analytics and issues the final HTTP redirect to the destination

**With Direct Redirect:**

1. Browser requests a short URL (for example `https://example.com/s/abc123`, or `https://example.com/abc123` when prefix is disabled)
2. ShortLink Manager issues a direct HTTP `301`/`302`/`307`/`308` response
3. Browser follows the redirect immediately

In Direct Redirect mode, server-side analytics only record when the short URL request actually reaches Craft. If a browser, CDN, or static cache serves the short URL before PHP runs, repeat-hit tracking can be bypassed.

## Configuration

### Global Setting

```php
// config/shortlink-manager.php
return [
    '*' => [
        'directRedirect' => true,
    ],
];
```

Or in the CP: **ShortLink Manager → Settings → Redirect Behavior → Direct Redirect**.

### Per-Link Override

Each short link has a **Direct Redirect** toggle with three states:

| Value | Behavior |
|-------|---------|
| `null` | Use the global setting (default) |
| `true` | Always use direct redirect for this link |
| `false` | Always use the redirect template for this link |

The per-link override lets you have Direct Redirect enabled globally while keeping the template for specific links that need SEOmatic tracking.

## Caching Behavior

Direct Redirect changes where tracking happens:

- `directRedirect = false`: the redirect template renders first, then sends the browser to an internal action route that performs analytics and the final redirect
- `directRedirect = true`: tracking and redirect happen on the original short URL request itself

This matters under caching:

- With `directRedirect = false`, the redirect page can still work with static caching because the tracked redirect happens on the internal action route
- With `directRedirect = true`, repeat-hit analytics depend on the short URL request reaching Craft every time

If your host, CDN, or static cache stores the short URL response, Direct Redirect can appear to "work once" and then stop recording until caches expire or are cleared.

> [!IMPORTANT]
> Direct Redirect is a performance feature, not a cache bypass. If you need reliable per-hit server-side analytics in direct mode, configure your infrastructure to bypass cache for your shortlink routes.

## Impact on SEOmatic Integration

> [!WARNING]
> SEOmatic client-side tracking events cannot fire when Direct Redirect is enabled. The redirect template is never rendered, so no JavaScript runs before the browser navigates away.

If you rely on SEOmatic/GTM tracking:
- Keep `directRedirect` globally set to `false`
- Use Direct Redirect only on links where tracking is not needed
- Or: use per-link overrides to keep tracking for important links

See [Integrations](../developers/integrations.md) for SEOmatic configuration.

## When to Use Direct Redirect

**Use Direct Redirect when:**
- Maximum redirect speed is critical
- You don't use GTM/GA tracking events via SEOmatic
- You understand that cache layers may reduce repeat-hit analytics unless those routes bypass cache
- The redirect template adds unnecessary latency (e.g., loading assets, complex layouts)
- You're using the short links programmatically and don't need a template at all

**Keep the redirect template when:**
- You fire SEOmatic/GTM tracking events before navigation
- You need analytics to remain reliable under static/browser/CDN caching
- Your redirect template includes analytics pixels or custom JS
- You need full control over the user experience during the redirect

## Template-Less Redirects Without Direct Redirect

If you want to skip the template but still keep the option to enable SEOmatic tracking for some links, another approach is to make your redirect template minimal — just a `<meta>` refresh and the SEOmatic include, with no other assets.

Direct Redirect is simply the most performant option when you have no template requirements at all.

## Changing Redirect Modes or Status Codes

After changing `directRedirect` or switching a link between `301`/`308` and `302`/`307`, clear any relevant browser, CDN, or static caches before testing again. Old permanent redirect responses can stay cached and make the new behavior look broken until those caches are cleared.
