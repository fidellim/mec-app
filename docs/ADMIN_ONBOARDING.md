# Admin Onboarding Guide

Welcome to **MEC Portal**. This guide explains how Admin users monitor timesheets, review records, export Excel workbooks, and handle allowed approval actions.

## Signing In

1. Open the MEC Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

## Main Menu

Admins can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows company-wide submission counts and department summary. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history, if you are assigned to a department. |
| ![All Timesheets](/images/sidebar/all-timesheets.svg) **All Timesheets** | Lets you filter, review, approve where allowed, and export timesheets. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this onboarding guide whenever you need a refresher. |

## Timesheet Statuses

| Status | Meaning |
| --- | --- |
| ![Draft](/images/status/draft.svg) | Saved by the owner but not submitted. |
| ![Submitted](/images/status/submitted.svg) | Sent for review. |
| ![Approved](/images/status/approved.svg) | Accepted by an authorized reviewer. |
| ![Rejected](/images/status/rejected.svg) | Returned to the owner with a comment. |

## Dashboard

The **Admin Dashboard** shows:

- Submitted this week.
- Approved this week.
- Rejected this week.
- Missing submissions.
- Department summary for the selected/current open period.

## All Timesheets

1. Go to **All Timesheets**.
2. Filter by week range, year, project, department, employee, or status.
3. Select **Apply Filters**.
4. Select **View** to open a timesheet.
5. Use **Export Excel** to download an Excel workbook using the current filters.

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

## Approval Rules

Admins can approve or reject submitted Head of Department timesheets.

Admins cannot:

- Approve or reject their own timesheet.
- Approve or reject ordinary employee timesheets.
- Edit another user's timesheet.
- Manage users, departments, projects, weekly periods, automations, or audit logs.

Rejecting a timesheet requires a comment. The comment is visible to the timesheet owner.

## Submit Your Own Timesheet

Admins can use **My Timesheets** for their own weekly timesheets only if they are assigned to a department.

1. Go to **My Timesheets**.
2. Select **Create Weekly Timesheet**.
3. Choose an open weekly period.
4. Enter daily attendance, project/job number, regular hours, overtime hours, and remarks.
5. Select **Save Draft** or **Submit for Approval**.

If you are not assigned to a department, MEC Portal disables timesheet creation for your account.

## Export Guide

Admins can export from **All Timesheets**.

1. Go to **All Timesheets**.
2. Apply filters for week range, year, project, department, employee, or status if needed.
3. Leave **Include individual employee timesheet sheets** unchecked for a faster summary-only workbook, or check it when detailed employee sheets are needed.
4. Select **Export Excel**.

Week range rules:

- Use **From Week** by itself to export one weekly period.
- Use **From Week** and **To Week** to export an inclusive range.
- **To Week** is optional, but it cannot be used without **From Week**.
- **Year** is required when filtering by week.
- The selected week or range must exist in the weekly periods for that year.

The export includes:

- A **Project Weekly Summary** worksheet grouped by project with exported weeks shown as columns.
- An **Attendance Code Summary** worksheet for leave and other non-project hours.
- Optional individual employee weekly timesheet worksheets.
- Employee ID, initials, employee name, job title, weekly regular hours, weekly overtime hours, weekly total hours, and project totals in the summary.
- Job title, regular hours, overtime hours, total hours, attendance/project codes, weekend columns, totals, and remarks in the individual timesheet worksheets.

In the summary, each project appears once, employees are listed down the rows, and exported weeks appear across the columns. Each week header shows the week number and date range above its Regular, Overtime, and Total columns. If an employee worked on the project in one exported week but not another, the missing week shows `0.00`.

The bottom **Grand Total** row also follows the week columns, so every exported week has its own regular, overtime, and total grand totals.

The **Attendance Code Summary** shows leave and non-project hours separately from project-chargeable hours. Use it to reconcile payroll/manhour totals with the Project Weekly Summary when employees have annual leave, sick leave, emergency leave, unpaid leave, paid holiday leave, maternity leave, paternity leave, compassionate leave, or other non-project hours.

By default, the export includes the **Project Weekly Summary** and **Attendance Code Summary** worksheets. Select **Include individual employee timesheet sheets** when the workbook also needs one detailed worksheet per employee timesheet.

If a project is selected, the summary only includes employees and hours logged to that project. The export button shows **Preparing export...** while the workbook is being generated.

If an employee does not have a Job Title saved, exports show `-` in that column.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot approve a timesheet | Admins can only approve/reject submitted HOD timesheets and cannot approve their own. | Use the correct authorized reviewer. |
| Export has too many records | Filters were not applied before export. | Apply week range, year, project, department, employee, or status filters first. |
| Export has too many worksheet tabs | Individual employee sheets were included. | Leave **Include individual employee timesheet sheets** unchecked for summary-only export. |
| Week export is rejected | The year is missing or the selected week/range has no matching weekly period. | Enter the year and choose a week that exists in Manage Weekly Periods. |
| Cannot create your own timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Timesheet cannot be edited | Admins do not edit other users' timesheets. | Return it with a rejection comment if correction is allowed. |
