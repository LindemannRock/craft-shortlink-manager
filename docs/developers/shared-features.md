# Shared features

ShortLink Manager builds on two shared libraries — `lindemannrock/base` and `lindemannrock/logging-library` — rather than reimplementing common infrastructure. This page lists the specific features used and what each one does.

## `lindemannrock/base`

| Feature | Description |
|---------|-------------|
| `PluginHelper::bootstrap()` | Initializes base module, Twig globals, and logging configuration |
| `PluginHelper::applyPluginNameFromConfig()` | Overrides plugin name from config file |
| `PluginHelper::isPluginEnabled()` | Checks whether another plugin is installed and enabled |
| `CpNavHelper::buildSubnav()` | Builds permission-filtered CP subnav items |
| `CpNavHelper::firstAccessibleRoute()` | Resolves the first accessible CP route for the current user |
| `SettingsConfigTrait` | Config file override detection and log level validation |
| `SettingsDisplayNameTrait` | Standardized plugin name helper methods |
| `SettingsPersistenceTrait` | Database persistence for Settings models |
| `DateFormatHelper` | Date/time formatting with timezone awareness; `localDateExpression()` / `localHourExpression()` for DB queries |
| `DateRangeHelper` | Date range resolution (bounds, day counts, default range, query application) |
| `ExportHelper` | CSV, JSON, and Excel export helpers |
| `GeoHelper` | Geographic utilities (country code to name conversion) |

### Details

**PluginHelper::bootstrap()**

Provides plugin name helpers in Twig templates (see [Twig globals](twig-globals.md)) and configures logging.

**PluginHelper::applyPluginNameFromConfig()**

Allows customizing the plugin display name via `config/shortlink-manager.php`.

**PluginHelper::isPluginEnabled()**

Used to conditionally integrate with the Logging Library and Smart Links plugin.

**CpNavHelper::buildSubnav() / firstAccessibleRoute()**

Builds the CP subnav filtering items by user permissions, and resolves the first accessible route when a user lands on the plugin's main CP page.

**SettingsConfigTrait**

Settings can be overridden via `config/shortlink-manager.php`. Debug logging requires `devMode`.

**SettingsDisplayNameTrait**

Provides `getDisplayName()`, `getFullName()`, `getPluralDisplayName()`, etc.

**SettingsPersistenceTrait**

Settings are stored in the database with automatic type conversion for boolean, integer, float, and JSON fields.

**DateFormatHelper**

Used for date/time display in the element index and for timezone-aware SQL expressions in analytics queries (`localDateExpression()`, `localHourExpression()`).

**DateRangeHelper**

Resolves date range bounds and day counts, applies date range filters to DB queries, and returns the persisted default date range for the plugin.

**ExportHelper**

Handles analytics exports to CSV, Excel, and JSON formats.

**GeoHelper**

ISO 3166-1 alpha-2 country code utilities, used in analytics breakdown and export to convert country codes to human-readable names.

---

## `lindemannrock/logging-library`

| Feature | Description |
|---------|-------------|
| `LoggingLibrary::configure()` | Dedicated plugin logging configuration |
| `LoggingTrait` | Convenient logging methods (`logInfo`, `logWarning`, `logError`, `logDebug`) |
| `LoggingLibrary::addLogsNav()` | Adds "Logs" subnav to plugin CP navigation |

### Details

**LoggingLibrary::configure()**

Enables dedicated log files at `storage/logs/{plugin-handle}-{date}.log`.

**LoggingTrait**

Provides standardized logging to dedicated plugin log files.

**LoggingLibrary::addLogsNav()**

View plugin logs directly in the Control Panel.
