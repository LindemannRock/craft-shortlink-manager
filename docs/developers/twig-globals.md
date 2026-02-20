# Twig Globals

ShortLink Manager provides the following global variables in your Twig templates.

## `shortlinkHelper`

*Provided by `lindemannrock/base`*

| Property | Description |
|----------|-------------|
| `shortlinkHelper.displayName` | Display name (singular, without "Manager") |
| `shortlinkHelper.pluralDisplayName` | Plural display name (without "Manager") |
| `shortlinkHelper.fullName` | Full plugin name (as configured) |
| `shortlinkHelper.lowerDisplayName` | Lowercase display name (singular) |
| `shortlinkHelper.pluralLowerDisplayName` | Lowercase plural display name |

### Examples

```twig
{{ shortlinkHelper.displayName }}
{{ shortlinkHelper.pluralDisplayName }}
{{ shortlinkHelper.fullName }}
{{ shortlinkHelper.lowerDisplayName }}
{{ shortlinkHelper.pluralLowerDisplayName }}
```

---

