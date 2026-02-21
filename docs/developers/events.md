# Events

ShortLink Manager dispatches events that let you hook into settings changes from your own plugin or module.

## Overview

| Model | Event | Use Case |
|-------|-------|----------|
| `Settings` | `EVENT_AFTER_SAVE_SETTINGS` | React to settings changes (cache clearing, re-registration of routes, notifications) |

## Settings Events

### `EVENT_AFTER_SAVE_SETTINGS`

Fired after plugin settings are saved from the control panel. Use this to react to configuration changes — for example, to clear custom caches, update external services, or trigger route re-registration.

```php
use lindemannrock\shortlinkmanager\models\Settings;
use yii\base\Event;

Event::on(
    Settings::class,
    Settings::EVENT_AFTER_SAVE_SETTINGS,
    function(Event $event) {
        /** @var Settings $settings */
        $settings = $event->sender;

        // React to settings changes
        Craft::info(
            'ShortLink Manager settings updated',
            'my-module'
        );
    }
);
```
