# MEC Group Portal

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

- Super Admin: manage users, departments, projects/job numbers, weekly periods, view all timesheets, approve/reject submitted records, recall approved records when correction is needed, void approved records when replacement is needed, and export. Super Admin cannot approve, reject, recall, or void their own timesheet.
- Super Admin can also manage scheduled automation controls, view audit logs for important system and timesheet actions, export audit logs to Excel, and delete audit logs when database cleanup is needed.
- Employee numbers are manually entered by Super Admin and must follow `MEC-HR-YYYY-NNN`, `MCE-HR-YYYY-NNN`, or `MEC-PHIL-HR-YYYY-NNN`; the final number must be at least 3 digits and can grow beyond 999.
- Employee initials are manually entered by Super Admin, are optional, and are used in weekly timesheet exports.
- Job titles are optional user profile details and appear in timesheet exports. Blank job titles export as `-`.
- Admin: view all timesheets, filter records, monitor dashboard summaries, export, approve/reject Employee and Head of Department timesheets, and recall approved Employee and Head of Department timesheets. Admin cannot approve, reject, or recall their own timesheet.
- Admin: view and review all leave plans, use the all-company leave calendar, approve/reject leave plans and cancellation requests, and receive no self-approval ability.
- Head of Department: view employees and timesheets for every department they are assigned to manage, approve submitted employee timesheets, reject employee timesheets with a required comment, recall approved employee timesheets with a required reason, and track missing submissions. Head of Department cannot approve, reject, or recall their own timesheet.
- Head of Department: view leave plans and the leave calendar for managed departments, approve/reject submitted leave plans, and review cancellation requests. Head of Department cannot approve or reject their own leave plan.
- Employee: create weekly timesheets, save drafts, submit for approval, view history, withdraw submitted timesheets before approval, edit draft/rejected/withdrawn/recalled timesheets, and resubmit corrected records.
- Employee: create leave plans, save drafts, submit them for HOD approval, request cancellation of approved leave, and view their department leave calendar.
- Admin and Super Admin department assignment is optional for system management, but required if they need to create or submit their own weekly timesheets.

## Leave Plans

Leave plans are tracked separately from weekly timesheet entries.

- Employees create leave plans from **My Leave Plans** and can view submitted, approved, and cancellation-requested leave in their department calendar.
- HODs review leave plans from **Department Leave Plans** and can visualize managed department leave in **Department Leave Calendar**.
- Admins and Super Admins review all leave plans from **All Leave Plans** and can visualize all leave in **All Leave Calendar**.
- Leave calendars show active company holidays as read-only events. Employee calendars show global holidays plus holidays for the employee's region; reviewer calendars show all company holiday regions with region labels.
- Submitted, approved, rejected, cancellation-requested, and cancelled leave-plan actions are audit logged.
- Email notifications are sent for submission/resubmission, approval, rejection, cancellation request, cancellation approval, and cancellation rejection.
- Approved leave plans appear as warnings on overlapping weekly timesheet forms, but timesheet rows are never auto-created or changed.
- `L100 - Annual Leave`, `L110 - Sick Leave`, `L160 - Maternity Leave`, `L170 - Parental Leave`, `L180 - Bereavement / Compassionate Leave`, and Philippines-only `L190 - Service Incentive Leave` have entitlement limits. Super Admin sets UAE and Philippines defaults in **Leave Settings**, including UAE sick and maternity maximum claimable calendar-day limits, and can set per-user annual overrides in **Manage Users**.
- Eligible entitlement balances are shown on dashboards, leave forms, and user profiles. Maternity leave is available only when the employee gender is set to Female. Parental leave is available only when marital status is set to Married. Service incentive leave is available only to Philippines employees. A separate paternal leave code has not been added; the marital-status rule applies to the existing `L170 - Parental Leave` code.
- Leave entitlements are calendar-year based. Unused days expire on December 31, do not carry over, and require no automation to refresh because usage is calculated dynamically by leave-plan year.
- Submitted, approved, and cancellation-requested entitled leave plans consume allowance. Draft, rejected, cancelled, recalled, and voided plans do not consume allowance.
- Active applicable holidays are part of dynamic usage calculations for every leave type, so adding, updating, activating, or deactivating a holiday after submission or approval can change counted leave days and remaining balance. UAE sick and maternity leave count calendar days, including weekends, but still exclude applicable holidays.
- Cross-year entitled leave plans are split by counted leave date, so December days count against the old year and January days count against the new year.
- UAE sick leave employee-facing balances show the 15 full-pay calendar days, while validation allows up to the 90-day maximum and admin review can see the payroll split of 15 full-pay, 30 half-pay, and 45 unpaid calendar days. UAE maternity employee-facing balances show the 45 full-pay calendar days, while validation allows up to the 60-day maximum and admin review can see the 45 full-pay and 15 half-pay split.
- `L190 - Service Incentive Leave` defaults to 5 days for Philippines employees and is hidden/blocked for UAE employees.

See `docs/LEAVE_PLANS.md` for the full workflow, email matrix, calendar visibility rules, and current limitations.

## User Management

Super Admin users can create, edit, activate/deactivate, and delete users from **Manage Users**.

- Employee numbers are entered manually when creating or editing employees and HODs.
- Initials can be entered manually when creating or editing users. If initials are blank, exports fall back to initials derived from the employee name.
- Job Title can be entered manually when creating or editing users. It is optional, limited to 100 characters, and appears as `-` in exports when blank.
- User passwords must be 10 to 64 characters. Letters, numbers, symbols, and spaces are allowed.
- Users without a department cannot create or submit their own timesheets. Assign a department first if Admin, Super Admin, Head of Department, or Employee accounts need to submit personal weekly time.
- When a user's department is changed, their existing draft, withdrawn, recalled, and rejected timesheets move to the new department. Submitted and approved timesheets remain with the original department for review and historical reporting.
- Super Admin cannot delete their own account.
- Deleting a user permanently removes the user and the timesheets owned by that user.
- Timesheet entries are also deleted because they belong to the deleted user's timesheets.
- Approval history is preserved where possible: if a deleted user previously approved or rejected another user's timesheet, the approver/rejector reference is set to blank.
- If the deleted user is assigned as a primary or additional Head of Department for any department, Super Admin must select a replacement Head of Department before deletion.
- The replacement Head of Department must be an active HOD user. The replacement is attached to every department previously managed by the deleted HOD so approvals continue to work.
- If no replacement Head of Department exists, create or update another active Head of Department before deleting the current one.

## Password Reset

Employees and admins can reset forgotten passwords from the **Forgot password?** link on the sign-in page.

- Only active existing user accounts can request a password reset email.
- Reset links are emailed to the user's account address.
- Password reset links expire after 1 hour.
- The reset form requires the new password and confirmation to match.
- New passwords must be 10 to 64 characters. Letters, numbers, symbols, and spaces are allowed.
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

### Multiple Head Of Department Approvers

Departments support one primary Head of Department and any number of additional HOD approvers.

- **Head of Department** is the primary HOD stored on the department record. This keeps older workflows and reports compatible.
- **HOD approvers** is the full list of HOD users who can manage the department. The primary HOD is included automatically in this list.
- A HOD can manage more than one department, even if their own user profile belongs to only one department.
- A HOD's managed departments are resolved from three sources: departments where they are selected as primary HOD, departments where they are selected as an additional HOD approver, and their own profile department as a legacy fallback.
- The HOD dashboard, Department Timesheets, Submission Tracker, missing-timesheet reminders, and submission/recall notification emails use the full managed-department list.
- HOD timesheet and tracker pages include a department filter when the HOD manages multiple departments. The filter only lists departments that HOD is allowed to manage.
- HODs can approve, reject, or recall approved employee timesheets only inside their managed departments.
- HODs still cannot approve, reject, or recall their own timesheet or another HOD's timesheet.
- Employee submission and resubmission emails are sent to every active HOD approver assigned to the timesheet department, including the primary HOD.
- When deleting a HOD assigned to one or more departments, Super Admin must select an active replacement HOD. The replacement is assigned to all departments previously managed by the deleted user.
- If a HOD user's role is changed back to Employee, Admin, or Super Admin, their primary HOD assignments and additional HOD approver assignments are cleared automatically.

### HOD Notification And Approval Exclusions

Super Admin users can edit an existing HOD user and configure exceptions for specific users in departments where that HOD is explicitly assigned as primary HOD or additional HOD approver.

- **Notification exclusion** stops email notifications for the selected HOD/user pair only. The HOD can still view, approve, reject, recall, and review cancellation requests where their normal HOD role allows it.
- **Approval exclusion** stops approval-request emails and prevents that HOD from approving, rejecting, recalling, approving cancellation, or rejecting cancellation for the selected user.
- Approval-excluded HODs can still view records in managed department pages, so department visibility and reporting remain intact.
- A HOD's own profile department does not qualify for exclusions unless the HOD is also assigned as a primary/additional HOD approver for that department.
- The system prevents approval exclusions that would leave a submitter with no eligible HOD approver.
- Invalid exclusions are cleaned up when users change role, users move department, users are deleted, or department HOD assignments change.

See `docs/HOD_EXCLUSIONS.md` for the full behavior, safety rules, audit action, and test coverage.

The database change for this feature is additive:

- `departments.hod_id` remains the primary HOD column.
- `department_hod` stores the many-to-many list of department HOD approvers.
- The migration backfills existing `departments.hod_id` values into `department_hod`.
- `hod_notification_exclusions` and `hod_approval_exclusions` store optional HOD/user exceptions.
- No existing table should be deleted for this feature.

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

Timesheet workflow history is stored separately in `timesheet_status_histories` so the history shown under a timesheet remains available even if general audit logs are later cleaned up. The migration backfills existing timesheet-related audit rows into this table, and new workflow actions write to both the dedicated history table and the general audit log.

## Main Workflow

1. Super Admin creates departments, users, projects, and weekly periods.
2. Employee selects an open weekly period and creates one timesheet for that period.
3. Employee enters daily rows against project/job numbers.
4. A day can contain multiple project rows, including overtime-only rows after normal working hours.
5. Leave attendance codes can carry regular hours without a project/job number, but cannot carry overtime.
6. `L200` Training Seminar can be entered without a project/job number and can include regular or overtime hours.
7. Employee saves as draft or submits.
8. Submitted timesheets are locked for the employee.
9. Employee can withdraw a submitted timesheet before approval, marking it withdrawn for correction without sending an email to themselves.
10. Head of Department approves or rejects employee timesheets for departments they manage; Admin and Super Admin can also approve or reject Employee and Head of Department timesheets.
11. Rejected timesheets show the rejection comment and become editable by the employee.
12. Approved timesheets can be recalled by an authorized reviewer with a required reason; the owner receives an email and can correct and resubmit the same record.
13. Admin and Super Admin monitor all records and export native XLSX timesheet workbooks.

## Open Period Rules

Employees can create timesheets for any weekly period that is marked `open`.

- Past periods can remain open when late submissions are allowed.
- Current periods can remain open for normal weekly submission.
- Future periods can be opened early when advance submissions are allowed.
- Closed periods cannot accept new drafts, submissions, or resubmissions unless an approved timesheet has been recalled for correction.
- Employees can still only have one active timesheet per weekly period. Voided records are excluded so a replacement can be created.

Super Admin controls availability from **Manage Weekly Periods**. To allow last week or next week, keep or set that period to `open`; to stop new changes, set the period to `closed`.

When creating or editing a weekly period, Super Admin selects the Monday start date. The form automatically fills the Sunday end date, ISO week number, and year, while backend validation still rejects periods that do not run from Monday through Sunday.

## Date Picker UX

Date fields use Flatpickr through the global layout initializer. Blade templates keep normal `type="date"` inputs for graceful fallback, then JavaScript upgrades them to themed inputs that still submit `YYYY-MM-DD` values under the original Laravel field names.

- The picker must support direct month and year selection.
- The popup must remain readable in both light and dark themes.
- Keep the global helper functions `syncDatePicker`, `setDatePickerMin`, and `setDatePickerReadonly` available because leave-plan and weekly-period forms use them for dependent date rules.

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

When more than one weekly period is exported, the Project Weekly Summary also adds a highlighted **Selected Period Total** column group after the week columns. This group shows Regular, Overtime, and Total hours across the full exported range for each employee row, each project total row, and the grand total row. Single-week exports keep the simpler one-week layout and do not show this extra range-total group.

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
- Export range Regular, Overtime, and Total hours when multiple weekly periods are exported
- Project total row

The bottom of the summary contains a grand total row. Grand totals are calculated for every exported week column group, so exports with Week 12 through Week 15 show separate Regular, Overtime, and Total grand totals for each week. Multi-week exports also show the highlighted Selected Period Total at the far right, giving the final Regular, Overtime, and Total hours for the whole selected range.

The **Attendance Code Summary** worksheet lists leave and other non-project hours from the exported timesheets. It includes week, employee, department, job title, attendance code, attendance label, project/job if present, regular hours, overtime hours, total hours, and status. This worksheet explains why payroll/manhour totals may be higher than project-chargeable totals when employees have annual leave, sick leave, emergency leave, unpaid leave, paid holiday leave, maternity leave, parental leave, bereavement / compassionate leave, service incentive leave, or other non-project hours.

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

The Admin **Export Excel** button shows a short `Starting export...` state after it is clicked, then returns to normal while the browser handles the file download. A toast also appears with `Export started. Your Excel file will download when ready.` This avoids implying that the page can track the browser's full download lifecycle.

If Laravel redirects back with a warning, success message, or validation errors, the layout keeps the normal page alert and also shows a toast. Export issues such as duplicate export attempts therefore appear as both a durable alert and a temporary notification.

For larger exports, leave `Include individual employee timesheet sheets` unchecked unless detailed timesheet backup sheets are needed. Summary-only exports avoid generating many extra worksheet tabs and reduce server work.

## Email Notifications

The system sends email notifications for core timesheet workflow actions:

- Employee submits a timesheet: every Head of Department approver for that department receives a review email.
- Employee resubmits a rejected timesheet: every Head of Department approver for that department receives a review email.
- Employee withdraws a submitted timesheet: no email is sent, but the action is stored in timesheet history.
- Head of Department approves a timesheet: employee receives an approval email.
- Head of Department rejects a timesheet: employee receives a rejection email with the comment.
- Authorized reviewer recalls an approved timesheet: employee receives a recall email with the reason.
- Missing weekly timesheet reminders: employees receive a reminder email when they do not have a submitted or approved timesheet for the selected period.

Head of Department users can send reminders from **Department Submission Tracker** for one missing employee or all missing employees in their managed departments. A scheduled command also sends automatic reminders for the latest past open weekly period:

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

When an employee is on cooldown, their **Send Reminder** button is disabled in **Submission Tracker** and shows how long remains before another reminder can be sent. **Notify All Missing** skips employees who are on cooldown and sends only to employees who are eligible. If every missing employee is on cooldown, MEC Group Portal shows a warning instead of sending reminders.

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

Run the full PHPUnit regression suite in parallel:

```bash
php artisan config:clear
composer test
```

`composer test` runs `php artisan test --parallel --processes=4`. Use the serial fallback only when debugging order-sensitive failures:

```bash
composer test:serial
```

Run the leave-plan regression subset:

```bash
composer test:leave
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
- employee timesheet creation, draft, submission, withdrawal, rejection, approved recall, and resubmission
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

The repository also includes `.env.testing` with the same safe defaults for developers and tools that explicitly load Laravel's testing environment. If a test run reports an unsafe database configuration, clear cached config before rerunning:

```bash
php artisan config:clear
php artisan test
```

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

For safer browser testing, use a separate E2E database instead of your normal local database:

1. Create a local database named `timesheet_program_e2e`.
2. Copy `.env.e2e.example` to `.env.e2e`.
3. Update `.env.e2e` with your local database username/password and, if needed, the full `PHP_BINARY` path.
4. Prepare that database:

```bash
npm run test:e2e:prepare
```

On Windows PowerShell:

```powershell
Copy-Item .env.e2e.example .env.e2e
npm.cmd run test:e2e:prepare
```

Run the browser tests:

```bash
npm run test:e2e
```

Reset the E2E database and run the browser tests in one command:

```bash
npm run test:e2e:fresh
```

On Windows PowerShell:

```powershell
npm.cmd run test:e2e:fresh
```

Playwright automatically loads `.env.e2e` when it exists. Shell environment variables still take priority, so you can override a setting for one run without editing the file.

E2E defaults to one worker because the specs use shared seeded accounts and mutate a shared local database. To experiment with parallel runs, set `E2E_WORKERS`, but be aware Laravel login throttling may apply when many tests use the same account.

`.env.e2e` also sets `E2E_DISABLE_LOGIN_THROTTLE=true`, which disables only the login rate limiter when Laravel is running with `--env=e2e`. This avoids false failures from repeated automated logins and does not affect local or production environments.

In `.env.e2e`, set `PHP_BINARY` if PHP is installed but not available to npm as `php`:

```env
PHP_BINARY='C:\xampp\php\php.exe'
```

Then run:

```bash
npm run test:e2e
```

For a one-off override on Windows PowerShell:

```powershell
$env:PHP_BINARY="C:\xampp\php\php.exe"
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
- Approved timesheet correction is implemented through required-reason recall, employee resubmission, dedicated timesheet history, audit logging, and Super Admin-only IP visibility.
- Notifications, payroll, leave management, Teams integration, Power BI, mobile app, and advanced analytics are outside Phase 1.
- User deletion is permanent in Phase 1; deactivate a user instead if historical ownership should be retained.
- Department and project deletion is restricted to unused records; use deactivate/archive for anything with history.

## Suggested Phase 2 Improvements

- Laravel Excel XLSX exports and scheduled export delivery.
- Email or Teams approval notifications.
- Delegated approval and temporary Head of Department coverage.
- Payroll integration and locked payroll periods.
- Rich reporting dashboards and Power BI dataset sync.
- Optional second-level approval for approved-timesheet corrections.
