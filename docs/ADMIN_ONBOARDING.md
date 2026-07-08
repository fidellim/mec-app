# Admin Onboarding Guide

Welcome to **MEC Group Portal**. This guide explains how Admin users monitor timesheets, review records, export Excel workbooks, and handle allowed approval actions.

## Signing In

1. Open the MEC Group Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

If you forget your password, select **Forgot password?** on the sign-in page. Password reset emails are only sent to active existing user accounts, and each reset link expires after 1 hour.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

## Main Menu

Admins can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows company-wide submission counts and department summary. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history, if you are assigned to a department. |
| ![All Timesheets](/images/sidebar/all-timesheets.svg) **All Timesheets** | Lets you filter, review, approve where allowed, and export timesheets. |
| ![All Timesheets](/images/sidebar/all-timesheets.svg) **All Leave Plans** | Lets you review leave plans and open the all-company leave calendar. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Leave Entitlements** | Shows company leave balances by employee, department, and year. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Assigned Leave Plans** | Appears when you are configured as the Director, UAE HR, or Philippines HR leave approver. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **HOD Timesheets** | Shows Head of Department timesheets that Admins can review. |
| ![Submission Tracker](/images/sidebar/submission-tracker.svg) **HOD Tracker** | Shows which Heads of Department have submitted for a selected weekly period. |
| ![Users](/images/sidebar/users.svg) **Users** | Lets you view users and edit Employee/HOD profile details. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Leave Settings** | Lets you maintain regional leave policy allowances and claimable limits. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Holidays** | Lets you maintain global, UAE, and Philippines company holidays. |
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

## Dashboard

The **Admin Dashboard** shows:

- Submitted this week.
- Approved this week.
- Rejected this week.
- Missing submissions.
- Department summary for the selected/current open period.

## All Timesheets

1. Go to **All Timesheets**.
2. Choose **Weekly** or **Monthly** report mode, then filter by week range or month, year, project, department, user, role, or status.
3. Select **Apply Filters**.
4. Select **View** to open a timesheet.
5. Use **Summary Report Preview** to review summary totals in the portal when the selected week range is 1 to 6 weekly periods.
6. Use **Export Excel** to download an Excel workbook using the current filters.

Use the **Role** filter to focus on Employees, Heads of Department, Admins, or Super Admins.

Use **Status: Not Submitted** with Weekly mode, a week, and a year to show active department-assigned users who do not have a submitted or approved timesheet for that weekly period. This is useful for checking whether Heads of Department, Admins, or Super Admins assigned to departments still need to submit their own timesheets.

## HOD Timesheets

Use **HOD Timesheets** when you only need to review Head of Department submissions.

1. Go to **HOD Timesheets**.
2. Filter by department, HOD, status, week, or year.
3. Select **Review** to open the timesheet.
4. Approve or reject submitted HOD timesheets from the detail page.

This page excludes ordinary employee, Admin, and Super Admin timesheets so HOD approvals are easier to find.

## HOD Submission Tracker

Use **HOD Tracker** to monitor HOD submissions for a selected weekly period.

1. Go to **HOD Tracker**.
2. Select a weekly period.
3. Optionally filter by department.
4. Review each HOD's period status.
5. Use **Send Reminder** for one missing HOD or **Notify All Missing** for all missing HODs in the selected view.

Submitted and approved HOD timesheets are treated as complete. Draft, rejected, and missing records are treated as needing action.

Reminder emails are sent only to active HODs with an email address. A manual reminder cooldown prevents the same HOD from being reminded repeatedly for the same weekly period.

## Review A Timesheet

The timesheet detail page shows:

- Employee name and employee number.
- Department.
- Regular hours.
- Overtime hours.
- Total hours.
- Daily attendance codes.
- Project/job numbers.
- Remarks.
- Rejection comment, if one exists.
- Timesheet history under the entry table, including status changes, comments, actors, and timestamps.

## Approval Rules

Admins can approve or reject submitted Employee and Head of Department timesheets.

Admins can also recall approved Employee and Head of Department timesheets when a correction is needed after approval. A recall reason is required, the timesheet owner receives an email, and the status changes from **Approved** to **Recalled**. The owner can then correct and resubmit the same timesheet. Use recall for normal corrections and reserve voiding for records that should be cancelled and replaced.

When a Head of Department submits or resubmits their own timesheet, active Admins receive an email notification so they can review it from **All Timesheets**. A Super Admin can turn off **Receive HOD timesheet submission emails** on an Admin account if that Admin should not receive those approval-request emails.

Admins cannot:

- Approve or reject their own timesheet.
- Recall their own approved timesheet.
- Edit another user's timesheet.
- Create users, delete users, change user roles, or edit Super Admin accounts.
- Manage departments, projects, weekly periods, automations, or audit logs.

Rejecting a timesheet requires a comment. The comment is visible to the timesheet owner.

When an Admin approves or rejects a timesheet, the timesheet owner receives an approval or rejection email for that weekly period.

When an Admin recalls an approved timesheet, the recall reason, Admin, timestamp, and IP address are stored in the history log. IP addresses are visible only to Super Admin users.

## All Leave Plans

Use **All Leave Plans** to review leave plans across all departments.

- Filter leave plans by department, multiple employees, status, or attendance code.
- Use **Add Approved Leave** to record one leave plan that was already approved outside the portal.
- Use **Import CSV** to preview and bulk import already-approved historical leave records.
- Approve or reject submitted leave plans, except your own.
- Approve or reject cancellation requests, except your own.
- Use **Calendar** to visualize submitted, approved, cancellation-requested leave, and company holidays by month.

For entitled leave types, submitted, approved, and cancellation-requested plans reserve the employee's allowance. Rejected, cancelled, recalled, and voided plans do not reserve allowance.

Holiday entries are read-only and include region labels where applicable.

Employees receive email notifications when their leave plan or cancellation request is approved or rejected.

### Bulk Import Approved Leave

Use bulk import only for leave that was already approved before it was entered in MEC Group Portal. Use **Add Approved Leave** when you only need to add one record.

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

If a historical approved leave record is imported incorrectly, ask a Super Admin to use the existing void flow so the record remains in audit history but no longer counts as active leave.

## Leave Entitlements

Use **Leave Entitlements** to review current leave balances for active Employees and Heads of Department.

- Filter by department, employee, and year.
- Balances use the same eligibility rules shown on employee leave forms.
- Submitted, approved, and cancellation-requested leave reserves allowance. Draft, rejected, cancelled, recalled, and voided leave does not.
- Active applicable holidays can change counted leave days and remaining balances when holiday records are added, updated, activated, or deactivated.

## Leave Settings

Use **Leave Settings** to maintain UAE leave defaults and active Philippines statutory leave defaults for maternity, parental, paternity, VAWC, special leave for women, and service incentive leave. UAE sick and maternity settings are maximum claimable calendar-day limits; employee and HOD balances show only the full-pay portion. UAE bereavement settings control the calendar-year spouse-death and immediate-family-death balances for `L180`; employees must also have HR eligibility approval for the selected bereavement relationship.

The annual default applies to every user whose **Annual leave allowance override** is blank. Leave allowances are calendar-year based, unused days expire on December 31, and remaining allowance is calculated dynamically from leave plans and active applicable holidays.

## Users And Holidays

Admins can use **Users** to view company users and edit Employee/HOD profile details such as name, employee number, initials, job title, gender, joining date, marital status, department, and active status. Admins cannot create users, delete users, change roles, reset passwords, edit Super Admin accounts, or configure HOD exclusions.

Use **Holidays** to maintain company holidays used by leave calendars and leave entitlement day counting.

- Holidays can be global, UAE-specific, or Philippines-specific.
- A holiday can be a single date or a date range.
- The same region cannot have duplicate holiday dates.
- Deactivate a holiday when it should stop affecting calendars and leave counts without deleting its history.

## Submit Your Own Timesheet

Admins can use **My Timesheets** for their own weekly timesheets only if they are assigned to a department.

Submitting your own Admin timesheet does not send a timesheet-submitted email to yourself or to HOD approvers.

1. Go to **My Timesheets**.
2. Select **Create Weekly Timesheet**.
3. Choose an open weekly period.
4. Enter daily attendance, project/job number, regular hours, overtime hours, and remarks.
5. Select **Save Draft** or **Submit for Approval**.

The weekly timesheet form groups entries by day. Each day header shows the full date, ISO date, weekday, and RT/OT totals. Use **Add project** for a blank same-day row, **Duplicate** to copy a row directly below it, **Copy Day** to paste one day's rows onto selected target days, and **Remove** to clear or delete a row.

Pressing **Enter** while editing a timesheet row does not save or submit the form. Use **Save Draft** or **Submit for Approval** when you are ready.

If you are not assigned to a department, MEC Group Portal disables timesheet creation for your account.

## Export Guide

Admins can export from **All Timesheets**.

1. Go to **All Timesheets**.
2. Choose **Weekly** for normal weekly reports or **Monthly** for management summaries.
3. Apply filters for week range or month, year, project, department, user, role, or status if needed.
4. Leave **Include individual employee timesheet sheets** unchecked for a faster weekly summary-only workbook, or check it when detailed weekly employee sheets are needed. Monthly reports are always summary-only.
5. Select **Summary Report Preview** when you want to check the Project Summary and Attendance Code Summary before downloading.
6. Select **Export Excel**.

Week range rules:

- Use **From Week** by itself to export one weekly period.
- Use **From Week** and **To Week** to export an inclusive range.
- **To Week** is optional, but it cannot be used without **From Week**.
- **Year** is required when filtering by week.
- The selected week or range must exist in the weekly periods for that year.

Monthly report rules:

- Monthly reports are for management reporting only; weekly timesheets remain the submission and approval unit.
- A monthly report includes only timesheet entry dates inside the selected calendar month.
- If a weekly period crosses into the previous or next month, dates outside the selected month are excluded from the monthly totals.
- Monthly workbooks include an **Employee Rates** sheet. Paste each employee's **Rate/Manhour** there to automatically calculate regular, overtime, and total cost columns in the monthly summary sheets.
- **Status: Not Submitted** is a weekly-period view and is not available in Monthly mode.

The export includes:

- A **Project Weekly Summary** worksheet for Weekly mode, or a **Project Monthly Summary** worksheet for Monthly mode, grouped by project with exported periods shown as columns.
- An **Attendance Code Summary** worksheet for leave and other non-project hours.
- An **Employee Rates** worksheet in Monthly mode for manually entering employee rate/manhour values used by the workbook formulas.
- Optional individual employee weekly timesheet worksheets in Weekly mode only.
- Employee ID, initials, employee name, job title, weekly regular hours, weekly overtime hours, weekly total hours, and project totals in the summary.
- Job title, regular hours, overtime hours, total hours, attendance/project codes, weekend columns, totals, and remarks in the individual timesheet worksheets.

In the summary, each project appears once, employees are listed down the rows, and exported weeks appear across the columns. Each week header shows the week number and date range above its Regular, Overtime, and Total columns. If an employee worked on the project in one exported week but not another, the missing week shows `0.00`.

The bottom **Grand Total** row also follows the week columns, so every exported week has its own regular, overtime, and total grand totals.

The **Attendance Code Summary** shows leave and non-project hours separately from project-chargeable hours. Use it to reconcile payroll/manhour totals with the Project Weekly Summary when employees have annual leave, sick leave, emergency leave, unpaid leave, paid holiday leave, maternity leave, parental leave, bereavement / compassionate leave, or other non-project hours.

By default, the export includes the **Project Weekly Summary** and **Attendance Code Summary** worksheets. Select **Include individual employee timesheet sheets** when the workbook also needs one detailed worksheet per employee timesheet.

The **Summary Report Preview** button appears when the current filters include a year and a week range of 1 to 6 weekly periods. The preview shows only the summary report tables inside MEC Group Portal:

- **Project Summary**, matching the Project Weekly Summary export.
- **Attendance Summary**, matching the Attendance Code Summary export.

If more than 6 weekly periods are selected, MEC Group Portal hides the preview and shows a notice asking you to narrow the week range or use **Export Excel**. This keeps the page responsive and avoids loading very large all-time summaries in the browser.

If a project is selected, the summary only includes employees and hours logged to that project. The export button shows **Preparing export...** while the workbook is being generated.

If an employee does not have a Job Title saved, exports show `-` in that column.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot approve a timesheet | Admins can approve/reject submitted Employee and HOD timesheets, but cannot approve their own or non-submitted records. | Use the correct authorized reviewer. |
| Export has too many records | Filters were not applied before export. | Apply week range, year, project, department, user, role, or status filters first. |
| Summary Report Preview is hidden | No week/year was selected, Status is Not Submitted, or the selected week range is more than 6 weekly periods. | Select a year and a 1 to 6 week range, then apply filters. |
| Export has too many worksheet tabs | Individual employee sheets were included. | Leave **Include individual employee timesheet sheets** unchecked for summary-only export. |
| Week export is rejected | The year is missing or the selected week/range has no matching weekly period. | Enter the year and choose a week that exists in Manage Weekly Periods. |
| Cannot create your own timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Timesheet cannot be edited | Admins do not edit other users' timesheets. | Reject submitted timesheets, or recall approved timesheets with a required reason. |
