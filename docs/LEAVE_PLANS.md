# Leave Plans

Leave plans let employees plan leave separately from weekly timesheet entries. Approved leave plans appear as warnings on matching weekly timesheets, but they do not automatically create or change timesheet rows.

## Employee Workflow

Employees use **My Leave Plans** to create, save, submit, and track leave plans.

- Draft leave plans can be edited or deleted.
- Submitted leave plans are sent to assigned HOD approvers.
- Rejected leave plans can be edited and resubmitted.
- Approved leave plans are locked unless the employee requests cancellation.
- Cancellation requests require HOD, Admin, or Super Admin review.
- Recalled approved leave plans can be edited and resubmitted by the employee.
- Overlapping active leave plans show a warning but are not blocked.

Half-day leave is single-date only and must be marked as morning or afternoon.
Leave plan screens show both the inclusive calendar-day count and the counted leave day total. Counted leave days exclude Saturday, Sunday, and active applicable company holidays.

## HOD, Admin, And Super Admin Workflow

HODs use **Department Leave Plans** to review leave plans for departments they manage.

- HODs can approve or reject submitted leave plans in managed departments.
- HODs can approve or reject cancellation requests in managed departments.
- HODs can recall approved leave plans in managed departments so employees can correct and resubmit them.
- HODs cannot approve, reject, recall, or action cancellation for their own leave plans.

Admins and Super Admins use **All Leave Plans** to review leave plans across all departments.

- Admins and Super Admins can approve, reject, recall, and action cancellation globally.
- Super Admins can void approved leave plans with a required reason. Voided plans remain in audit history but no longer count as active planned leave.
- Self-approval is blocked for Admin and Super Admin users too.

Use cancellation when the employee requests to remove an approved leave plan. Use recall when an approved leave plan has incorrect details and should be corrected by the employee. Use void when a Super Admin needs to mark an approved leave plan inactive for audit-only history.

## Email Notifications

| Event | Recipient |
| --- | --- |
| Leave plan submitted or resubmitted | Active HOD approvers for the employee's department |
| Leave plan approved | Employee |
| Leave plan rejected | Employee |
| Cancellation requested | Active HOD approvers for the employee's department |
| Cancellation approved | Employee |
| Cancellation rejected | Employee |
| Approved leave recalled | Employee |

Email delivery uses Laravel queued mail. Inactive users, users without email addresses, and the leave plan owner when they are also an HOD approver are skipped.

## Calendar Visibility

| Role | Calendar | Visible leave plans |
| --- | --- | --- |
| Employee | My Leave Calendar | Own leave plans only |
| HOD | Department Leave Calendar | Leave plans in managed departments |
| Admin | All Leave Calendar | All leave plans |
| Super Admin | All Leave Calendar | All leave plans |

Calendars are read-only. Users create and edit leave plans through the existing leave plan forms.

By default, calendars show submitted, approved, and cancellation-requested leave plans. Filters are available for status, leave type, employee, and department where the role is allowed to use them. Recalled, cancelled, and voided leave plans can be viewed by filtering for those statuses.

## Current Limitations

- Leave balances and entitlements are not tracked.
- Approved leave does not automatically populate weekly timesheets.
- Overlap detection warns only; it does not block submission.
- Calendar entries link to existing detail or review pages instead of editing inline.
- Holiday calendars are maintained by Admins and Super Admins for global, UAE, and Philippines regions.
