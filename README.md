# Timesheet Management System

Phase 1 replaces the Excel/email weekly timesheet process with a Laravel web application for employee submission, HOD approval, admin monitoring, and export.

## Tech Stack

- Laravel 11 style application
- Blade + Bootstrap 5
- MySQL
- Laravel session authentication
- Role middleware for `super_admin`, `admin`, `hod`, and `employee`
- Native XLSX export service for weekly timesheet workbooks

## Installation

Install PHP 8.2+, Composer, and MySQL first. Then run:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database named `timesheet_program`, or update `.env` with your database name and credentials.

```bash
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Demo Logins

All seeded accounts use this password:

```text
password123
```

| Role | Email |
| --- | --- |
| Super Admin | superadmin@example.com |
| Admin | admin@example.com |
| Operations HOD | ops.hod@example.com |
| Engineering HOD | eng.hod@example.com |
| Employee | aisha@example.com |
| Employee | ben@example.com |
| Employee | carla@example.com |
| Employee | daniel@example.com |
| Employee | fatima@example.com |

## Role Permissions

- Super Admin: manage users, departments, projects/job numbers, weekly periods, view all timesheets, approve or reject submitted records if needed, and export.
- Super Admin can also view audit logs for important system and timesheet actions.
- Employee numbers are manually entered by Super Admin and must follow `MEC-HR-YYYY-NNN` or `MCE-HR-YYYY-NNN`; the final number must be at least 3 digits and can grow beyond 999.
- Admin: view all timesheets, filter records, monitor dashboard summaries, and export.
- Head of Department: view only their department employees and timesheets, approve submitted timesheets, reject with a required comment, and track missing submissions.
- Employee: create weekly timesheets, save drafts, submit for approval, view history, edit only draft or rejected timesheets, and resubmit rejected records.

## User Management

Super Admin users can create, edit, activate/deactivate, and delete users from **Manage Users**.

- Employee numbers are entered manually when creating or editing employees and HODs.
- Super Admin cannot delete their own account.
- Deleting a user permanently removes the user and the timesheets owned by that user.
- Timesheet entries are also deleted because they belong to the deleted user's timesheets.
- Approval history is preserved where possible: if a deleted user previously approved or rejected another user's timesheet, the approver/rejector reference is set to blank.
- If the deleted user is assigned as the HOD of a department, Super Admin must select a replacement HOD before deletion.
- The replacement HOD must be an active HOD in the same department so department approvals continue to work.
- If no replacement HOD exists, create or update another HOD for that department before deleting the current HOD.

## Department And Project Management

Super Admin users can manage departments and projects/job numbers from the **Manage** area.

- Departments and projects can be activated or deactivated.
- Deactivated departments are hidden from new user department assignment, but existing users and historical timesheets keep their department.
- Deactivated projects are hidden from new timesheet project selection, but historical timesheets and exports keep the original project/job number.
- Departments can only be permanently deleted when they have no users, no timesheets, and no assigned HOD.
- Projects can only be permanently deleted when they have no timesheet entries.
- If a department or project already has historical usage, deactivate it instead of deleting it.

## Main Workflow

1. Super Admin creates departments, users, projects, and weekly periods.
2. Employee creates one timesheet per open weekly period.
3. Employee enters daily rows against project/job numbers.
4. A day can contain multiple project rows, including overtime-only rows after normal working hours.
5. Employee saves as draft or submits.
6. Submitted timesheets are locked for the employee.
7. Employee can recall a submitted timesheet before HOD action, returning it to draft for correction.
8. HOD approves or rejects department timesheets.
9. Rejected timesheets show the rejection comment and become editable by the employee.
10. Admin and Super Admin monitor all records and export native XLSX timesheet workbooks.

## Export

The export route is available to Admin and Super Admin:

```text
/admin/timesheets/export
```

It streams a native `.xlsx` workbook using Laravel Excel. The first worksheet is a project summary that combines regular, overtime, and total hours by project across all exported users. The remaining worksheets mirror the employee weekly timesheet layout: employee details, week number, attendance/project codes, weekday RT/OT columns, weekend columns, totals, and remarks.

## Testing

The project has automated tests for the Laravel backend flows and browser-level end-to-end checks.

### Unit Tests

Unit tests live in `tests/Unit`.

Use unit tests for small isolated logic that does not need a browser or full HTTP request, such as model helpers, value calculations, or service behavior.

Run all PHPUnit tests:

```bash
composer test
```

Run only unit tests:

```bash
php artisan test --testsuite=Unit
```

### Feature / Functional Tests

Feature tests live in `tests/Feature`.

These tests exercise real Laravel routes, middleware, validation, database writes, redirects, and authorization rules. They currently cover:

- login and inactive user access
- role-based access control
- dashboard rendering for each role
- employee timesheet creation, draft, submission, recall, rejection, and resubmission
- timesheet edge cases such as duplicate weeks, closed periods, blank weekend attendance, missing project/code, and invalid dates
- HOD approval and rejection rules
- admin and super admin approval/export access
- user, department, project, period, and audit-log management flows

Run only feature tests:

```bash
php artisan test --testsuite=Feature
```

Run one specific test file:

```bash
php artisan test tests/Feature/EmployeeTimesheetWorkflowTest.php
```

### Integration Testing

The feature tests also act as integration tests because they verify multiple layers working together:

- HTTP routes
- middleware
- form request validation
- Eloquent models and relationships
- migrations
- audit logging
- Excel export response generation

The test environment uses an in-memory SQLite database through `phpunit.xml.dist`, so tests run quickly and do not touch the local MySQL/MariaDB data.

### End-To-End Tests

End-to-end tests live in `tests/E2E` and use Playwright.

Install Node dependencies:

```bash
npm install
```

Prepare a seeded test database:

```bash
php artisan migrate:fresh --seed
```

Run the browser tests:

```bash
npm run test:e2e
```

Run the browser tests in headed mode so you can watch the browser:

```bash
npm run test:e2e:headed
```

Run headed mode more slowly so each browser action is easier to follow:

```bash
npm run test:e2e:headed:slow
```

The slow headed script uses `E2E_SLOW_MO=500` by default. To use a different delay, set `E2E_SLOW_MO` before running Playwright.

Open the Playwright UI runner:

```bash
npm run test:e2e:ui
```

By default, Playwright starts the Laravel development server at `http://127.0.0.1:8000`. To test an already-running app:

```bash
E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://127.0.0.1:8000 npm run test:e2e
```

On Windows PowerShell:

```powershell
$env:E2E_SKIP_WEBSERVER="1"
$env:E2E_BASE_URL="http://127.0.0.1:8000"
npm run test:e2e
```

For headed mode against an already-running app on Windows PowerShell:

```powershell
$env:E2E_SKIP_WEBSERVER="1"
$env:E2E_BASE_URL="http://127.0.0.1:8000"
npm run test:e2e:headed
```

For slow headed mode against an already-running app on Windows PowerShell:

```powershell
$env:E2E_SKIP_WEBSERVER="1"
$env:E2E_BASE_URL="http://127.0.0.1:8000"
npm run test:e2e:headed:slow
```

To run one browser project only:

```bash
npx playwright test --project=chromium --headed
```

To run one browser project slowly:

```powershell
$env:E2E_SLOW_MO="500"
npx playwright test --project=chromium --headed
```

### Recommended Test Routine

Before committing backend or validation changes:

```bash
composer test
```

Before releasing to production:

```bash
composer test
npm run test:e2e
```

When a bug is found, add or update a test that reproduces the bug first, then fix the code and rerun the relevant test file.

## Phase 1 Limitations

- Registration is disabled; Super Admin creates users.
- Export uses Laravel Excel and generates native `.xlsx` files.
- Approved timesheet correction is intentionally not implemented.
- Notifications, payroll, leave management, Teams integration, Power BI, mobile app, and advanced analytics are outside Phase 1.
- User deletion is permanent in Phase 1; deactivate a user instead if historical ownership should be retained.
- Department and project deletion is restricted to unused records; use deactivate/archive for anything with history.

## Suggested Phase 2 Improvements

- Laravel Excel XLSX exports and scheduled export delivery.
- Email or Teams approval notifications.
- Delegated approval and temporary HOD coverage.
- Payroll integration and locked payroll periods.
- Rich reporting dashboards and Power BI dataset sync.
- Correction workflow for approved timesheets with audit approval.
