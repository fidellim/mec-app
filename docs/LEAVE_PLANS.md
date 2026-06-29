# Leave Plans

Leave plans let employees plan leave separately from weekly timesheet entries. Approved leave plans appear as warnings on matching weekly timesheets, but they do not automatically create or change timesheet rows.

## Employee Workflow

Employees use **My Leave Plans** to create, save, submit, and track leave plans.

- Draft leave plans can be edited or deleted.
- Submitted leave plans start a staged approval chain: assigned HOD approver, Director of Engineering & Project Management, then regional HR.
- Rejected leave plans can be edited and resubmitted.
- Approved leave plans are locked unless the employee requests cancellation.
- Cancellation requests require HOD, Admin, or Super Admin review only; they do not repeat the Director/HR chain.
- Recalled approved leave plans can be edited and resubmitted by the employee.
- Overlapping active leave plans show a warning but are not blocked.
- `L100 - Annual Leave` is checked against the employee's yearly annual leave allowance when the employee submits. Drafts can still be saved when they exceed the allowance.

Half-day leave is single-date only and must be marked as morning or afternoon.
Leave plan screens show counted leave days only. Counted leave days exclude Saturday, Sunday, and active applicable company holidays.

## Staged Approval Workflow

HODs use **Department Leave Plans** to review leave plans for departments they manage.

- HODs can approve or reject submitted leave plans in managed departments while the plan is pending Head of Department approval.
- HOD approval moves the leave plan to Director review; it does not make the plan fully approved.
- HODs can approve or reject cancellation requests in managed departments.
- HODs can recall approved leave plans in managed departments so employees can correct and resubmit them.
- HODs cannot approve, reject, recall, or action cancellation for their own leave plans.

The configured **Director of Engineering & Project Management** approver uses **Assigned Leave Plans** to review leave plans after HOD approval.

- Director approval moves the leave plan to regional HR review.
- Director rejection returns the leave plan to the employee with the rejection comment.

The configured regional HR approver uses **Assigned Leave Plans** to complete final review.

- UAE HR reviews employees whose employee number starts with `MEC-HR-` or `MCE-HR-`.
- Philippines HR reviews employees whose employee number starts with `MEC-PHIL-HR-`.
- HR approval marks the leave plan approved.
- HR rejection returns the leave plan to the employee with the rejection comment.

Admins and Super Admins use **All Leave Plans** to review leave plans across all departments.

- Admins and Super Admins can review leave plans globally and action the current approval stage when the required Director/HR approver is configured.
- Super Admins can void approved leave plans with a required reason. Voided plans remain in audit history but no longer count as active planned leave.
- Self-approval is blocked for Admin and Super Admin users too.

Use cancellation when the employee requests to remove an approved leave plan. Use recall when an approved leave plan has incorrect details and should be corrected by the employee. Use void when a Super Admin needs to mark an approved leave plan inactive for audit-only history.

If the Director, UAE HR, or Philippines HR approver is not configured, the leave plan remains submitted at that approval stage. The review page shows a setup warning until a Super Admin assigns the missing approver in **Leave Approvers**.

## Annual Leave Entitlement

`L100 - Annual Leave` has a calendar-year entitlement limit.

- Super Admins set the global default yearly allowance in **Leave Settings**.
- Super Admins can set an employee-specific annual leave override in **Manage Users**. A blank override uses the global default.
- Annual leave refreshes automatically by year. Unused allowance expires on December 31 and does not carry over into the next year.
- No scheduled automation is required for the yearly refresh because remaining allowance is calculated dynamically from leave plans in the requested year.
- Submitted, approved, and cancellation-requested `L100` plans consume allowance.
- Draft, rejected, cancelled, recalled, and voided `L100` plans do not consume allowance.
- Cross-year annual leave is split by counted leave date. For example, counted December dates consume the old year's allowance and counted January dates consume the new year's allowance.
- Half-day `L100` leave consumes `0.5` counted leave day when the date is not a weekend or applicable company holiday.

## Email Notifications

| Event | Recipient |
| --- | --- |
| Leave plan submitted or resubmitted | Active HOD approvers for the employee's department |
| HOD approval completed | Configured Director approver |
| Director approval completed | Configured regional HR approver |
| HR approval completed | Employee |
| Leave plan rejected | Employee |
| Cancellation requested | Active HOD approvers for the employee's department |
| Cancellation approved | Employee |
| Cancellation rejected | Employee |
| Approved leave recalled | Employee |

Email delivery uses Laravel queued mail. Inactive users, users without email addresses, and the leave plan owner when they are also an HOD approver are skipped.

## Calendar Visibility

| Role | Calendar | Visible leave plans |
| --- | --- | --- |
| Employee | Department Leave Calendar | Submitted, approved, and cancellation-requested leave plans in their own department |
| HOD | Department Leave Calendar | Leave plans in managed departments |
| Admin | All Leave Calendar | All leave plans |
| Super Admin | All Leave Calendar | All leave plans |

Calendars are read-only. Users create and edit leave plans through the existing leave plan forms. Leave events appear only on counted leave dates, so weekends and applicable holidays inside the submitted date range are not shown as leave events.

By default, calendars show submitted, approved, and cancellation-requested leave plans. Filters are available for status, leave type, employee, and department where the role is allowed to use them. Employee calendars intentionally ignore inactive status filters and do not link coworker entries. Recalled, cancelled, and voided leave plans can be viewed by filtering for those statuses on reviewer calendars.

## Current Limitations

- Only `L100 - Annual Leave` entitlement is tracked. Other leave codes do not have balance limits.
- Approved leave does not automatically populate weekly timesheets.
- Overlap detection warns only; it does not block submission.
- Calendar entries link to existing detail or review pages instead of editing inline.
- Holiday calendars are maintained by Admins and Super Admins for global, UAE, and Philippines regions.
