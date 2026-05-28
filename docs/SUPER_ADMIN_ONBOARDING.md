# Super Admin Onboarding Guide

Welcome to **MEC Portal**. This guide explains how Super Admins manage the portal setup, monitor records, export timesheets, control automations, and review audit logs.

## Signing In

1. Open the MEC Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

## Main Menu

Super Admins can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows system-wide totals and submission summary. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history, if you are assigned to a department. |
| ![All Timesheets](/images/sidebar/all-timesheets.svg) **All Timesheets** | Lets you filter, review, approve where allowed, and export timesheets. |
| ![Users](/images/sidebar/users.svg) **Users** | Create, edit, activate/deactivate, and delete users. |
| ![Departments](/images/sidebar/departments.svg) **Departments** | Maintain departments and HOD assignment. |
| ![Projects](/images/sidebar/projects.svg) **Projects** | Maintain project/job numbers used in timesheets. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Weekly Periods** | Open or close weekly submission windows. |
| ![Automations](/images/sidebar/automations.svg) **Automations** | Enable or disable scheduled background jobs. |
| ![Audit Logs](/images/sidebar/audit-logs.svg) **Audit Logs** | Review important system actions. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this onboarding guide whenever you need a refresher. |

## Timesheet Statuses

| Status | Meaning |
| --- | --- |
| ![Draft](/images/status/draft.svg) | Saved by the owner but not submitted. |
| ![Submitted](/images/status/submitted.svg) | Sent for review. |
| ![Approved](/images/status/approved.svg) | Accepted by an authorized reviewer. |
| ![Rejected](/images/status/rejected.svg) | Returned to the owner with a comment. |

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
- Role.
- Department.
- Active/inactive status.
- Password when creating or resetting a user.

Employee numbers are required for Employees and Heads of Department. The accepted format is:

```text
MEC-HR-YYYY-NNN
MCE-HR-YYYY-NNN
MEC-PHIL-HR-YYYY-NNN
```

The final number must have at least three digits.

Important user rules:

- Inactive users cannot use the system normally.
- Users without a department cannot create or submit personal timesheets.
- You cannot delete your own account.
- Deleting a user permanently deletes that user's timesheets and entries.
- If a user is assigned as a department HOD, a replacement active HOD in the same department must be selected before deletion.
- Deactivate users when history should remain easier to understand.

## Manage Departments

Use **Departments** to maintain department names, codes, active status, and Head of Department assignment.

Important department rules:

- Active departments are available for new user assignment.
- Deactivated departments remain on existing users and historical records.
- Departments with users, timesheets, or an assigned HOD cannot be deleted.
- Deactivate departments instead of deleting them when they have history.

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

Open periods allow drafts, submissions, and resubmissions. Closed periods block new changes.

Users can only create one timesheet per weekly period, even if the period remains open.

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
- Timesheet submission, recall, approval, rejection, resubmission, and deletion.
- Automation enable/disable actions.
- Missing timesheet reminders.

Audit logs can be filtered by action, user, and date range. Some logs include expandable before/after details.

## All Timesheets And Export

Use **All Timesheets** to filter, review, and export records.

1. Go to **All Timesheets**.
2. Filter by week, year, department, employee, or status.
3. Select **Apply Filters**.
4. Select **View** to open a timesheet.
5. Use **Export Excel** to download an Excel workbook using the current filters.

The export includes:

- A project summary worksheet.
- Individual employee weekly timesheet worksheets.
- Regular hours, overtime hours, total hours, attendance/project codes, weekend columns, totals, and remarks.

## Approval Rules

Super Admins can approve or reject submitted timesheets where needed, but cannot approve or reject their own timesheet.

Rejecting a timesheet requires a comment. The comment is visible to the timesheet owner.

## Submit Your Own Timesheet

Super Admins can use **My Timesheets** for their own weekly timesheets only if they are assigned to a department.

If you are not assigned to a department, MEC Portal disables timesheet creation for your account.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| User cannot create a timesheet | No department is assigned or no open period exists. | Assign a department or open/create the correct weekly period. |
| User cannot submit a timesheet | Required fields are missing, no hours were entered, or the period is closed. | Ask them to correct form errors or open the period if appropriate. |
| Cannot delete a user | The user may be your own account or an assigned HOD without a replacement. | Select a valid replacement HOD or deactivate the user. |
| Cannot delete a project or department | It has historical usage or assigned users/HOD. | Deactivate it instead. |
| Automation did not run | It may be disabled or no eligible period exists. | Check **Automations**, **Weekly Periods**, and **Audit Logs**. |
