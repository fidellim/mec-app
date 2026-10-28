# Project analytics and confidential labour costs

The existing project Analytics tab remains available to super admins and the project's own manager. Confidential cost controls and their nonfinancial calculation basis are rendered only for super admins. No new routes or database fields are introduced.

## Local-only rate files

Choose a currency and select a local `.xlsx` or `.xls` workbook. Required headings (case-insensitive) are `Employee ID`, `Initials`, `Employee Name`, and `Rate/Manhour`, in the first 20 rows. One worksheet must contain those headings. Other workbook sheets may exist but must not contain a second rate table. The import accepts up to 5 MB and 20,000 employee rows; worksheets are limited to 256 columns.

The file control has no form or name. Its selection is cleared immediately. An ArrayBuffer is transferred to a dedicated browser Worker, which reads the workbook using a locally bundled SheetJS CE reader. The Worker retains a validated Employee ID-to-rate map, not the workbook. Its only network loads are static, same-origin JavaScript dependencies. No financial upload endpoint exists. Workbook contents, file names, rates, currency selection, and calculated monetary results are not included in requests, URLs, application storage, telemetry or logs by this feature.

Employee IDs match `users.employee_code` after trimming and uppercasing, preserving leading zeros. Names are verification aids, never matching keys. Duplicates, blank IDs, invalid/nonpositive rates and missing headings reject the import. Rates support up to six decimal places. Excel numeric cells are normalized to that precision. Formula cells use saved numeric results; recalculate and save before importing. Missing project employees remain unpriced, with hours and identifiers shown explicitly. Unused imported employees are not an error. Name mismatches are reported for review.

Only nonfinancial project aggregates are sent by Laravel: employee code/name, month, charged department, historical manpower category, approval status, and regular/overtime hours. Financial calculations operate on the selected period, not the lifetime allocation. No salary or cost data is accepted by the controller or service.

## Financial basis

- Regular labour cost = regular hours × imported employee rate.
- Overtime labour cost = overtime hours × employee rate × 1.25.
- Overtime premium = overtime hours × employee rate × 0.25; it is already included in overtime cost.
- Total recorded labour cost = approved cost + pending submitted cost.

Decimal integer arithmetic avoids floating-point multiplication. Regular and overtime costs are rounded half-up to two decimal places for each employee/month/charged-department/category/status aggregate, then summed. Each displayed group reconciles to the summary under this rounding policy. Supported currencies have two minor-unit decimal places. Currency is a label for the source rates; no conversion is performed.

One employee rate applies throughout the selected period. These are calculated salary-based labour costs, not payroll, invoices, project completion or profitability. Benefits/overhead are included only if already included in the supplied rates. Monetary budgets and forecasts are not invented from hour allowances, especially where category budgets share a pool.

## Lifetime and clearing

The map lives in its dedicated Worker; monetary results live in the page's memory and DOM. Clear financial data terminates the Worker, drops results and clears financial DOM content. A new import first clears the old overlay. An absolute 15-minute limit starts after successful import; filtering does not extend it. A visibility check also enforces expiry after a suspended tab resumes. Page exit, refresh, same-page logout submission, and back/forward restoration clear the view. Financial panels are hidden from browser printing. No financial export is provided.

Filtering sends only ordinary hours-filter GET parameters and obtains a fresh nonfinancial HTML response with `Cache-Control: private, no-store`. Rates remain in the existing Worker for local recalculation. Unexpected redirects or lost authorization clear the financial view. Refreshing/reopening always requires reimporting the workbook.

This prevents intentional application/server storage, not device-level forensic recovery. It does not delete or modify the original workbook, securely wipe browser-managed memory, prevent screenshots, or isolate the page from browser extensions or compromised scripts. Existing shared-layout third-party dependencies remain unchanged. Automated tests must use synthetic employee data and rate files.

## Verification

- `node --test tests/JS/project-cost.test.js`: real XLSX/XLS parsing, employee rates, overtime, precision/rounding, reconciliation, missing rates, invalid imports and identifiers.
- `php artisan test --parallel --processes=4 tests/Feature/ProjectAnalyticsTest.php`: permissions and project/date/status/department/category isolation.
- `PHP_BINARY=php E2E_APP_ENV=e2e npx playwright test tests/E2E/project-analytics-cost.spec.js --project=chromium`: local import, outgoing-request/storage checks, filtering, clearing, refresh, expiry, return navigation, and responsive themes. Uses existing seeded E2E accounts and synthetic browser-intercepted hours; it does not reset the database. Configure the normal E2E environment first.
- `composer test`: full Laravel regression suite.

SheetJS CE 0.20.3 is vendored from the official https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js, following https://docs.sheetjs.com/docs/getting-started/installation/standalone/ . Its license is at `public/js/vendor/SHEETJS-LICENSE.txt`. SHA-256 of the minified file: `cc015130aa8521e7f088f88898eba949ccdcbfb38df0bd129b44b7273c3a6f41`.
