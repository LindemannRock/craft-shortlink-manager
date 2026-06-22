# Import & export

Move short links in bulk using CSV. Export your entire library, edit it offline, and re-import — or use a CSV to migrate links from another system. A four-step wizard walks you through upload, column mapping, preview, and import, with a history log of every past run.

## What you'll use it for

- Migrating a batch of links from another URL shortener or spreadsheet
- Bulk-editing destination URLs, QR settings, or folder/tag assignments offline
- Seeding a new Craft site with links that already exist in another environment
- Auditing your link library by exporting to CSV and reviewing in a spreadsheet

## Access

Navigate to **ShortLink Manager → Import/Export**. The page shows three sections based on your permissions:

- **Export** — download all short links as a CSV file (requires `shortLinkManager:exportLinks`)
- **Import** — upload a CSV file and map columns to short link fields (requires `shortLinkManager:importLinks`)
- **Import History** — log of past imports (requires `shortLinkManager:manageImportExport`)

## Exporting short links

Click **Export CSV** to download all short links across all sites. The CSV includes the following columns:

| Column | Description |
|--------|-------------|
| `code` | The short link's unique code/slug |
| `shortLinkType` | `manual` or `auto` (field-managed) |
| `linkType` | `code` or `vanity` |
| `destinationUrl` | The URL the short link redirects to |
| `elementId` | Linked Craft element ID (field-managed links only) |
| `elementType` | Linked element class (field-managed links only) |
| `httpCode` | HTTP redirect status (e.g., `301`, `302`) |
| `enabled` | `1` or `0` |
| `siteId` | Numeric site ID |
| `siteHandle` | Site handle (e.g., `default`, `en`) |
| `folder` | Folder name (empty if unassigned) |
| `tags` | Comma-separated tag names (empty if none) |
| `trackAnalytics` | `1` or `0` |
| `passQueryParams` | `1`, `0`, or empty (inherits global setting) |
| `directRedirect` | `1`, `0`, or empty (inherits global setting) |
| `qrCodeEnabled` | `1` or `0` |
| `qrCodeSize` | QR code size in pixels |
| `qrCodeColor` | Foreground color (hex, e.g., `#000000`) |
| `qrCodeBgColor` | Background color (hex) |
| `qrCodeEyeColor` | Eye color override (hex, empty to use `qrCodeColor`) |
| `qrCodeFormat` | `png` or `svg` |
| `qrLogoId` | Asset ID for QR logo (empty if none) |
| `postDate` | Post date |
| `dateExpired` | Expiry date (empty if not set) |

The export filename is generated automatically using the plugin's standard filename pattern (e.g., `shortlink-manager-export-2026-01-15-143000.csv`).

## Importing short links

Import uses a four-step wizard:

![Import wizard showing column mapping screen with CSV preview rows](images/import-export-wizard.webp)

### Step 1: Upload

Select your CSV file and click **Upload**. The importer accepts:

- UTF-8 encoded CSV files
- Auto-detected delimiter (comma, semicolon, or tab) — or specify a delimiter manually
- Maximum 5,000 rows and 5 MB per file (base plugin defaults)

### Step 2: Map columns

After upload, a column mapping screen shows the first 5 rows of your CSV as a preview. For each column in your file, select the corresponding short link field from a dropdown.

The **`code`** field is required. All other fields are optional — unmapped columns are ignored.

### Step 3: Preview

Review the import before committing:

- **Valid rows** — rows that will be imported
- **Duplicate rows** — rows where the code already exists in the database or appears more than once in the file (these are skipped)
- **Error rows** — rows with validation issues (missing required fields, invalid URLs, invalid QR format, etc.)

### Step 4: Import

Confirm to import. The importer creates new short links from all valid rows. Each imported link is saved through the standard `ShortLinksService::saveShortLink()` pipeline (including validation, cache invalidation, and analytics initialization).

After import, a notice shows how many links were imported and how many failed. The import is recorded in the import history.

## Multi-site imports

For multi-site setups, include both `siteId` and/or `siteHandle` columns. If both are present, `siteHandle` is used to resolve the site ID. If neither is mapped, all links are imported into the current (default) site.

To import the same short link code across multiple sites, include one row per site with the same `code` value and different `siteId`/`siteHandle` values.

## Import validation rules

| Rule | Detail |
|------|--------|
| `code` required | Rows without a code are skipped as errors |
| Unique code | Codes that already exist in the database are skipped as duplicates |
| `destinationUrl` required for manual links | `shortLinkType: manual` rows without a destination URL are skipped as errors |
| URL format | Destination URL must start with `https://`, `http://`, or `/`. Dangerous schemes (e.g. `javascript:`, `data:`), including obfuscated variants, are always rejected. |
| QR format | `qrCodeFormat` must be `png`, `svg`, or empty |
| Element must exist | `shortLinkType: auto` rows require a valid `elementId` pointing to a Craft element with a URL |
| `folder` | Creates the folder automatically if it doesn't exist |
| `tags` | Creates tags automatically if they don't exist; comma-separated in the CSV cell |
| `passQueryParams` / `directRedirect` | `1`/`true`/`yes` → `true`; `0`/`false` → `false`; empty → `null` (inherits global) |
| Hex colors | Auto-normalized to uppercase `#RRGGBB` format; invalid values are cleared |
| `qrCodeSize` | Clamped to 100–1000 pixels |

## Import history

The **Import History** table shows the last 20 imports with:

- Date and time
- Username of the importer
- Original filename and file size
- Count of successfully imported links
- Count of failed rows

Click **Clear History** (requires `shortLinkManager:clearImportHistory`) to remove all history records.

## Permissions

| Permission | Access |
|------------|--------|
| `shortLinkManager:manageImportExport` | Access the Import/Export section |
| `shortLinkManager:exportLinks` | Download CSV export |
| `shortLinkManager:importLinks` | Upload and run imports |
| `shortLinkManager:clearImportHistory` | Clear all import history records |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
