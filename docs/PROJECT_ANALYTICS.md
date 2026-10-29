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

## Project finance summary

Super admins can also load a local Excel project summary with six headings in the first 20 rows: `Project Code`, `Order Value`, `Invoice Submitted`, `Subcontractor Spent`, `Subcontractor Committed Value`, `Manhour Spent`. Column order does not matter. The committed-value column may be omitted for existing five-column workbooks; its value is then unknown. Exactly one worksheet must contain the schema. Matching trims whitespace and ignores case but otherwise requires the current project's exact code. Only the matching row is returned by the short-lived reader worker; unrelated project figures are discarded. Missing matches and duplicate matching rows reject the import. No routes, financial database fields or upload endpoints are added.

Amounts are cumulative AED values excluding VAT through the applied To date. The user must confirm this cutoff before selecting a workbook. Required amounts must be nonnegative numbers (explicit zero accepted); Manhour Spent and Subcontractor Committed Value may be blank. Blank commitment means unknown, not zero. Text amounts accept two decimals without separators or currency symbols; numeric saved Excel values round to two decimals. Limits are 5 MB, 20,000 worksheet rows, 256 columns and AED 1 trillion per amount. Formula cells require saved numeric results. Each successful import replaces the prior snapshot, never adds to it.

An imported Manhour Spent value, including zero, overrides the platform calculation. Blank uses all approved project hours through the applied To date and AED employee rates, independently of From date. Pending, other-project and hours after the cutoff are excluded. Missing approved-hour employee rates or a different rate currency keep total cost unavailable; partial labour totals are not presented as a complete project cost. Worker results are associated with the displayed cutoff so stale results cannot price a different reporting period. Nonfinancial aggregates for this calculation are rendered only for super admins. One current employee rate applies across historical hours; effective-dated rates are not modeled.

Invoice Submitted represents certified work value before retention/advance recovery, excluding VAT. Subcontractor Spent represents actual payments excluding VAT. Overhead is Invoice Submitted × 8%, rounded half-up to cents; Total Cost Spent is labour + subcontractor payments + overhead; Balance for Invoicing is Order Value − Invoice Submitted. The management view compares order, invoicing and cost spent on one zero-based AED scale, with signed balances beside it and a stacked cost breakdown below. Order less cost and invoicing less cost are balances, not profit. Over-invoicing remains visible as a negative balance with a warning.

Subcontractor Committed Value is the total approved subcontract value, including variations and amounts already paid. Remaining commitment = committed value − payments made. The commitment stack separates payments and remaining value. Cost spent + remaining commitment adds only the unpaid approved balance, without double counting payments. Unknown commitments leave this combined figure unavailable. Payments greater than commitment show the signed negative balance and a warning; the combined figure remains unavailable pending review, rather than reducing cost spent. Future labour costs are excluded. No monthly trends or profit forecasts are inferred from this snapshot.

Project figures have their own absolute 15-minute expiry, and clear on page exit, refresh, logout, financial clearing or invalid/unauthorized filter responses. Changing To date or removing cutoff confirmation clears the imported figures and requires a fresh confirmed import. From-date-only changes preserve figures without extending expiry. The import worker is terminated immediately after success or failure; only the current project snapshot lives in document memory. Clear project figures preserves employee rates; Clear financial data removes both. Both confidential panels are omitted from printing.

The financial view scripts and worker URLs share a content fingerprint covering both views, both workers, and the labour calculation core. The rate worker propagates this version to its core dependency so previously cached files cannot silently omit the management labour result after a deployment. Deploy these files and their Blade partials together. The finance note distinguishes unloaded rates, a missing calculation result, wrong currency and unpriced approved hours (with missing IDs).

## Verification commands

- `node --test tests/JS/project-cost.test.js`: real XLSX/XLS parsing, employee rates, overtime, precision/rounding, reconciliation, missing rates, invalid imports and identifiers.
- `node --test tests/JS/project-finance.test.mjs`: project-specific matching, unrelated data exclusion, duplicate/missing matches, blank versus zero labour and invalid finance inputs.
- `php artisan test --parallel --processes=4 tests/Feature/ProjectAnalyticsTest.php`: permissions and project/date/status/department/category isolation.
- `PHP_BINARY=php E2E_APP_ENV=e2e npx playwright test tests/E2E/project-analytics-cost.spec.js --project=chromium`: local import, outgoing-request/storage checks, filtering, clearing, refresh, expiry, return navigation, and responsive themes. Uses existing seeded E2E accounts and synthetic browser-intercepted hours; it does not reset the database. Configure the normal E2E environment first.
- `composer test`: full Laravel regression suite.

SheetJS CE 0.20.3 is vendored from the official https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js, following https://docs.sheetjs.com/docs/getting-started/installation/standalone/ . Its license is at `public/js/vendor/SHEETJS-LICENSE.txt`. SHA-256 of the minified file: `cc015130aa8521e7f088f88898eba949ccdcbfb38df0bd129b44b7273c3a6f41`.
