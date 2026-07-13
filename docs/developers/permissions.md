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

Two taxonomy actions have an extra requirement: the **Set Folder** and **Add Tags** bulk actions additionally require `createTaxonomy` when the folder or tag you type doesn't exist yet (they auto-create it). Assigning an *existing* folder or tag only needs `editLinks`.

## Multisite: the native `editSite` permission

On multi-site installs, ShortLink Manager's own permissions are not the whole story. Editing, deleting, or duplicating a short link also requires Craft's **native site permission** (`editSite:<site-uid>` — the "Edit site" checkbox under the site's name in the user group's permissions) for the site the link belongs to. A user with `editLinks` but no edit access to the link's site cannot modify that link.

The same site scoping applies across the plugin:

- **Folder & tag bulk actions** only affect links on sites the user can edit — links on other sites are silently skipped.
- **CSV export** includes only links from sites that are both enabled for the plugin *and* editable by the exporting user. If no sites qualify, the export shows "No shortlinks to export."
- **CSV import** counts a row as failed when it targets a site the importing user can't edit (or a site not enabled for the plugin) — see [Import & Export](../feature-tour/import-export.md).

On single-site installs none of this applies — the plugin's own permissions are sufficient.
