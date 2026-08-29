# Console commands

Run ShortLink Manager's console commands to finish setup tasks without visiting the Control Panel.

## Getting help @since(5.20.0)

The plugin-level help command lists available commands:

```bash title="PHP"
php craft shortlink-manager/help
```

```bash title="DDEV"
ddev craft shortlink-manager/help
```

Pass a command name when you want context-specific notes for one workflow:

Setup templates:

```bash title="PHP"
php craft shortlink-manager/help setup/copy-templates
```

```bash title="DDEV"
ddev craft shortlink-manager/help setup/copy-templates
```

Generate the analytics salt:

```bash title="PHP"
php craft shortlink-manager/help security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/help security/generate-salt
```

Craft's native help is still available when you need the exact Yii option signature:

Generate the analytics salt:

```bash title="PHP"
php craft help shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft help shortlink-manager/security/generate-salt
```

Setup templates:

```bash title="PHP"
php craft help shortlink-manager/setup/copy-templates
```

```bash title="DDEV"
ddev craft help shortlink-manager/setup/copy-templates
```

## Setup

### `shortlink-manager/setup/copy-templates` @since(5.27.0)

Copy bundled starter templates into the configured paths in your site's `templates/` folder. This is the fastest way to make the redirect, expired, and QR pages render without Twig template loading errors.

```bash title="PHP"
php craft shortlink-manager/setup/copy-templates
```

```bash title="DDEV"
ddev craft shortlink-manager/setup/copy-templates
```

**What it does:**

1. Reads the current ShortLink Manager template settings and resolves any CP-saved `$ENV_VAR` expressions without replacing their stored values.
2. Checks whether every site enabled in ShortLink Manager resolves each template through a site-specific override or the global fallback.
3. Copies one global fallback when one or more enabled sites cannot resolve a template; it never replaces site-specific overrides.
4. Preserves an explicit configured extension such as `.html`; an extensionless configured path uses a `.twig` starter destination.
5. Creates destination folders automatically.
6. Skips templates that already resolve for every enabled site and skips existing global destinations unless you target one interactively or pass `--overwrite`.

| Option | Description |
|--------|-------------|
| `--template=redirect` | Copy only the redirect interstitial template |
| `--template=expired` | Copy only the expired-link template |
| `--template=qr` | Copy only the QR display template |
| `--overwrite` | Replace the calculated global destination without prompting; site-specific overrides are never overwritten |

Copy one template:

```bash title="PHP"
php craft shortlink-manager/setup/copy-templates --template=redirect
```

```bash title="DDEV"
ddev craft shortlink-manager/setup/copy-templates --template=redirect
```

Replace one template:

```bash title="PHP"
php craft shortlink-manager/setup/copy-templates --template=qr --overwrite
```

```bash title="DDEV"
ddev craft shortlink-manager/setup/copy-templates --template=qr --overwrite
```

**When to use:** Run this after installing the plugin, after changing template paths in settings, or when the setup page reports missing starter templates. Review and customize copied templates before going live.

## Security

### `shortlink-manager/security/generate-salt`

Generate a cryptographically secure salt for IP hashing and write it to your `.env` file.

```bash title="PHP"
php craft shortlink-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft shortlink-manager/security/generate-salt
```

**What it does:**

1. Generates a 64-character hexadecimal string using `random_bytes(32)` — cryptographically secure
2. Checks if `SHORTLINK_MANAGER_IP_SALT` already exists in your `.env` file
3. If not found: appends the new salt to `.env`
4. If found: prompts for confirmation before replacing (replacing resets unique visitor tracking)

**Expected output:**

```
ShortLink Manager - IP Hash Salt Generator
============================================================

Generated secure salt:
a1b2c3d4e5f6...  (64 hex characters)

✓ Added SHORTLINK_MANAGER_IP_SALT in .env file
Location: /var/www/html/.env

Important:
• Never commit .env to version control
• Store the salt securely (password manager recommended)
• Use the SAME salt across all environments (dev/staging/production)
• Changing the salt will reset unique visitor tracking
```

**When to use:** Run once after installing the plugin, before analytics data starts accumulating. The same salt must be used across all environments — dev, staging, and production — to maintain consistent unique visitor counts.

> [!WARNING]
> Replacing an existing salt invalidates all previously hashed IP addresses. Existing analytics records retain their old hash values, so unique visitor counts will not merge correctly across old and new data. Only replace the salt if you're resetting analytics data entirely.
