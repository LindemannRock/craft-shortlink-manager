# Permissions

Control what each user group can see and do in ShortLink Manager. Assign permissions via **Settings → Users → User Groups → [Group Name] → ShortLink Manager**.

ShortLink Manager registers 20 permissions across 7 areas.

## Permission structure

### Short links

| Permission | Description |
|------------|-------------|
| **`shortLinkManager:manageLinks`** | Parent — view short links and access the CP section |
| └─ `shortLinkManager:createLinks` | Create new short links |
| └─ `shortLinkManager:editLinks` | Edit existing short links |
| └─ `shortLinkManager:deleteLinks` | Delete short links |

### Folders & tags

| Permission | Description |
|------------|-------------|
| **`shortLinkManager:manageTaxonomy`** | Parent — access the Folders & Tags CP section |
| └─ `shortLinkManager:createTaxonomy` | Create folders and tags |
| └─ `shortLinkManager:editTaxonomy` | Rename folders and tags |
| └─ `shortLinkManager:deleteTaxonomy` | Delete folders and tags |

### Analytics

| Permission | Description |
|------------|-------------|
| **`shortLinkManager:viewAnalytics`** | Parent — access the analytics dashboard |
| └─ `shortLinkManager:exportAnalytics` | Export analytics data to CSV or JSON |
| └─ `shortLinkManager:clearAnalytics` | Clear all analytics data |

### Import / export

| Permission | Description |
|------------|-------------|
| **`shortLinkManager:manageImportExport`** | Parent — access the Import/Export CP section |
| └─ `shortLinkManager:importLinks` | Upload and import short links from CSV |
| └─ `shortLinkManager:exportLinks` | Export short links to CSV |
| └─ `shortLinkManager:clearImportHistory` | Clear the import history log |

### Cache

| Permission | Description |
|------------|-------------|
| `shortLinkManager:clearCache` | Clear QR code and device detection caches |

### Logs

| Permission | Description |
|------------|-------------|
| **`shortLinkManager:viewLogs`** | Parent — access the logs section |
| └─ `shortLinkManager:viewSystemLogs` | View system-level plugin logs |
|     └─ `shortLinkManager:downloadSystemLogs` | Download log files |

### Settings

| Permission | Description |
|------------|-------------|
| `shortLinkManager:manageSettings` | Access and modify plugin settings |

## Checking permissions

In Twig:

```twig
{% if currentUser.can('shortLinkManager:manageLinks') %}
    <a href="{{ url('shortlink-manager') }}">Manage Short Links</a>
{% endif %}

{% if currentUser.can('shortLinkManager:viewAnalytics') %}
    <a href="{{ url('shortlink-manager/analytics') }}">View Analytics</a>
{% endif %}
```

In PHP:

```php
if (Craft::$app->getUser()->checkPermission('shortLinkManager:manageLinks')) {
    // User can manage links
}

// In a controller
$this->requirePermission('shortLinkManager:editLinks');
```

## Nested permission pattern

Craft's nested permissions are a UI convenience — the parent permission does not automatically grant child permissions at runtime.

- **`manageLinks`** grants CP subnav visibility and the ability to view short links. It is checked by `canView()` on the element and by the CP nav (`permissionsAll`)
- **Write permissions** (`createLinks`, `editLinks`, `deleteLinks`) are nested under `manageLinks` and control specific write operations

To give a user **read-only** access to short links, grant only `manageLinks` (without any nested write permissions).

To give a user **full access**, grant `manageLinks`, `createLinks`, `editLinks`, and `deleteLinks`.

The same pattern applies to analytics: `viewAnalytics` grants access to the analytics dashboard, while `exportAnalytics` and `clearAnalytics` are optional write operations.

The same pattern applies to import/export: `manageImportExport` grants access to the Import/Export CP section and import history. Individual child permissions (`importLinks`, `exportLinks`, `clearImportHistory`) control specific operations.

The same pattern applies to folders & tags: `manageTaxonomy` grants access to the Folders & Tags CP section. Child permissions (`createTaxonomy`, `editTaxonomy`, `deleteTaxonomy`) control write operations on the taxonomy entries themselves.
