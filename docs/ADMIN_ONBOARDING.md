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
2. Filter by week, year, department, employee, or status.
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
2. Apply filters for week, year, department, employee, or status if needed.
3. Select **Export Excel**.

The export includes:

- A project summary worksheet.
- Individual employee weekly timesheet worksheets.
- Regular hours, overtime hours, total hours, attendance/project codes, weekend columns, totals, and remarks.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot approve a timesheet | Admins can only approve/reject submitted HOD timesheets and cannot approve their own. | Use the correct authorized reviewer. |
| Export has too many records | Filters were not applied before export. | Apply week, year, department, employee, or status filters first. |
| Cannot create your own timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Timesheet cannot be edited | Admins do not edit other users' timesheets. | Return it with a rejection comment if correction is allowed. |
