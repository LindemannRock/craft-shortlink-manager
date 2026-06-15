# Twig globals

Use `shortlinkHelper` to access the plugin's display name strings and cache paths in any Twig template — useful for building CP UI components that need to reference the plugin by name or locate its cache directories.

## `shortlinkHelper` @since(5.0.0)

*Provided by `lindemannrock/base`*

| Property | Description |
|----------|-------------|
| `shortlinkHelper.displayName` | Display name (singular, without "Manager") |
| `shortlinkHelper.pluralDisplayName` | Plural display name (without "Manager") |
| `shortlinkHelper.fullName` | Full plugin name (as configured) |
| `shortlinkHelper.lowerDisplayName` | Lowercase display name (singular) |
| `shortlinkHelper.pluralLowerDisplayName` | Lowercase plural display name |
| `shortlinkHelper.cacheBasePath` @since(5.5.0) | Base cache path (e.g., `storage/runtime/shortlink-manager/cache/`) |
| `shortlinkHelper.cachePath` @since(5.5.0) | Cache path for a specific type — use as a method: `shortlinkHelper.cachePath('qr')` |

### Examples

```twig
{{ shortlinkHelper.displayName }}
{{ shortlinkHelper.pluralDisplayName }}
{{ shortlinkHelper.fullName }}
{{ shortlinkHelper.lowerDisplayName }}
{{ shortlinkHelper.pluralLowerDisplayName }}
{{ shortlinkHelper.cacheBasePath }}
{{ shortlinkHelper.cachePath('qr') }}
```
