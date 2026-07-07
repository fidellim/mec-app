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
- UAE annual, sick, maternity, parental, and bereavement / compassionate leave are checked against the employee's allowance when the employee submits. UAE bereavement / compassionate leave also requires HR eligibility approval for the selected spouse or immediate-family relationship, then checks the matching calendar-year balance.
- Philippines employees do not see or apply for `L100 - Annual Leave`, `L110 - Sick Leave`, `L120 - Emergency Leave`, or `L180 - Bereavement Leave`.
- Philippines statutory leave options are shown only when the employee is eligible: `L190 - Service Incentive Leave`, `L160 - Maternity Leave`, `L170 - Parental Leave`, `L210 - Paternity Leave`, `L220 - Leave for VAWC`, and `L230 - Special Leave for Women`.
- Drafts can still be saved when they exceed an allowance, but submission enforces eligibility and balance checks.

Half-day leave is single-date only and must be marked as morning or afternoon.
Leave plan screens show counted leave days. Most leave types exclude Saturday, Sunday, and active applicable company holidays. UAE sick and maternity leave count calendar days, including weekends, but still exclude active applicable company holidays.

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

## Leave Entitlements

Eligible leave entitlements have calendar-year limits. UAE annual, sick, maternity, and parental leave use the employee's regional or profile entitlement. UAE bereavement / compassionate leave uses HR-attested spouse and immediate-family eligibility with separate calendar-year balances. Philippines employees only receive balances for eligible statutory leave options and do not receive Annual, Sick, Emergency, or Bereavement balances.

- Admins and Super Admins set UAE default allowances and active Philippines statutory leave allowances in **Leave Settings**. UAE sick and maternity settings are maximum claimable calendar-day limits, not employee-facing full-pay balances. UAE bereavement settings define yearly spouse-death and immediate-family-death allowances.
- Super Admins can set an employee-specific annual leave override in **Manage Users** for employees who are eligible for annual leave. A blank override uses the regional default.
- Leave allowances refresh automatically by year. Unused allowance expires on December 31 and does not carry over into the next year.
- No scheduled automation is required for the yearly refresh because remaining allowance is calculated dynamically from leave plans in the requested year.
- Submitted, approved, and cancellation-requested entitled leave plans consume allowance.
- Draft, rejected, cancelled, recalled, and voided plans do not consume allowance.
- Leave usage is recalculated dynamically from active applicable holidays for every leave type. If Admin or Super Admin adds, updates, activates, or deactivates a holiday after a leave plan is submitted or approved, those plans' counted leave days and remaining balance may change.
- UAE sick and maternity leave count calendar days, excluding applicable holidays. Employee and HOD balance cards show only the full-pay allowance: 15 sick days and 45 maternity days. Validation still allows the full claimable limits: 90 sick days and 60 maternity days. Admin review can see the payroll split.
- UAE bereavement leave requires HR eligibility approval and a relationship selection on `L180`. The selected relationship must match the approved spouse or immediate-family eligibility and controls which calendar-year balance is consumed; older records without a relationship are treated as manual-review records and are not assigned to either relationship bucket.
- Philippines service incentive leave requires at least one year of service and defaults to 5 days.
- Philippines maternity leave requires Female gender and HR eligibility approval. Qualified solo parents receive 120 days instead of the standard 105 days.
- Philippines parental leave requires HR eligibility approval and at least six months of service.
- Philippines paternity leave requires Philippines region, Male gender, Married status, and HR eligibility approval.
- Philippines leave for VAWC requires Philippines region, Female gender, and HR eligibility approval.
- Philippines special leave for women requires Philippines region, Female gender, HR eligibility approval, and at least six months of service.
- Cross-year entitled leave is split by counted leave date. For example, counted December dates consume the old year's allowance and counted January dates consume the new year's allowance.
- Half-day entitled leave consumes `0.5` counted leave day when the date is not a weekend or applicable company holiday. For UAE sick and maternity leave, a half day consumes `0.5` calendar day.

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

Calendars also show active company holidays as read-only events. Employee calendars show global holidays plus the employee's applicable region. HOD, Admin, and Super Admin calendars show global, UAE, and Philippines holidays with region labels so reviewers can understand why leave day counts may differ from calendar date ranges.

By default, calendars show submitted, approved, and cancellation-requested leave plans. Filters are available for status, leave type, employee, and department where the role is allowed to use them. Employee calendars intentionally ignore inactive status filters and do not link coworker entries. Recalled, cancelled, and voided leave plans can be viewed by filtering for those statuses on reviewer calendars.

## Current Limitations

- Leave options and balance cards are region-specific. UAE employees can see UAE Annual, Sick, Maternity, Parental, and Bereavement behavior. Philippines employees see eligible statutory leave options only and do not see Annual, Sick, Emergency, or Bereavement leave.
- Existing historical leave plans remain readable even if the employee is no longer eligible to create a new request for that leave code.
- Approved leave does not automatically populate weekly timesheets.
- Overlap detection warns only; it does not block submission.
- Calendar entries link to existing detail or review pages instead of editing inline.
- Holiday calendars are maintained by Admins and Super Admins for global, UAE, and Philippines regions.
