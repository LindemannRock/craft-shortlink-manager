# Translations

ShortLink Manager ships with translations for 12 languages. The Control Panel automatically uses the user's preferred language from their Craft account settings — no extra configuration required.

## Supported languages

| Language | Code |
|----------|------|
| English | `en` |
| German | `de` |
| French | `fr` |
| Dutch | `nl` |
| Spanish | `es` |
| Arabic | `ar` |
| Italian | `it` |
| Portuguese | `pt` |
| Japanese | `ja` |
| Swedish | `sv` |
| Danish | `da` |
| Norwegian | `no` |

## Overriding translations

To replace any translation string, create a static translation file in your project:

```
translations/
└── de/
    └── shortlink-manager.php
```

```php
<?php

return [
    'Settings' => 'Konfiguration',  // Override the default "Einstellungen"
];
```

Only the keys you include are replaced — all other strings use the plugin's built-in translations.

See [Craft's Static Translation Strings](https://craftcms.com/docs/5.x/system/sites.html#static-message-translations) for details.

### Using Translation Manager

If you have [Translation Manager](https://github.com/LindemannRock/craft-translation-manager) installed, you can manage overrides directly from the Control Panel:

1. Add a new translation category using the plugin handle (`shortlink-manager`)
2. Edit translations through the Translation Manager interface

Available languages are based on the site languages active in your Craft installation.

## Contributing translations

If you find a translation error or want to improve a translation, please [open an issue](https://github.com/LindemannRock/craft-shortlink-manager/issues) with:

- The language affected
- The current (incorrect) string
- Your suggested correction
