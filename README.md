# MEC Portal

Phase 1 replaces the Excel/email weekly timesheet process with a Laravel web application for employee submission, Head of Department approval, admin monitoring, and export.

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
| Operations Head of Department | ops.hod@example.com |
| Engineering Head of Department | eng.hod@example.com |
| Employee | aisha@example.com |
| Employee | ben@example.com |
| Employee | carla@example.com |
| Employee | daniel@example.com |
| Employee | fatima@example.com |

## Role Permissions

- Super Admin: manage users, departments, projects/job numbers, weekly periods, view all timesheets, approve or reject submitted records if needed, and export. Super Admin cannot approve or reject their own timesheet.
- Super Admin can also manage scheduled automation controls, view audit logs for important system and timesheet actions, export audit logs to Excel, and delete audit logs when database cleanup is needed.
- Employee numbers are manually entered by Super Admin and must follow `MEC-HR-YYYY-NNN`, `MCE-HR-YYYY-NNN`, or `MEC-PHIL-HR-YYYY-NNN`; the final number must be at least 3 digits and can grow beyond 999.
- Employee initials are manually entered by Super Admin, are optional, and are used in weekly timesheet exports.
- Job titles are optional user profile details and appear in timesheet exports. Blank job titles export as `-`.
- Admin: view all timesheets, filter records, monitor dashboard summaries, export, and approve or reject Head of Department timesheets. Admin cannot approve or reject their own timesheet.
- Head of Department: view only their department employees and timesheets, approve submitted employee timesheets, reject employee timesheets with a required comment, and track missing submissions. Head of Department cannot approve or reject their own timesheet.
- Employee: create weekly timesheets, save drafts, submit for approval, view history, edit only draft or rejected timesheets, and resubmit rejected records.
- Admin and Super Admin department assignment is optional for system management, but required if they need to create or submit their own weekly timesheets.

## User Management

Super Admin users can create, edit, activate/deactivate, and delete users from **Manage Users**.

- Employee numbers are entered manually when creating or editing employees and HODs.
- Initials can be entered manually when creating or editing users. If initials are blank, exports fall back to initials derived from the employee name.
- Job Title can be entered manually when creating or editing users. It is optional, limited to 100 characters, and appears as `-` in exports when blank.
- Users without a department cannot create or submit their own timesheets. Assign a department first if Admin, Super Admin, Head of Department, or Employee accounts need to submit personal weekly time.
- When a user's department is changed, their existing draft and rejected timesheets move to the new department. Submitted and approved timesheets remain with the original department for review and historical reporting.
- Super Admin cannot delete their own account.
- Deleting a user permanently removes the user and the timesheets owned by that user.
- Timesheet entries are also deleted because they belong to the deleted user's timesheets.
- Approval history is preserved where possible: if a deleted user previously approved or rejected another user's timesheet, the approver/rejector reference is set to blank.
- If the deleted user is assigned as the Head of Department, Super Admin must select a replacement Head of Department before deletion.
- The replacement Head of Department must be active and in the same department so department approvals continue to work.
- If no replacement Head of Department exists, create or update another Head of Department for that department before deleting the current one.

## Password Reset

Employees and admins can reset forgotten passwords from the **Forgot password?** link on the sign-in page.

- Only active existing user accounts can request a password reset email.
- Reset links are emailed to the user's account address.
- Password reset links expire after 1 hour.
- The reset form requires the new password and confirmation to match.
- Sign-in and reset password fields include a show/hide control so users can review what they typed.
- Inactive accounts cannot use password reset. A Super Admin must reactivate the account first.

## Department And Project Management

Super Admin users can manage departments and projects/job numbers from the **Manage** area.

- Departments and projects can be activated or deactivated.
- Deactivated departments are hidden from new user department assignment, but existing users and historical timesheets keep their department.
- Deactivated projects are hidden from new timesheet project selection, but historical timesheets and exports keep the original project/job number.
- Departments can only be permanently deleted when they have no users, no timesheets, and no assigned Head of Department.
- Projects can only be permanently deleted when they have no timesheet entries.
- If a department or project already has historical usage, deactivate it instead of deleting it.

## Automation Controls

Super Admin users can pause or resume scheduled background jobs from **Manage Automations**.

- Disabled automations are skipped by Laravel's scheduler.
- Automation commands also check their automation setting, so direct command runs will not execute while disabled unless `--force` is used.
- Enable and disable actions are recorded in `audit_logs`.
- Existing manual actions, such as a Head of Department manually sending a reminder from the tracker, remain available.

Configured automations:

```text
timesheet_period_auto_creation
timesheet_missing_reminders
```

- `timesheet_period_auto_creation` creates the current Monday-to-Sunday weekly period if it does not exist yet.
- `timesheet_missing_reminders` sends the automatic Monday reminder for employees missing submitted or approved weekly timesheets.

### Automation Audit Actions

Automation runs write audit log actions so Super Admins can see whether scheduled jobs ran, skipped work, or were blocked.

| Automation | Action | When it is recorded |
| --- | --- | --- |
| Weekly Period Auto Creation | `timesheet_period_auto_creation_succeeded` | The command ran successfully. `new_values.result` is `created` when a period was created, or `skipped` when the period already existed. |
| Weekly Period Auto Creation | `timesheet_period_auto_creation_failed` | The command did not run because the automation was disabled. |
| Weekly Period Auto Creation | `timesheet_period_auto_created` | A new `timesheet_periods` record was created by the automation. |
| Weekly Period Auto Creation | `timesheet_period_auto_create_skipped` | The automation found an existing period for the target ISO week/year and did not create a duplicate. |
| Missing Timesheet Reminders | `timesheet_missing_reminders_succeeded` | The reminder command completed for an eligible period. `new_values.sent_count` contains the number of emails sent. |
| Missing Timesheet Reminders | `timesheet_missing_reminders_failed` | The command did not send reminders because the automation was disabled or no eligible open past period was found. |
| Missing Timesheet Reminders | `timesheet_missing_reminder_sent` | One reminder email was sent to one employee. This can appear multiple times in a successful reminder run. |
| Automation Controls | `automation_enabled` | A Super Admin enabled an automation from Manage Automations. |
| Automation Controls | `automation_disabled` | A Super Admin disabled an automation from Manage Automations. |

## Audit Logs

Super Admin users can review audit logs from **Manage Audit Logs**.

- Audit logs can be filtered by action, user, and date range.
- **Export Excel** downloads an `.xlsx` file using the current audit-log filters.
- Use row checkboxes and **Delete Selected** to manually remove selected logs from the current page.
- Use **Delete All Matching Filters** to remove every audit log matching the current filters. This action requires the explicit acknowledgement checkbox and confirmation modal before the request is submitted.
- Audit log deletion is restricted to Super Admin users.

## Main Workflow

1. Super Admin creates departments, users, projects, and weekly periods.
2. Employee selects an open weekly period and creates one timesheet for that period.
3. Employee enters daily rows against project/job numbers.
4. A day can contain multiple project rows, including overtime-only rows after normal working hours.
5. Leave attendance codes can carry regular hours without a project/job number, but cannot carry overtime.
6. Employee saves as draft or submits.
7. Submitted timesheets are locked for the employee.
8. Employee can recall a submitted timesheet before Head of Department action, returning it to draft for correction.
9. Head of Department approves or rejects department timesheets.
10. Rejected timesheets show the rejection comment and become editable by the employee.
11. Admin and Super Admin monitor all records and export native XLSX timesheet workbooks.

## Open Period Rules

Employees can create timesheets for any weekly period that is marked `open`.

- Past periods can remain open when late submissions are allowed.
- Current periods can remain open for normal weekly submission.
- Future periods can be opened early when advance submissions are allowed.
- Closed periods cannot accept new drafts, submissions, or resubmissions.
- Employees can still only have one timesheet per weekly period.

Super Admin controls availability from **Manage Weekly Periods**. To allow last week or next week, keep or set that period to `open`; to stop new changes, set the period to `closed`.

When creating or editing a weekly period, Super Admin selects the Monday start date. The form automatically fills the Sunday end date, ISO week number, and year, while backend validation still rejects periods that do not run from Monday through Sunday.

The weekly period auto creation command is:

```bash
php artisan timesheets:create-weekly-period
```

It runs every Monday at 06:30 when **Weekly Period Auto Creation** is enabled. It creates the current ISO week period as `open` if the week/year does not exist yet. If the period already exists, it skips creation and records the skip in `audit_logs`.

## Export

The export route is available to Admin and Super Admin:

```text
/admin/timesheets/export
```

It streams a native `.xlsx` workbook using Laravel Excel.

### Timesheet Export Filters

The Admin **All Timesheets** page and export route share the same filters:

- `From Week`
- `To Week (optional)`
- `Year`
- `Project`
- `Department`
- `Employee`
- `Status`
- `Include individual employee timesheet sheets`

Week filter behavior:

- If only `From Week` is filled, the export targets that one weekly period.
- If both `From Week` and `To Week` are filled, the export targets the inclusive week range.
- `To Week` cannot be earlier than `From Week`.
- `To Week` cannot be used without `From Week`.
- `Year` is required whenever week filtering is used.
- The selected week or week range must match at least one actual `timesheet_periods` record for that year.
- A range can include missing weeks as long as at least one weekly period exists inside the range.

Project filter behavior:

- If no project is selected, the export includes all project hours from the matching timesheets.
- If a project is selected, the index page only lists timesheets that have entries for that project.
- If a project is selected, the **Project Weekly Summary** worksheet only includes hours logged to that project.
- Individual employee timesheet worksheets are still included for matching timesheets.

### Timesheet Export Workbook Layout

The exported workbook contains:

1. **Project Weekly Summary**
2. **Attendance Code Summary**
3. Optional individual employee timesheet worksheets

The **Project Weekly Summary** worksheet is grouped by project. Each project group shows employees down the rows and weekly periods across the columns. Each week header is merged above its Regular, Overtime, and Total hour columns and is sized to show both the week number and date range. If an employee logged hours for the project in one exported week but not another, the missing week columns show `0.00`.

Each project group shows:

- Project code
- Full project name, wrapped so long names remain readable in Excel
- Client name, when available
- Employee ID
- Initials
- Employee name
- Job title
- Week number and week date range
- Regular hours per week
- Overtime hours per week
- Total hours per week
- Project total row

The bottom of the summary contains a grand total row. Grand totals are calculated for every exported week column group, so exports with Week 12 through Week 15 show separate Regular, Overtime, and Total grand totals for each week.

The **Attendance Code Summary** worksheet lists leave and other non-project hours from the exported timesheets. It includes week, employee, department, job title, attendance code, attendance label, project/job if present, regular hours, overtime hours, total hours, and status. This worksheet explains why payroll/manhour totals may be higher than project-chargeable totals when employees have annual leave, sick leave, emergency leave, unpaid leave, paid holiday leave, maternity leave, paternity leave, compassionate leave, or other non-project hours.

By default, the export includes the **Project Weekly Summary** and **Attendance Code Summary** worksheets. This keeps project reports faster to generate and still gives stakeholders the non-project hour context needed for reconciliation.

If `Include individual employee timesheet sheets` is selected, the workbook also includes one worksheet per exported employee timesheet. These detail worksheets mirror the weekly timesheet layout: employee details including job title, week number, attendance/project codes, weekday RT/OT columns, weekend columns, totals, and remarks.

### Export Validation Messages

The export/filter validation may return these messages:

| Message | Meaning |
| --- | --- |
| `Enter From Week when using To Week.` | `To Week` was filled but `From Week` was blank. |
| `To Week must be greater than or equal to From Week.` | The selected range is backwards. |
| `Year is required when filtering by week.` | A week filter was used without a year. |
| `Selected week period does not exist.` | The selected single week has no matching weekly period for that year. |
| `Selected week range does not contain any existing periods.` | None of the weeks in the selected range exist for that year. |

### Export UX

The Admin **Export Excel** button shows a small loading spinner and `Preparing export...` label after it is clicked. This gives feedback while Laravel builds the workbook and discourages duplicate clicks on larger exports.

For larger exports, leave `Include individual employee timesheet sheets` unchecked unless detailed timesheet backup sheets are needed. Summary-only exports avoid generating many extra worksheet tabs and reduce server work.

## Email Notifications

The system sends email notifications for core timesheet workflow actions:

- Employee submits a timesheet: Head of Department receives a review email.
- Employee resubmits a rejected timesheet: Head of Department receives a review email.
- Employee recalls a submitted timesheet: Head of Department receives a recall email.
- Head of Department approves a timesheet: employee receives an approval email.
- Head of Department rejects a timesheet: employee receives a rejection email with the comment.
- Missing weekly timesheet reminders: employees receive a reminder email when they do not have a submitted or approved timesheet for the selected period.

Head of Department users can send reminders from **Department Submission Tracker** for one missing employee or all missing employees in their department. A scheduled command also sends automatic reminders for the latest past open weekly period:

```bash
php artisan timesheets:send-missing-reminders
```

To target a specific open period manually:

```bash
php artisan timesheets:send-missing-reminders --period_id=1
```

Each sent reminder is recorded in `audit_logs` with the `timesheet_missing_reminder_sent` action.
Reminder sending is processed in small batches of 25 employees and the scheduled reminder uses Laravel's overlap protection, so a slow email run will not start a duplicate reminder process.

Manual HOD reminders use a per-employee, per-period cooldown to prevent repeated reminder emails. The default cooldown is 24 hours:

```env
MISSING_TIMESHEET_REMINDER_COOLDOWN_HOURS=24
```

When an employee is on cooldown, their **Send Reminder** button is disabled in **Submission Tracker** and shows how long remains before another reminder can be sent. **Notify All Missing** skips employees who are on cooldown and sends only to employees who are eligible. If every missing employee is on cooldown, MEC Portal shows a warning instead of sending reminders.

Local development defaults to logging emails instead of sending them:

```env
MAIL_MAILER=log
```

Workflow emails, missing timesheet reminder emails, and password reset emails are queueable. The same code works with different queue connections:

| Queue connection | When to use it | What is required |
| --- | --- | --- |
| `sync` | Local development or very simple hosting. Jobs run immediately during the request. | No worker required. Requests may wait while email is sent. |
| `database` | Production-safe default for small/internal deployments. | Run migrations so the `jobs` and `failed_jobs` tables exist, then run a queue worker. |
| `redis` | Better performance when Redis is available and reliable. | Configure Redis credentials and run a queue worker against Redis. |

Recommended shared-hosting setup:

```env
QUEUE_CONNECTION=database
```

Then run:

```bash
php artisan migrate
```

If cPanel does not support Supervisor or another long-running daemon, use a Cron Job that starts a short-lived worker every minute and exits when the queue is empty:

```cron
* * * * * cd /home/your-cpanel-user/path-to-project && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=60 >> /dev/null 2>&1
```

If Redis is available, configure:

```env
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379
```

Use the exact host, port, and password shown by cPanel. If cPanel shows a non-standard port such as `42035`, use that value for `REDIS_PORT`.

To test the Redis connection from cPanel Terminal or SSH, use `redis-cli` with the values from cPanel:

```bash
redis-cli -h 127.0.0.1 -p 6379 -a 'your_redis_password' ping
```

Expected response:

```text
PONG
```

For a custom cPanel port:

```bash
redis-cli -h 127.0.0.1 -p 42035 -a 'your_redis_password' ping
```

If the password contains special characters, keep it inside quotes. If `redis-cli` is not available in cPanel Terminal, ask the host whether Redis CLI access is enabled; Laravel can still use Redis if PHP can connect to it.

Then use this cPanel cron command instead:

```cron
* * * * * cd /home/your-cpanel-user/path-to-project && /usr/local/bin/php artisan queue:work redis --stop-when-empty --max-time=50 --tries=3 --backoff=60 >> /dev/null 2>&1
```

Recommended Redis cron parameter meanings:

- `* * * * *` runs the worker every minute so password reset and workflow emails are processed quickly.
- `cd /home/your-cpanel-user/path-to-project` moves into the Laravel project folder before running Artisan.
- `/usr/local/bin/php` is the PHP CLI binary. Replace it if cPanel shows a different PHP path.
- `artisan queue:work redis` processes jobs from the Redis queue connection.
- `--stop-when-empty` makes the worker exit after all currently available jobs are processed.
- `--max-time=50` keeps each cron-started worker under 50 seconds, reducing overlap before the next minute starts.
- `--tries=3` allows a failed job to be attempted up to three times.
- `--backoff=60` waits 60 seconds before retrying a failed job.
- `>> /dev/null 2>&1` discards normal output and errors so cPanel does not email output every minute.

If more jobs exist than can be processed in 50 seconds, the remaining jobs stay safely in Redis and the next cron run continues processing them.

If production can run a persistent worker through Supervisor, systemd, or a hosting daemon, use:

```bash
php artisan queue:work --tries=3 --backoff=60
```

For shared cPanel hosting, the `--stop-when-empty` cron approach is usually the safest option. Queueing is still useful on shared hosting because users do not have to wait for SMTP during form submissions, but emails will only be delivered when the cron worker runs successfully. If the host cannot run cron reliably, keep `QUEUE_CONNECTION=sync`.

Useful queue checks:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

For the `database` queue, pending jobs are visible in the `jobs` table. A healthy worker keeps this table close to empty. Failed jobs are stored in `failed_jobs`. For Redis, use the hosting Redis tools if available, and use `php artisan queue:failed` for recorded failures.

Useful Redis checks:

```bash
redis-cli -h 127.0.0.1 -p 6379 -a 'your_redis_password' ping
redis-cli -h 127.0.0.1 -p 6379 -a 'your_redis_password' llen queues:default
redis-cli -h 127.0.0.1 -p 6379 -a 'your_redis_password' keys '*queues*'
```

Command meanings:

- `ping` confirms Redis is reachable.
- `llen queues:default` shows how many jobs are waiting in the default queue.
- `keys '*queues*'` can help find queue keys, but avoid using broad `keys` commands on large production Redis databases.

Useful Laravel queue commands:

```bash
php artisan queue:work redis --stop-when-empty --max-time=50 --tries=3 --backoff=60
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all
php artisan queue:forget {failed_job_id}
php artisan queue:flush
```

Command meanings:

- `queue:work redis --stop-when-empty --max-time=50` processes Redis jobs, exits when no jobs remain, and caps each cron-started run at 50 seconds.
- `queue:restart` asks long-running workers to restart after the current job. Use this after deployments if a persistent worker is supported.
- `queue:failed` lists failed jobs recorded by Laravel.
- `queue:retry all` retries all failed jobs.
- `queue:forget {failed_job_id}` removes one failed job.
- `queue:flush` removes all failed job records.

Successful Redis queue jobs are removed from Redis automatically after the worker processes them.

### Testing Reminder Emails

To test missing timesheet reminders without sending real emails, temporarily use the log mailer:

```env
MAIL_MAILER=log
```

Clear cached configuration and run the reminder command for a specific period:

```bash
php artisan config:clear
php artisan timesheets:send-missing-reminders --period_id=1
```

Replace `1` with the target `timesheet_periods.id`. The email content will be written to:

```text
storage/logs/laravel.log
```

After confirming the email content is correct, switch back to SMTP in `.env`, clear config again, and rerun the command:

```bash
php artisan config:clear
php artisan timesheets:send-missing-reminders --period_id=1
```

Verify the result by checking the recipient inbox, the `audit_logs` table for `timesheet_missing_reminder_sent`, and `storage/logs/laravel.log` for SMTP errors if the email does not arrive.

### Testing Automation Locally

Use log mail locally so reminder emails are written to `storage/logs/laravel.log` instead of being sent:

```env
MAIL_MAILER=log
```

Then clear cached config:

```bash
php artisan config:clear
```

To test weekly period auto creation directly, run:

```bash
php artisan timesheets:create-weekly-period
```

To test a specific week without changing your computer date, pass any date inside that week:

```bash
php artisan timesheets:create-weekly-period --date=2026-05-25
```

Expected result:

- if the week does not exist, a new Monday-to-Sunday `open` period is created
- if the week already exists, no duplicate is created
- `audit_logs` contains `timesheet_period_auto_creation_succeeded`
- `audit_logs` also contains `timesheet_period_auto_created` or `timesheet_period_auto_create_skipped`
- **Weekly Period Auto Creation** `last_run_at` updates in **Manage Automations**

To test missing timesheet reminders directly, make sure the target weekly period is `open`, has an `end_date` before today, and has at least one active employee without a submitted or approved timesheet. Then run:

```bash
php artisan timesheets:send-missing-reminders --period_id=1
```

Expected result:

- reminder email content appears in `storage/logs/laravel.log`
- `audit_logs` contains `timesheet_missing_reminders_succeeded`
- `audit_logs` contains `timesheet_missing_reminder_sent`
- the automation's `last_run_at` updates in **Manage Automations**

To test the Super Admin emergency stop:

1. Log in as Super Admin.
2. Go to **Manage Automations**.
3. Disable **Weekly Period Auto Creation** or **Missing Timesheet Reminders**.
4. Run the related command again:

```bash
php artisan timesheets:send-missing-reminders --period_id=1
```

Expected output:

```text
Missing timesheet reminders automation is disabled.
```

No email should be logged or sent. For weekly period auto creation, the disabled output is:

```text
Weekly period auto creation automation is disabled.
```

To test the emergency CLI override, run:

```bash
php artisan timesheets:create-weekly-period --force
php artisan timesheets:send-missing-reminders --period_id=1 --force
```

To test Laravel's scheduler locally instead of calling the command directly, use one of these scheduler commands.

Run the scheduler once and stop:

```bash
php artisan schedule:run
```

This is what the cPanel cron job calls every minute in production. It checks whether any scheduled task is due at that exact moment, runs due tasks, then exits.

Keep the scheduler running locally:

```bash
php artisan schedule:work
```

Keep that terminal open. Laravel will check the schedule every minute and run due tasks, such as the Monday 06:30 weekly period creation and Monday 07:00 missing timesheet reminder when each automation is enabled. This is usually easier for local testing because you do not need to configure a local cron job.

For production SMTP email sending, configure these values in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=your.smtp.host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=timesheets@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
APP_URL=https://your-production-domain.com
```

Credentials needed from the email provider:

- SMTP host
- SMTP port
- SMTP username
- SMTP password or app password
- encryption type, usually `tls` for port `587` or `ssl` for port `465`
- verified sender email address
- sender name

The branded email template is located at:

```text
resources/views/emails/timesheet-workflow.blade.php
resources/views/emails/missing-timesheet-reminder.blade.php
```

### cPanel Scheduler

Automation works on cPanel by adding a Cron Job that runs Laravel's scheduler every minute. Use placeholders in documentation and replace them with the actual production project path and PHP binary on the server:

```cron
* * * * * cd /home/your-cpanel-user/path-to-project && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

If the hosting provider uses a different PHP path, update `/usr/local/bin/php` to the PHP CLI path shown in cPanel or provided by hosting support.

Laravel then runs the missing timesheet reminder command every Monday at 07:00 for the latest open period that has already ended.

## Testing

The project has automated tests for the Laravel backend flows and browser-level end-to-end checks.

### Unit Tests

Unit tests live in `tests/Unit`.

Use unit tests for small isolated logic that does not need a browser or full HTTP request, such as model helpers, value calculations, or service behavior.

Run all PHPUnit tests:

```bash
php artisan config:clear
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
- Head of Department approval and rejection rules
- admin and Super Admin approval/export access
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
- Delegated approval and temporary Head of Department coverage.
- Payroll integration and locked payroll periods.
- Rich reporting dashboards and Power BI dataset sync.
- Correction workflow for approved timesheets with audit approval.
