# ShortLink Manager — Manual CSV Fixtures

**Manual QA CSV fixtures** for the CSV import flow through the CP UI (Import/Export → upload → map columns → preview → import). Not loaded by PHPUnit — automated coverage is in `../Integration/ImportUrlValidationTest`.

ShortLink imports a **`destinationUrl`** that becomes a redirect target. Validation (`ImportExportController::isValidDestinationUrl()`):

- Must match `^https?://` **or** `^/` — i.e. an absolute http(s) URL **or a relative path**.
- Executable schemes (`javascript:`, `vbscript:`, `data:`, `file:`) are rejected up front by `UrlSafetyHelper::hasDangerousScheme()`, including whitespace/`//`-obfuscated variants.
- **`mailto:` / `tel:` / `ftp:` are rejected** here (they don't match the allowlist) — this differs from SmartLink (which accepts `mailto:`/`ftp:`) and from Redirect Manager (which accepts `mailto:`/`tel:`). Relative paths, conversely, **are** allowed here but rejected by SmartLink.

All files share the header: `code,destinationUrl,httpCode,enabled`. Rows default to a manual short link (`shortLinkType=manual`, `linkType=vanity`); `code` and `destinationUrl` are required.

## Test Files

### `shortlink-valid.csv` — positive control
Should import cleanly: `https://`, `http://`, a relative `/landing-page` (the ShortLink-specific case), and a query-string URL.

### `shortlink-malicious.csv` — security
Should be blocked/neutralized:
- `javascript:` in `destinationUrl` (plain, `//%0a`-obfuscated, leading-space)
- `data:text/html`, `vbscript:`, `file:///`
- CSV formula in the `code` cell (`=cmd|'/c calc'`) — the formula prefix is stripped and the code is slugified, so the row imports with a neutralized code (`cmd-c-calc`), not a formula.

### `shortlink-edge-cases.csv` — boundary conditions
- Missing `destinationUrl` (required → error)
- Empty `code` (required → error)
- Bare `example.com` (no scheme, no leading `/` → rejected)
- `//evil.com` (protocol-relative → **rejected**; it resolves off-site, and the runtime redirect refuses it anyway, so input rejects it too)
- `mailto:` and `ftp:` destinations (**rejected** here — note the contrast with SmartLink/Redirect)

## How to run a pass

1. **Baseline:** export current short links to confirm the round-trip format.
2. **Valid:** import `shortlink-valid.csv`; every row should preview as importable (confirm the relative `/landing-page` row is **accepted**).
3. **Malicious:** import `shortlink-malicious.csv`; confirm every dangerous-scheme row lands in **errors**, none reach the DB, and the formula `code` cell is neutralized (no leading `=` survives to a re-export).
4. **Edge cases:** import `shortlink-edge-cases.csv`; confirm required-field errors are clear and `mailto:`/`ftp:` reject as documented.

## Expected behavior summary

| Input (destinationUrl) | Expected |
|---|---|
| `javascript:` / `vbscript:` / `data:` / `file:` (+ obfuscated) | Rejected |
| `https://…`, `http://…`, relative `/path` | Accepted |
| Bare `domain.com` | Rejected (no scheme/leading slash) |
| `//host` | Rejected (protocol-relative resolves off-site; runtime refuses it too) |
| `mailto:` / `tel:` / `ftp:` | Rejected (not in the http(s)/relative allowlist) |
| Leading `=`/`@`/etc. in `code` | Formula prefix stripped, then slugified |
| Missing `code` or `destinationUrl` | Rejected with a clear error |
