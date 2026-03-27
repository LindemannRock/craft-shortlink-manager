# Folders & Tags

Organize your short links using a plugin-managed taxonomy system — folders for hierarchical grouping and tags for flexible, multi-value categorization.

## How It Works

Folders and tags are separate taxonomies stored in the plugin's own database tables. They are independent of Craft's native Folder, Tag, or Category elements.

**Folders** provide a single-level grouping (one folder per link). Use them to organize links into broad categories — by campaign, department, or project.

**Tags** allow multiple labels per link. Use them for cross-cutting attributes — audience, region, content type, or any other multi-value classification.

Both folders and tags are managed from the **Folders & Tags** section in the ShortLink Manager CP nav.

## Managing Folders & Tags

Navigate to **ShortLink Manager → Folders & Tags**. The section has a sidebar with two views:

- **Folders** — list of all folders with usage count (number of links in each folder)
- **Tags** — list of all tags with usage count

### Creating

Click **New Folder** or **New Tag** to create a new entry. Each requires a unique name. Names are normalized and converted to a slug automatically.

### Editing

Click a folder or tag name to open the edit form and rename it.

### Deleting

Use the row action menu to delete a single folder or tag. Deleting a folder also unassigns it from all linked short links. Deleting a tag removes it from the pivot table (all associations are cleared).

Select multiple rows and click **Delete (N)** to bulk delete.

## Assigning to Short Links

When creating or editing a short link, you can optionally assign:

- **Folder** — a single folder from a dropdown (populated from all existing folders)
- **Tags** — a comma-separated list of tag names; new tags are created automatically if they don't exist

Tags entered on the short link edit form are normalized and synced to the `shortlinkmanager_shortlink_tags` pivot table on save.

## Bulk Actions on the Short Link Index

The following bulk actions are available when selecting short links on the **Short Links** index:

| Action | Description |
|--------|-------------|
| **Add Tags** | Add one or more tags to all selected links (existing tags are preserved) |
| **Remove Tags** | Remove specific tags from all selected links |
| **Clear Tags** | Remove all tags from all selected links |
| **Set Folder** | Assign a folder to all selected links |
| **Clear Folder** | Remove the folder assignment from all selected links |

All bulk actions require the `shortLinkManager:editLinks` permission.

## Permission

Folder and tag management (create, edit, delete) requires the `shortLinkManager:editLinks` permission.

See [Permissions](../developers/permissions.md) for the full permission reference.

## Filtering by Folder or Tag

The short link index supports filtering by folder and tag. Use the **Folder** or **Tag** source filters in the element index sidebar to narrow the list.

## CSV Import & Export

Folder and tag data is included in CSV export and import:

- **`folder`** column — the folder name (empty if unassigned)
- **`tags`** column — comma-separated tag names (empty if none)

On import, if a folder name does not exist it is created automatically. Tags listed in the CSV are also created if they don't already exist.

See [Import & Export](import-export.md) for details on the CSV workflow.
