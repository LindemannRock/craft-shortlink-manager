# Dashboard Widgets

ShortLink Manager provides two Craft dashboard widgets for at-a-glance analytics without leaving the dashboard.

## Adding Widgets

Add widgets via **Dashboard → New Widget** and selecting either widget from the ShortLink Manager section. Both widgets require the `shortLinkManager:viewAnalytics` permission to display data.

## Analytics Summary Widget

The Analytics Summary widget shows an overview of click activity across your short links over a configurable time period.

### What It Shows

- **Total clicks** — all recorded click events in the selected date range
- **Top device types** — breakdown of phone, tablet, desktop, and other device categories
- **Top countries** — most common visitor countries (requires geo-detection enabled)
- **Click trend** — daily click counts as a sparkline or mini chart

### Configuration

Click the widget's settings icon to configure:

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| **Date Range** | `string` | `'last7days'` | Time period to summarize: `'today'`, `'yesterday'`, `'last7days'`, `'last30days'`, `'last90days'`, `'all'` |

### Multi-Site

In a multi-site setup, the widget shows analytics for all sites unless you configure a specific site scope. Site filtering is available within the widget settings.

## Top Links Widget

The Top Links widget shows which short links received the most clicks during the selected period.

### What It Shows

- **Link title** — the short link's title, linked to its edit page
- **Click count** — total clicks in the selected period
- **Trend indicator** — whether clicks are increasing or decreasing compared to the previous equivalent period

### Configuration

Click the widget's settings icon to configure:

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| **Date Range** | `string` | `'last7days'` | Time period: `'today'`, `'yesterday'`, `'last7days'`, `'last30days'`, `'last90days'`, `'all'` |
| **Limit** | `int` | `5` | Maximum number of links to display (1–20) |

### Multi-Site

The Top Links widget shows results across all sites by default. Configure a site scope in widget settings to restrict the results.

## Permissions

Both widgets require the `shortLinkManager:viewAnalytics` permission. Users without this permission will see a "You don't have permission to view analytics" message in place of widget content.

Admins always have access regardless of permission settings.

See [Permissions](../developers/permissions.md) for the full permission reference.
