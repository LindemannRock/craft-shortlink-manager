# ShortLink Manager Logging

ShortLink Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized, structured logging across all LindemannRock plugins.

## Log Levels

- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

## Configuration

### Control Panel

1. Navigate to **Settings → ShortLink Manager → General**
2. Scroll to **Logging Settings**
3. Select desired log level from dropdown
4. Click **Save**

### Config File

```php
// config/shortlink-manager.php
return [
    'pluginName' => 'ShortLinks',  // Optional: Customize plugin name shown in logs interface
    'logLevel' => 'error',          // error, warning, info, or debug
];
```

**Notes:**
- The `pluginName` setting customizes how the plugin name appears in the log viewer interface (page title, breadcrumbs, etc.). If not set, it defaults to "ShortLink Manager".
- Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

## Log Files

- **Location**: `storage/logs/shortlink-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at ShortLink Manager → Logs

## What's Logged

The plugin logs meaningful events using context arrays for structured data. All logs include user context when available.

### Redirect Controller (RedirectController)

#### Shortlink Redirection
- **[DEBUG]** `Shortlink redirect requested` - Redirect request initiated
  - Context: `code` (shortlink code)
- **[WARNING]** `Shortlink code missing` - No code provided in redirect request
- **[WARNING]** `Shortlink not found` - Shortlink code doesn't exist
  - Context: `code`
- **[DEBUG]** `Shortlink found` - Shortlink retrieved from database
  - Context: `slug`, `destinationUrl`, `elementId`
- **[INFO]** `Shortlink disabled` - Shortlink is disabled
  - Context: `code`
- **[INFO]** `Shortlink expired` - Shortlink has expired
  - Context: `code`
- **[DEBUG]** `Fetching URL from linked element` - Retrieving URL from linked element
  - Context: `elementId`, `elementType`
- **[DEBUG]** `Element URL retrieved` - Element URL successfully retrieved
  - Context: `url`
- **[ERROR]** `Linked element not found` - Linked element doesn't exist
  - Context: `elementId`
- **[ERROR]** `No destination URL available` - No valid destination URL
  - Context: `slug`, `elementId`
- **[INFO]** `Redirecting shortlink` - Executing redirect
  - Context: `slug`, `destination`, `httpCode`
- **[INFO]** `Shortlink 404 handled by Redirect Manager` - 404 forwarded to Redirect Manager plugin
  - Context: `code`, `hasRedirectManager`

### ShortLinks Service (ShortLinksService)

#### Validation & Save Operations
- **[ERROR]** `Slug is required for vanity URLs` - Vanity URL missing slug
- **[ERROR]** `Invalid or duplicate slug` - Slug validation failed
  - Context: `slug`
- **[ERROR]** `ShortLink validation failed` - Element validation errors
  - Context: `errors` (validation errors array)

#### Element Operations
- **[INFO]** `Updated shortlink destination for element` - Shortlink destination updated when element URI changes
  - Context: `shortlinkId`, `elementId`, `oldDestination`, `newDestination`
- **[INFO]** `Deleted shortlink for element` - Shortlink deleted when element deleted
  - Context: `shortlinkId`, `elementId`

#### Cache Operations
- **[INFO]** `Invalidated ShortLink Manager caches` - Caches cleared successfully
  - Context: `cleared` (number of caches cleared)
- **[ERROR]** `Failed to invalidate caches` - Cache clearing failed
  - Context: `error` (exception message)

#### Redirect Creation
- **[INFO]** `Created redirect for slug change` - Redirect created when shortlink slug changes
  - Context: `oldSlug`, `newSlug`, `redirectId`, `shortlinkId`
- **[INFO]** `Auto-created redirect for expired shortlink` - Redirect created for expired shortlink
  - Context: `shortlinkSlug`, `destination`, `redirectId`, `shortlinkId`
- **[INFO]** `Auto-created redirect for deleted shortlink` - Redirect created for deleted shortlink
  - Context: `shortlinkSlug`, `destination`, `redirectId`, `shortlinkId`

### QR Code Service (QrCodeService)

- **[ERROR]** `Failed to add logo to QR code` - QR code logo overlay failed
  - Context: `error` (exception message)

### Analytics Service (AnalyticsService)

#### IP Tracking
- **[ERROR]** `Failed to hash IP address` - IP hashing failed
  - Context: `error` (exception message)
- **[ERROR]** `IP hash salt not configured - analytics tracking disabled` - Missing IP hash salt
  - Context: `ip` (always 'hidden'), `saltValue` (NULL or unparsed string)

#### Cleanup Operations
- **[INFO]** `Cleaned up old analytics` - Old analytics records deleted
  - Context: `deleted` (number of records deleted)

#### Geolocation
- **[WARNING]** `Failed to get location from IP` - IP geolocation lookup failed
  - Context: `error` (exception message)

### Cleanup Analytics Job (CleanupAnalyticsJob)

- **[INFO]** `Cleaned up old analytics records` - Scheduled cleanup completed
  - Context: `deleted` (number of records deleted)

### Settings Model (Settings)

#### Log Level Adjustments
- **[WARNING]** `Log level "debug" from config file changed to "info" because devMode is disabled` - Debug level auto-corrected from config file
  - Context: `configFile` (path to config file)
- **[WARNING]** `Log level automatically changed from "debug" to "info" because devMode is disabled` - Debug level auto-corrected from database setting

#### Loading Operations
- **[ERROR]** `Failed to load settings from database` - Database query error
  - Context: `error` (exception message)
- **[WARNING]** `No settings found in database` - No settings record exists in database

#### Save Operations
- **[ERROR]** `Settings validation failed` - Settings validation errors
  - Context: `errors` (validation errors array)
- **[DEBUG]** `Attempting to save settings` - Settings save operation initiated
  - Context: `attributes` (settings being saved)
- **[DEBUG]** `Database update result` - Database update operation result
  - Context: `result` (update result)
- **[INFO]** `Settings saved successfully to database` - Settings saved
- **[ERROR]** `Database update returned false` - Database update operation returned false
- **[ERROR]** `Failed to save ShortLink Manager settings` - Settings save exception
  - Context: `error` (exception message)

### Main Plugin (ShortLinkManager)

- **[INFO]** `Could not load settings from database` - Settings loading error during plugin initialization
  - Context: `error` (exception message)
- **[INFO]** `Scheduled initial analytics cleanup job` - Analytics cleanup job scheduled
  - Context: `interval` (cleanup interval)

## Log Management

### Via Control Panel

1. Navigate to **ShortLink Manager → Logs**
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

### Via Command Line

**View today's log**:

```bash
tail -f storage/logs/shortlink-manager-$(date +%Y-%m-%d).log
```

**View specific date**:

```bash
cat storage/logs/shortlink-manager-2025-01-15.log
```

**Search across all logs**:

```bash
grep "Shortlink" storage/logs/shortlink-manager-*.log
```

**Filter by log level**:

```bash
grep "\[ERROR\]" storage/logs/shortlink-manager-*.log
```

## Log Format

Each log entry follows structured JSON format with context data:

```json
{
  "timestamp": "2025-01-15 14:30:45",
  "level": "INFO",
  "message": "Redirecting shortlink",
  "context": {
    "slug": "promo",
    "destination": "https://example.com/promotion",
    "httpCode": 302,
    "userId": 1
  },
  "category": "lindemannrock\\shortlinkmanager\\controllers\\RedirectController"
}
```

## Using the Logging Trait

All services and controllers in ShortLink Manager use the `LoggingTrait` from the LindemannRock Logging Library:

```php
use lindemannrock\logginglibrary\traits\LoggingTrait;

class MyService extends Component
{
    use LoggingTrait;

    public function myMethod()
    {
        // Info level - general operations
        $this->logInfo('Operation started', ['param' => $value]);

        // Warning level - important but non-critical
        $this->logWarning('Missing data', ['key' => $missingKey]);

        // Error level - failures and exceptions
        $this->logError('Operation failed', ['error' => $e->getMessage()]);

        // Debug level - detailed information
        $this->logDebug('Processing item', ['item' => $itemData]);
    }
}
```

## Best Practices

### 1. DO NOT Log in init() ⚠️

The `init()` method is called on **every request** (every page load, AJAX call, etc.). Logging there will flood your logs with duplicate entries.

```php
// ❌ BAD - Causes log flooding
public function init(): void
{
    parent::init();
    $this->logInfo('Plugin initialized');  // Called on EVERY request!
}

// ✅ GOOD - Log actual operations
public function handleRedirect($code): void
{
    $this->logInfo('Shortlink redirect processed', ['code' => $code]);
    // ... your logic
}
```

### 2. Always Use Context Arrays

Use the second parameter for variable data, not string concatenation:

```php
// ❌ BAD - Concatenating variables into message
$this->logError('Redirect failed: ' . $e->getMessage());
$this->logInfo('Processing shortlink: ' . $code);

// ✅ GOOD - Use context array for variables
$this->logError('Redirect failed', ['error' => $e->getMessage()]);
$this->logInfo('Processing shortlink', ['code' => $code]);
```

**Why Context Arrays Are Better:**
- Structured data for log analysis tools
- Easier to search and filter in log viewer
- Consistent formatting across all logs
- Automatic JSON encoding with UTF-8 support

### 3. Use Appropriate Log Levels

- **debug**: Internal state, variable dumps (requires devMode)
- **info**: Normal operations, user actions
- **warning**: Unexpected but handled situations
- **error**: Actual errors that prevent operation

### 4. Security

- Never log passwords or sensitive data
- Be careful with user input in log messages
- Never log API keys, tokens, or credentials
- IP addresses are hashed when IP tracking is enabled

## Performance Considerations

- **Error/Warning levels**: Minimal performance impact, suitable for production
- **Info level**: Moderate logging, useful for tracking operations
  - Logs shortlink redirects
  - Element operations (updates, deletions)
  - Redirect creation for slug changes
  - Cache invalidation
  - Analytics cleanup
- **Debug level**: Extensive logging, use only in development (requires devMode)
  - Logs every redirect request attempt
  - Shortlink lookup details
  - Element URL retrieval
  - Settings save operations
  - Database update results

## Requirements

ShortLink Manager logging requires:

- **lindemannrock/logginglibrary** plugin (installed automatically as dependency)
- Write permissions on `storage/logs` directory
- Craft CMS 5.x or later

## Troubleshooting

If logs aren't appearing:

1. **Check permissions**: Verify `storage/logs` directory is writable
2. **Verify library**: Ensure LindemannRock Logging Library is installed and enabled
3. **Check log level**: Confirm log level allows the messages you're looking for
4. **devMode for debug**: Debug level requires `devMode` enabled in `config/general.php`
5. **Check CP interface**: Use ShortLink Manager → Logs to verify log files exist

## Common Scenarios

### Shortlink Not Redirecting

When shortlinks don't redirect properly:

```bash
grep "Shortlink" storage/logs/shortlink-manager-*.log
```

Look for:

- `Shortlink redirect requested` - Verify redirect was requested
- `Shortlink not found` - Code doesn't exist in database
- `Shortlink disabled` - Shortlink is disabled
- `Shortlink expired` - Shortlink has expired
- `No destination URL available` - Missing destination
- `Linked element not found` - Element-linked shortlink's element deleted

Common causes:

- Wrong shortlink code
- Shortlink disabled or expired
- Linked element deleted
- No destination URL configured
- Invalid element ID

### Missing Destination URL

Debug destination URL issues:

```bash
grep "destination\|URL" storage/logs/shortlink-manager-*.log
```

Look for:

- `No destination URL available` - No valid destination
- `Fetching URL from linked element` - Trying to get URL from element
- `Element URL retrieved` - Successfully got element URL
- `Linked element not found` - Element doesn't exist

If destinations are missing:

- Check if destination URL is set on shortlink
- Verify linked element exists and is enabled
- Ensure linked element has a URL
- Check element site settings

### Slug Validation Errors

Track slug validation issues:

```bash
grep "slug" storage/logs/shortlink-manager-*.log
```

Look for:

- `Slug is required for vanity URLs` - Missing slug for vanity URL
- `Invalid or duplicate slug` - Slug validation failed
- `ShortLink validation failed` - Check errors context for details

If slug validation fails:

- Ensure slug is unique
- Check slug format (no special characters)
- Verify slug length is within limits
- Review slug pattern requirements

### QR Code Generation Issues

Debug QR code problems:

```bash
grep "QR" storage/logs/shortlink-manager-*.log
```

Look for:

- `Failed to add logo to QR code` - Logo overlay failed

Common QR code issues:

- Logo file not found or invalid format
- Logo too large for QR code
- Invalid image file
- GD/ImageMagick library issues

### Analytics Not Tracking

Debug analytics tracking issues:

```bash
grep -i "analytics\|IP hash" storage/logs/shortlink-manager-*.log
```

Look for:

- `IP hash salt not configured - analytics tracking disabled` - Missing salt configuration
- `Failed to hash IP address` - IP hashing error
- `Cleaned up old analytics` - Successful cleanup
- `Failed to get location from IP` - Geolocation issues

Common issues:

- IP hash salt not configured (run console command to generate)
- Analytics disabled in settings
- Database write issues
- IP geolocation failures (expected for local development)

### Auto-Redirect Creation

Monitor automatic redirect creation:

```bash
grep "Auto-created redirect\|Created redirect" storage/logs/shortlink-manager-*.log
```

Look for:

- `Created redirect for slug change` - Redirect created when slug changes
- `Auto-created redirect for expired shortlink` - Expired shortlink redirect
- `Auto-created redirect for deleted shortlink` - Deleted shortlink redirect

These automatic redirects:

- Preserve SEO when shortlinks change
- Handle expired shortlink requests
- Maintain links when shortlinks are deleted
- Require Redirect Manager plugin integration

### Cache Issues

Track cache operations:

```bash
grep "cache" storage/logs/shortlink-manager-*.log
```

Look for:

- `Invalidated ShortLink Manager caches` - Caches cleared successfully
- `Failed to invalidate caches` - Cache clearing failed

If cache operations fail:

- Check cache configuration
- Verify cache directory permissions
- Clear Craft's general caches
- Review cache driver settings

### Element Synchronization

Monitor element-related operations:

```bash
grep "element" storage/logs/shortlink-manager-*.log
```

Look for:

- `Updated shortlink destination for element` - URI change detected
- `Deleted shortlink for element` - Element deleted
- `Fetching URL from linked element` - Getting element URL
- `Linked element not found` - Element missing

Element sync ensures:

- Shortlinks stay up-to-date with element URI changes
- Automatic destination updates
- Cleanup when elements deleted
- Proper URL resolution

### Settings Save Issues

Monitor settings operations:

```bash
grep -i "settings" storage/logs/shortlink-manager-*.log
```

Look for:

- `Attempting to save settings` - Save initiated
- `Settings saved successfully to database` - Successful save
- `Settings validation failed` - Validation errors
- `Failed to save ShortLink Manager settings` - Save exception

If settings fail to save:

- Check validation errors for specific fields
- Verify database connectivity
- Ensure database table exists (run migrations)
- Review config file overrides (may prevent saves)

## Development Tips

### Enable Debug Logging

For detailed troubleshooting during development:

```php
// config/shortlink-manager.php
return [
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

This provides:

- Every shortlink redirect request
- Shortlink lookup details
- Element URL retrieval steps
- Database update results
- Detailed validation errors

### Monitor Specific Operations

Track specific operations using grep:

```bash
# Monitor all shortlink redirects
grep "Redirecting shortlink" storage/logs/shortlink-manager-*.log

# Watch logs in real-time
tail -f storage/logs/shortlink-manager-$(date +%Y-%m-%d).log

# Check all errors
grep "\[ERROR\]" storage/logs/shortlink-manager-*.log

# Monitor redirect creation
grep "Auto-created redirect\|Created redirect" storage/logs/shortlink-manager-*.log

# Track analytics operations
grep -i "analytics" storage/logs/shortlink-manager-*.log

# Watch element synchronization
grep "element" storage/logs/shortlink-manager-*.log

# Monitor cache operations
grep "cache" storage/logs/shortlink-manager-*.log
```

### Debug Shortlink Resolution

When troubleshooting shortlink resolution:

```bash
# Find all logs for specific shortlink code
grep "code.*:.*\"abc123\"" storage/logs/shortlink-manager-*.log

# Track shortlink lookup process
grep "Shortlink found\|Shortlink not found" storage/logs/shortlink-manager-*.log
```

Review the context to see:

- Shortlink details (slug, destination, element)
- Why shortlinks fail (expired, disabled, not found)
- Element URL resolution
- Redirect execution

### Performance Monitoring

Track shortlink performance:

```bash
# Monitor cache operations
grep "cache" storage/logs/shortlink-manager-*.log

# Check redirect frequency
grep "Redirecting shortlink" storage/logs/shortlink-manager-*.log

# Track auto-redirect creation
grep "Auto-created redirect" storage/logs/shortlink-manager-*.log
```

Enable debug mode to see:

- Lookup performance
- Cache effectiveness
- Element query efficiency
- Database operation timing

### IP Tracking Configuration

Monitor IP tracking setup:

```bash
grep "IP hash salt" storage/logs/shortlink-manager-*.log
```

If you see `IP hash salt not configured`:

1. Run: `php craft shortlink-manager/security/generate-salt`
2. Salt will be added to `.env` file
3. IP tracking will be enabled automatically
4. Check logs again to verify tracking works

**Note**: IP hashing is used for privacy - actual IP addresses are never logged.

### Integration with Redirect Manager

ShortLink Manager can create redirects in the Redirect Manager plugin:

- Slug changes create 301 redirects
- Expired shortlinks create redirects to destination
- Deleted shortlinks create redirects to destination

Monitor this integration:

```bash
grep "redirect" storage/logs/shortlink-manager-*.log
```

Benefits:

- SEO preservation when shortlinks change
- Graceful handling of expired/deleted links
- Unified redirect management
- Analytics tracking across both plugins
