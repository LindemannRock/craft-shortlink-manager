# Console Commands

ShortLink Manager provides console commands for security and maintenance tasks.

## Console Help

Use the plugin-level help command to see the available ShortLink Manager commands and focused guidance for each workflow:

```bash title="DDEV"
ddev craft shortlink-manager/help
ddev craft shortlink-manager/help security/generate-salt
```

Craft's native command help is still available when you need the exact Yii option signature:

```bash title="DDEV"
ddev craft help shortlink-manager/security/generate-salt
```

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
