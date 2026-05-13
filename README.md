# Timesheet Management System

Phase 1 replaces the Excel/email weekly timesheet process with a Laravel web application for employee submission, HOD approval, admin monitoring, and export.

## Tech Stack

- Laravel 11 style application
- Blade + Bootstrap 5
- MySQL
- Laravel session authentication
- Role middleware for `super_admin`, `admin`, `hod`, and `employee`
- CSV export service placeholder that can be replaced with Excel export later

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
10. Admin and Super Admin monitor all records and export CSV data.

## Export

The export route is available to Admin and Super Admin:

```text
/admin/timesheets/export
```

It streams a CSV with employee, department, period, date, project, hours, status, and approval fields. The `App\Services\TimesheetExportService` class is intentionally isolated so a package such as Laravel Excel can be added later without changing controllers.

## Phase 1 Limitations

- Registration is disabled; Super Admin creates users.
- Export is CSV, not native XLSX.
- Approved timesheet correction is intentionally not implemented.
- Notifications, payroll, leave management, Teams integration, Power BI, mobile app, and advanced analytics are outside Phase 1.
- Confirmation is browser-based for Phase 1; Bootstrap modal styling can be added later without changing the workflow.

## Suggested Phase 2 Improvements

- Laravel Excel XLSX exports and scheduled export delivery.
- Email or Teams approval notifications.
- Delegated approval and temporary HOD coverage.
- Payroll integration and locked payroll periods.
- Rich reporting dashboards and Power BI dataset sync.
- Correction workflow for approved timesheets with audit approval.
