# Super Admin Onboarding Guide

Welcome to **MEC Group Portal**. This guide explains how Super Admins manage the portal setup, monitor records, export timesheets, control automations, and review audit logs.

## Signing In

1. Open the MEC Group Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

If you forget your password, select **Forgot password?** on the sign-in page. Password reset emails are only sent to active existing user accounts, and each reset link expires after 1 hour.

For security, public authentication forms use temporary rate limits:

- Login attempts are limited per email address and IP address.
- Forgot password requests are limited per email address and IP address.
- Password reset submissions are limited per IP address.

If a user reaches a limit, they are redirected back to the form with a message telling them how long to wait before trying again. This does not mean the account is inactive or locked; it is a temporary cooldown.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

## Main Menu

Super Admins can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows system-wide totals and submission summary. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history, if you are assigned to a department. |
| ![All Timesheets](/images/sidebar/all-timesheets.svg) **All Timesheets** | Lets you filter, review, approve where allowed, and export timesheets. |
| ![All Timesheets](/images/sidebar/all-timesheets.svg) **All Leave Plans** | Lets you review leave plans and open the all-company leave calendar. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Leave Entitlements** | Shows company leave balances by employee, department, and year. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Assigned Leave Plans** | Appears when you are configured as the Director, UAE HR, or Philippines HR leave approver. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **HOD Timesheets** | Shows Head of Department timesheets that Admins and Super Admins can review. |
| ![Submission Tracker](/images/sidebar/submission-tracker.svg) **HOD Tracker** | Shows which Heads of Department have submitted for a selected weekly period. |
| ![Users](/images/sidebar/users.svg) **Users** | Create, edit, activate/deactivate, and delete users. |
| ![Departments](/images/sidebar/departments.svg) **Departments** | Maintain departments and HOD assignments. |
| ![Projects](/images/sidebar/projects.svg) **Projects** | Maintain project/job numbers used in timesheets. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Weekly Periods** | Open or close weekly submission windows. |
| ![Users](/images/sidebar/users.svg) **Leave Approvers** | Assign Director and regional HR approvers for leave-plan approvals. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Leave Settings** | Set UAE and Philippines leave policy allowances. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Holidays** | Maintain global, UAE, and Philippines company holidays. |
| ![Automations](/images/sidebar/automations.svg) **Automations** | Enable or disable scheduled background jobs. |
| ![Audit Logs](/images/sidebar/audit-logs.svg) **Audit Logs** | Review, export, and clean up important system actions. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this onboarding guide whenever you need a refresher. |

## Timesheet Statuses

| Status | Meaning |
| --- | --- |
| ![Draft](/images/status/draft.svg) | Saved by the owner but not submitted. |
| ![Submitted](/images/status/submitted.svg) | Sent for review. |
| ![Approved](/images/status/approved.svg) | Accepted by an authorized reviewer. |
| ![Rejected](/images/status/rejected.svg) | Returned to the owner with a comment. |
| ![Withdrawn](/images/status/withdrawn.svg) | The owner withdrew a submitted timesheet before approval. |
| ![Recalled](/images/status/recalled.svg) | An approved timesheet was sent back for correction with a required reason. |
| ![Voided](/images/status/voided.svg) | Cancelled by a Super Admin because an approved timesheet needs correction. Voided records are kept for audit history and are excluded from corrected submissions and normal exports. |

## Dashboard

The **Super Admin Dashboard** shows:

- Total users.
- Active departments.
- Active projects.
- Current/open period summary.
- Submitted, approved, and rejected counts.

## Manage Users

Use **Users** to create and maintain accounts.

For each user, manage:

- Name.
- Email.
- Employee number.
- Initials.
- Job Title.
- Gender.
- Joining Date.
- Marital Status.
- Role.
- Department.
- Active/inactive status.
- Whether an Admin or Super Admin receives HOD timesheet submission emails.
- HOD notification, approval, and visibility exceptions when editing an existing HOD.
- Password when creating or resetting a user.

Passwords must be 10 to 64 characters. Letters, numbers, symbols, and spaces are allowed.

Employee numbers are required for Employees and Heads of Department. The accepted format is:

```text
MEC-HR-YYYY-NNN
MCE-HR-YYYY-NNN
MEC-PHIL-HR-YYYY-NNN
```

The final number must have at least three digits.

Job Title is optional and appears in timesheet exports. If it is left blank, exports show `-`.

Gender, joining date, and marital status are optional profile details. Gender controls maternity-leave eligibility, and marital status controls parental-leave eligibility.

Important user rules:

- Inactive users cannot use the system normally.
- Super Admins can turn off **Receive HOD timesheet submission emails** on Admin and Super Admin accounts if that user should not receive HOD approval-request emails.
- When editing an existing HOD, Super Admins can exclude selected users from that HOD's approval-request emails, prevent that HOD from approving/rejecting selected users, or hide selected users from that HOD's managed-department views when another HOD approver remains responsible.
- Users without a department cannot create or submit personal timesheets.
- When a user's department changes, draft, withdrawn, recalled, and rejected timesheets move to the new department. Submitted and approved timesheets stay in the original department.
- You cannot delete your own account.
- Deleting a user permanently deletes that user's timesheets and entries.
- If a user is assigned as a primary or additional department HOD, a replacement active HOD must be selected before deletion.
- Deactivate users when history should remain easier to understand.
- Use **Annual leave allowance override** only when a user should have a different yearly `L100 - Annual Leave` allowance from the regional default. Blank means the user follows **Leave Settings**.

## Manage Departments

Use **Departments** to maintain department names, codes, active status, and Head of Department assignments.

Important department rules:

- Active departments are available for new user assignment.
- Deactivated departments remain on existing users and historical records.
- Departments with users, timesheets, or an assigned HOD cannot be deleted.
- Deactivate departments instead of deleting them when they have history.

### Multiple HOD Approvers

Each department can have one primary **Head of Department** and multiple **HOD approvers**.

- The primary **Head of Department** is the main HOD shown for the department.
- **HOD approvers** are all HOD users who can manage that department.
- The selected primary HOD is automatically included as an approver.
- A HOD can manage more than one department.
- A HOD can approve, reject, or recall approved employee timesheets only for departments they manage.
- A HOD cannot approve, reject, or recall their own timesheet or another HOD's timesheet.
- Submission and resubmission emails go to every HOD approver assigned to that employee's department.
- The HOD dashboard, Department Timesheets page, Submission Tracker, and reminder tools use the full list of departments assigned to the HOD.
- If a HOD user's role is changed back to Employee, Admin, or Super Admin, MEC Group Portal automatically removes that user from primary HOD and additional HOD approver assignments.

### HOD Notification, Approval, And Visibility Exceptions

When a department has multiple HOD approvers, Super Admins can manage exceptions from the HOD's user edit page. Exceptions are available only for users in departments where the edited HOD is assigned as the primary HOD or an additional HOD approver.

- **Do not email this HOD for submissions from** stops email notifications only. The HOD can still approve or reject those users.
- **Do not allow this HOD to approve/reject submissions from** blocks approval, rejection, recall, and cancellation-review actions for those users. It also stops approval-request emails.
- **Do not show this employee to this HOD** removes those users from the HOD's managed-department timesheet, tracker, leave-plan, and calendar pages. It also prevents direct HOD action on those records.
- Approval-excluded HODs can still view records in managed department pages unless a visibility exception is also configured.
- The HOD's own profile department does not qualify unless the HOD is also selected as a primary/additional HOD approver for that department.
- MEC Group Portal prevents approval exceptions that would leave a user without any eligible HOD approver.
- MEC Group Portal also prevents visibility exceptions that would leave a user without any eligible HOD approver who can see and approve their records.
- Exceptions are cleaned up automatically when users change role, users move department, or department HOD assignments change.
- Changes are recorded in Audit Logs as `user_hod_exclusions_updated`.

See `docs/HOD_EXCLUSIONS.md` for the full reference.

When deleting a HOD who manages departments, select an active replacement HOD. The replacement will be assigned to every department the deleted HOD managed, including primary HOD assignments and additional approver assignments.

## Manage Projects / Job Numbers

Use **Projects** to maintain project/job numbers available in timesheets.

Important project rules:

- Active projects appear in new timesheet entry selections.
- Deactivated projects remain visible in historical timesheets and exports.
- Projects with timesheet entries cannot be deleted.
- Deactivate projects instead of deleting them when they have history.

## Manage Weekly Periods

Use **Weekly Periods** to control when users can create, submit, or resubmit timesheets.

A valid weekly period:

- Has a week number from 1 to 53.
- Has a year from 2020 to 2100.
- Starts on Monday.
- Ends on Sunday.
- Contains exactly 7 days.
- Is either open or closed.

Open periods allow drafts, submissions, and resubmissions. Closed periods block new changes except when an approved timesheet has been recalled for correction.

Users can only create one active timesheet per weekly period, even if the period remains open. Withdrawn and recalled timesheets remain active so the owner corrects the same record. If a Super Admin voids an approved timesheet for replacement, the owner can create a new timesheet for that same weekly period while the voided record remains visible for audit history.

## Manage Leave Plan Approvers

Use **Leave Approvers** to assign the non-HOD reviewers in the leave-plan approval chain.

- **Director approver** reviews after HOD approval.
- **UAE HR approver** completes final approval for employee numbers starting with `MEC-HR-` or `MCE-HR-`.
- **Philippines HR approver** completes final approval for employee numbers starting with `MEC-PHIL-HR-`.
- Approvers can be any active user account.

If a Director or regional HR approver is missing, leave plans remain submitted at that stage and the review page shows a setup warning. Assign the missing approver, then the configured reviewer can continue from **Assigned Leave Plans**.

## Bulk Import Approved Leave

Use **All Leave Plans > Import CSV** to add historical leave that was already approved before it was entered in MEC Group Portal. Use **Add Approved Leave** instead when there is only one record to add.

The CSV must use this exact header order:

```csv
employee_code,attendance_code,start_date,end_date,duration_type,half_day_period,bereavement_relationship,approved_at,reason
MEC-HR-2026-101,L100,2026-03-10,2026-03-12,full_day,,,2026-02-20,Historical approved annual leave
```

Rules:

- Dates must use `YYYY-MM-DD`.
- `duration_type` must be `full_day` or `half_day`.
- Leave `half_day_period` blank for full-day leave. Use `morning` or `afternoon` for half-day leave.
- Leave `bereavement_relationship` blank unless the leave type is UAE `L180`. For UAE bereavement leave, use exactly `spouse` or `immediate_family`.
- The same CSV template supports Philippines leave rows. Use eligible Philippines leave codes such as `L190`, `L160`, `L170`, `L210`, `L220`, or `L230`; leave `bereavement_relationship` blank.
- `approved_at` is the original approval date and is required.
- `reason` is optional.

The import is preview-first. Upload the CSV, review any row errors, fix the CSV if needed, and upload it again. The final import button appears only when every row is valid. Validation checks the employee number, active Employee/HOD role, current department, leave eligibility, leave balance, date rules, and overlapping active leave.

Uploaded CSV files are not retained on the server. The portal parses the file for preview and discards the uploaded file immediately whether preview succeeds or fails. Only normalized preview rows and errors are kept temporarily in your session until import, a new upload, or session expiry.

If an imported approved leave record is wrong, use the existing Super Admin void flow from the leave-plan review page. Voiding keeps the audit trail while removing the record from active leave usage.

## Manage Leave Settings

Admins and Super Admins use **Leave Settings** to set UAE leave defaults and active Philippines statutory leave defaults for maternity, parental, paternity, VAWC, special leave for women, and service incentive leave. UAE sick and maternity settings are maximum claimable calendar-day limits; employee and HOD balances show only the full-pay portion. UAE bereavement settings control the calendar-year spouse-death and immediate-family-death balances for `L180`; employees must also have HR eligibility approval for the selected bereavement relationship.

- The annual default applies to every user whose **Annual leave allowance override** is blank.
- Sick leave uses the regional default only. UAE sick leave counts calendar days excluding applicable holidays; employees and HODs see 15 full-pay days, while validation allows the configured maximum and Admin/Super Admin review shows 15 full-pay days, 30 half-pay days, and 45 unpaid days.
- UAE maternity leave counts calendar days excluding applicable holidays; employees and HODs see 45 full-pay days, while validation allows the configured maximum and Admin/Super Admin review shows 45 full-pay days and 15 half-pay days.
- Philippines service incentive leave defaults to 5 days and is hidden/blocked for UAE employees.
- The allowances are calendar-year based and refresh automatically each January 1.
- Unused days expire on December 31 and do not carry over.
- No automation is required for refresh because leave usage is calculated dynamically from leave plans in each year.
- Submitted, approved, and cancellation-requested entitled leave plans consume allowance. Draft, rejected, cancelled, recalled, and voided plans do not consume allowance.

## Manage Holidays

Use **Holidays** to maintain company holidays used by leave calendars and leave entitlement day counting.

- Holidays can be global, UAE-specific, or Philippines-specific.
- A holiday can be a single date or a date range.
- The same region cannot have duplicate holiday dates.
- Deactivate a holiday when it should stop affecting calendars and leave counts without deleting its history.
- Active applicable holidays appear as read-only events in leave calendars.
- Changes are recorded in Audit Logs as `holiday_created`, `holiday_updated`, `holiday_activated`, or `holiday_deactivated`.

## Automations

Use **Automations** to enable or disable scheduled jobs.

The system includes:

| Automation | What it does |
| --- | --- |
| Weekly Period Auto Creation | Creates the current Monday-to-Sunday period if it does not already exist. |
| Missing Timesheet Reminders | Emails employees who are missing a submitted or approved timesheet for the latest past open period. |

Disabling an automation pauses the scheduled background job.

### Automation Audit Action Names

Use these action names when filtering **Audit Logs** for automation activity.

| Automation | Action name | Meaning |
| --- | --- | --- |
| Weekly Period Auto Creation | `timesheet_period_auto_creation_succeeded` | The automation command completed. Details show whether it created a period or skipped because the period already existed. |
| Weekly Period Auto Creation | `timesheet_period_auto_creation_failed` | The automation command did not run because it was disabled. |
| Weekly Period Auto Creation | `timesheet_period_auto_created` | A weekly period was created by the automation. |
| Weekly Period Auto Creation | `timesheet_period_auto_create_skipped` | The target weekly period already existed, so no duplicate was created. |
| Missing Timesheet Reminders | `timesheet_missing_reminders_succeeded` | The reminder automation completed for an eligible period. Details include how many emails were sent. |
| Missing Timesheet Reminders | `timesheet_missing_reminders_failed` | The reminder automation did not send emails because it was disabled or no eligible open past period existed. |
| Missing Timesheet Reminders | `timesheet_missing_reminder_sent` | One missing-timesheet reminder email was sent to one employee. |
| Automation Controls | `automation_enabled` | A Super Admin enabled an automation. |
| Automation Controls | `automation_disabled` | A Super Admin disabled an automation. |

## Audit Logs

Use **Audit Logs** to review important actions such as:

- User creation, updates, and deletion.
- Department and project changes.
- Weekly period changes.
- Timesheet submission, withdrawal, approved recall, approval, rejection, resubmission, voiding, and deletion.
- Automation enable/disable actions.
- Missing timesheet reminders.

Audit logs can be filtered by action, user, and date range. Some logs include expandable before/after details. Timesheet history is also shown under each timesheet's entry table from a dedicated history table, so it remains available even if general audit logs are later cleaned up. Only Super Admin users can see stored IP addresses in that history.

Use **Export Excel** to download an Excel file of audit logs using the current filters.

Exports are protected from repeated or duplicate requests. If an export is already running, or too many exports are requested in a short time, MEC Group Portal shows a warning and asks you to wait before trying again.

To clean up stored audit logs:

1. Select individual log checkboxes, or use the page checkbox to select all logs visible on the current page.
2. Select **Delete Selected** and confirm in the modal.
3. To delete every log matching the current filters, tick **I understand this permanently deletes all matching logs**, select **Delete All Matching Filters**, and confirm in the modal.

Only Super Admin users can delete audit logs.

## All Timesheets And Export

Use **All Timesheets** to filter, review, and export records.

1. Go to **All Timesheets**.
2. Filter by week, year, department, user, role, or status.
3. Select **Apply Filters**.
4. Select **View** to open a timesheet.
5. Use **Summary Report Preview** to review summary totals in the portal when the selected week range is 1 to 6 weekly periods.
6. Use **Export Excel** to download an Excel workbook using the current filters.

Use the **Role** filter to focus on Employees, Heads of Department, Admins, or Super Admins.

Use **Status: Not Submitted** with a week and year to show active department-assigned users who do not have a submitted or approved timesheet for that weekly period. This helps identify Heads of Department, Admins, or Super Admins assigned to departments who still need to submit their own timesheets.

Exports are protected from repeated or duplicate requests. If an export is already running, or too many exports are requested in a short time, MEC Group Portal shows a warning and asks you to wait before trying again.

The **Summary Report Preview** button appears when the current filters include a year and a week range of 1 to 6 weekly periods. The preview shows only the **Project Summary** and **Attendance Summary** tables in MEC Group Portal, so Super Admins can check totals before downloading Excel. If more than 6 weekly periods are selected, MEC Group Portal hides the preview and shows a notice asking you to narrow the week range or use **Export Excel**.

The export includes:

- A **Project Weekly Summary** worksheet for project-chargeable hours.
- An **Attendance Code Summary** worksheet for leave and other non-project hours.
- Optional individual employee weekly timesheet worksheets.
- Employee job title where available, or `-` when blank.
- Regular hours, overtime hours, total hours, attendance/project codes, weekend columns, totals, and remarks.

Use the Attendance Code Summary to reconcile payroll/manhour totals with project totals when employees submit leave codes without a project/job number.

## HOD Timesheets And Tracker

Use **HOD Timesheets** when you only need to review Head of Department submissions. The page excludes ordinary employee, Admin, and Super Admin timesheets.

1. Go to **HOD Timesheets**.
2. Filter by department, HOD, status, week, or year.
3. Select **Review** to open the timesheet.
4. Approve or reject submitted HOD timesheets from the detail page.
5. Recall approved HOD timesheets with a required reason when a correction is needed after approval.

Use **HOD Tracker** to monitor HOD submissions for a selected weekly period.

1. Go to **HOD Tracker**.
2. Select a weekly period.
3. Optionally filter by department.
4. Review each HOD's period status.
5. Use **Send Reminder** for one missing HOD or **Notify All Missing** for all missing HODs in the selected view.

Submitted and approved HOD timesheets are treated as complete. Draft, rejected, withdrawn, recalled, and missing records are treated as needing action. Reminder emails are sent only to active HODs with an email address, and a manual cooldown prevents repeated reminders for the same HOD and weekly period.

## Approval Rules

Super Admins can approve or reject submitted timesheets where needed, but cannot approve or reject their own timesheet. Super Admins can recall approved timesheets where needed, but cannot recall their own approved timesheet.

Rejecting or recalling a timesheet requires a comment. The comment is visible to the timesheet owner and stored in the timesheet history.

When a Head of Department submits or resubmits their own timesheet, active Admins and Super Admins receive an email notification unless **Receive HOD timesheet submission emails** is turned off on their user account.

When a Super Admin approves, rejects, or recalls a Head of Department timesheet, the Head of Department receives an email for that weekly period.

## All Leave Plans

Use **All Leave Plans** to review leave plans across all departments.

- Filter leave plans by department, multiple employees, status, or attendance code.
- Review the staged approval progress for HOD, Director, and HR.
- Approve or reject the current submitted approval stage when the required approver is configured, except your own.
- Approve or reject cancellation requests, except your own.
- Use **Calendar** to visualize submitted, approved, cancellation-requested leave, and company holidays by month.
- Entitled leave limits are enforced at employee submission time. Cross-year plans consume each calendar year's allowance separately.

Leave plan submission, stage approval, final approval, rejection, cancellation request, cancellation approval, and cancellation rejection are recorded in audit logs. Employees, HOD approvers, the Director approver, and regional HR approvers receive email notifications for the relevant workflow events.

## Recalling Or Voiding Approved Timesheets

Use recall when an approved timesheet needs to be corrected by the same owner while preserving one active record for that weekly period.

1. Go to **All Timesheets**.
2. Open the approved timesheet.
3. In **Recall approved timesheet**, enter a clear recall reason.
4. Select **Recall approved timesheet** and confirm.

Important recall rules:

- Super Admins can recall approved timesheets, except their own.
- Admins can recall approved Employee and Head of Department timesheets, except their own.
- HODs can recall approved employee timesheets in departments they manage.
- A recall reason is required.
- The employee receives an email with the reason and can correct and resubmit the same timesheet.
- The reason, actor, timestamp, and IP address are stored in timesheet history. IP addresses are visible only to Super Admin users.

Use voiding when an already approved timesheet should be cancelled and replaced by a corrected employee submission.

1. Go to **All Timesheets**.
2. Open the approved timesheet.
3. In **Correction action**, enter a clear void reason.
4. Select **Void timesheet** and confirm.

Important voiding rules:

- Only Super Admins can void timesheets.
- Only approved timesheets can be voided.
- Super Admins cannot void their own timesheet.
- A void reason is required and is stored with the timesheet history and audit log.
- The original timesheet remains visible with status **Voided**.
- Voided timesheets are excluded from normal Excel exports and submission-complete checks.
- The employee can create and submit a corrected timesheet for the same weekly period.

## Submit Your Own Timesheet

Super Admins can use **My Timesheets** for their own weekly timesheets only if they are assigned to a department.

Submitting your own Super Admin timesheet does not send a timesheet-submitted email to yourself or to HOD approvers.

The weekly timesheet form groups entries by day. Each day header shows the full date, ISO date, weekday, and RT/OT totals. Use **Add project** for a blank same-day row, **Duplicate** to copy a row directly below it, **Copy Day** to paste one day's rows onto selected target days, and **Remove** to clear or delete a row.

Pressing **Enter** while editing a timesheet row does not save or submit the form. Use **Save Draft** or **Submit for Approval** when you are ready.

If you are not assigned to a department, MEC Group Portal disables timesheet creation for your account.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| User cannot create a timesheet | No department is assigned or no open period exists. | Assign a department or open/create the correct weekly period. |
| User cannot submit a timesheet | Required fields are missing, no hours were entered, or the period is closed. | Ask them to correct form errors or open the period if appropriate. |
| Summary Report Preview is hidden | No week/year was selected, Status is Not Submitted, or the selected week range is more than 6 weekly periods. | Select a year and a 1 to 6 week range, then apply filters. |
| Cannot delete a user | The user may be your own account or an assigned HOD without a replacement. | Select an active replacement HOD or deactivate the user. |
| Cannot delete a project or department | It has historical usage or assigned users/HOD. | Deactivate it instead. |
| Automation did not run | It may be disabled or no eligible period exists. | Check **Automations**, **Weekly Periods**, and **Audit Logs**. |
