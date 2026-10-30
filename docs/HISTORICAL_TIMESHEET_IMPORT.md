# Historical timesheet import

Super admins can open **All Timesheets → Import historical timesheets**. The import is separate from employee submission and approval workflows. It does not send approval emails, create leave plans, or deduct leave balances.

## Supported workbooks

- `.xlsx` and `.xlsm`, using the existing **Employee Time Record** worksheet template.
- Employee code in `Q8`, employee name in `D8`, Monday start date in `C5`, project/attendance headers in `B15:C15`.
- Detail rows start at 17 and end at the `TOTAL` marker in column C (up to row 100).
- Weekday regular/overtime pairs: `D/E`, `F/G`, `H/I`, `J/K`, `L/M`. Saturday and Sunday regular hours: `O` and `P`. Remarks: `X`.
- Only saved formula results are read. Formulas and macros are never executed. Missing saved results require recalculating and saving the workbook in Excel before uploading again.
- Default limits: 20 files, 5 MB per file, 20 MB combined, 32 MB expanded per workbook, 2,000 ZIP members, and 500 daily entries per timesheet. Web-server/PHP upload limits must accommodate the batch; PHP's lower limits still apply.
- Extracted preview data is limited to 8 MB per batch, and imported cell text to 2,000 characters, to bound memory use even for highly compressed workbooks.

The importer does not infer employee identity or dates from filenames. It requires an existing employee, department, project (for project-based work), and matching Monday–Sunday period. Historical periods may be closed, but must have ended before today. Missing periods are created through the existing period management screen, not implicitly by the importer.

## Review and edits

Each workbook is checked in upload order. An existing active timesheet or a later workbook for the same employee/week is a conflict and cannot overwrite an existing record. Voided timesheets follow the existing replacement policy: they do not occupy the active employee/week slot. The first workbook in a batch owns that slot even when it needs correction.

One workbook is editable at a time. Dates, attendance, project numbers, discipline, manpower categories, hours and remarks can be corrected. Employee identity and week remain tied to the source workbook. Set both hour fields to zero to exclude an entry. Use **Apply to matching entries** to classify entries for a project and discipline together. **Save changes and refresh preview** recomputes validation, totals, and the actual shared timesheet display. Unsaved edits disable confirmation in the browser. Revision checks reject stale tabs and incomplete previews.

Inactive employees, inactive projects/disciplines, and former project assignments produce warnings. They do not bypass reference or allocation validation. Hours must be nonnegative with at most two decimal places, with combined daily hours across all entries at most 24. Leave entries cannot contain projects or overtime. Historical attendance uses the configured code vocabulary rather than today's employee entitlement eligibility.

Every project-based entry must have an allocated discipline. Controlled disciplines also require a valid manpower category. Current assignment categories are suggestions; admin-selected historical categories are saved as entry snapshots without changing current assignments. Department totals, reserved category pools, and the shared pool are all enforced. Ready workbooks reserve preview capacity in upload order. Reallocate through project management and refresh/re-upload if capacity is insufficient.

## Confirmation, history and retries

Confirmation processes only workbooks that were ready in the saved preview. It revalidates references, duplicate conflicts, hours and allocations using current database state. Earlier successful imports in the same batch consume capacity for later ones.

Each timesheet, its entries, its history entry and its audit record are written in one database transaction. One failure does not roll back unrelated successes. An employee row lock serialises imports for the same employee; existing allocation locks and the production active employee/week unique index protect capacity and duplicates against concurrent submissions. The batch has a process-held exclusive file lock to prevent concurrent edits, confirmation, or cancellation. Repeated confirmation displays the existing receipt.

Imported records have `approved` status. `approved_by`, `approved_at` and `submitted_at` remain null because the original approver and dates are not reliably available. The **Historical Timesheet Imported** history entry records the importing super admin and actual import time, source filename, worksheet, SHA-256 fingerprint, batch ID, warnings, original entries, and edited entries. It states that the timesheet was imported as previously approved. Original approval details are not fabricated.

The results list each workbook as imported, skipped, or failed, with a reason and a link for successes. Retry failed workbooks after correcting their data or project allocation. Already imported timesheets will be skipped. A process crash after a timesheet commits but before its receipt is written is also recoverable by re-uploading: the existing timesheet prevents duplication.

## Temporary data and deployment

Uploads are moved into private temporary storage and deleted in a `finally` block after parsing, including parse failure. PHP temporary upload files are explicitly removed after request validation success or failure. There is no workbook retained for the preview or confirmation.

Normalised preview rows are encrypted with the application key, scoped to the uploading super admin and expire after 60 minutes. On completion/cancellation they are replaced by a minimal result receipt, without staged entries. Import history/audit metadata remains permanently for traceability.

`timesheets:prune-imports` runs every ten minutes through the existing Laravel scheduler. It deletes expired previews/receipts and workbook upload leftovers older than an hour, skips locked batches, and tolerates old corrupted previews. Ensure the Laravel scheduler is running in deployment. Application cleanup cannot promise immediate deletion after a machine/process crash or forensic erasure; abandoned workbook deletion depends on the scheduler.

The default private directory is `storage/app/private/timesheet-imports`; `TIMESHEET_IMPORT_DIRECTORY` can override it. It must not be web-accessible or included in backups. For multiple application nodes, use shared private storage with reliable `flock` semantics. Preview data is encrypted, but temporary workbook files are not; use encrypted storage where required. No new database migrations are needed.

## Verification

Run focused coverage with:

```sh
php artisan test --parallel --processes=4 tests/Feature/HistoricalTimesheetImportTest.php
```

Run the full Laravel regression suite with `composer test`. Tests use synthetic workbooks and the guarded in-memory SQLite database; they do not depend on the external sample folder or import sample data into the live database. Production MySQL row-lock contention is not reproduced by SQLite; the import reuses the existing allocation-locking path and active-period unique index.
