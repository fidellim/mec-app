# Super Admin Onboarding Guide

Welcome to **MEC Portal**. This guide explains how Super Admins manage the portal setup, monitor records, export timesheets, control automations, and review audit logs.

## Signing In

1. Open the MEC Portal URL provided by the company.
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
| ![Users](/images/sidebar/users.svg) **Users** | Create, edit, activate/deactivate, and delete users. |
| ![Departments](/images/sidebar/departments.svg) **Departments** | Maintain departments and HOD assignments. |
| ![Projects](/images/sidebar/projects.svg) **Projects** | Maintain project/job numbers used in timesheets. |
| ![Weekly Periods](/images/sidebar/weekly-periods.svg) **Weekly Periods** | Open or close weekly submission windows. |
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
| Voided | Cancelled by a Super Admin because an approved timesheet needs correction. Voided records are kept for audit history and are excluded from corrected submissions and normal exports. |

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
- Role.
- Department.
- Active/inactive status.
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

Important user rules:

- Inactive users cannot use the system normally.
- Users without a department cannot create or submit personal timesheets.
- When a user's department changes, draft and rejected timesheets move to the new department. Submitted and approved timesheets stay in the original department.
- You cannot delete your own account.
- Deleting a user permanently deletes that user's timesheets and entries.
- If a user is assigned as a primary or additional department HOD, a replacement active HOD must be selected before deletion.
- Deactivate users when history should remain easier to understand.

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
- A HOD can approve or reject employee timesheets only for departments they manage.
- A HOD cannot approve or reject their own timesheet or another HOD's timesheet.
- Submission, resubmission, and recall emails go to every HOD approver assigned to that employee's department.
- The HOD dashboard, Department Timesheets page, Submission Tracker, and reminder tools use the full list of departments assigned to the HOD.
- If a HOD user's role is changed back to Employee, Admin, or Super Admin, MEC Portal automatically removes that user from primary HOD and additional HOD approver assignments.

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

Open periods allow drafts, submissions, and resubmissions. Closed periods block new changes.

Users can only create one active timesheet per weekly period, even if the period remains open. If a Super Admin voids an approved timesheet for correction, the owner can create a new timesheet for that same weekly period while the voided record remains visible for audit history.

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

Use **Export Excel** to download an Excel file of audit logs using the current filters.

Exports are protected from repeated or duplicate requests. If an export is already running, or too many exports are requested in a short time, MEC Portal shows a warning and asks you to wait before trying again.

To clean up stored audit logs:

1. Select individual log checkboxes, or use the page checkbox to select all logs visible on the current page.
2. Select **Delete Selected** and confirm in the modal.
3. To delete every log matching the current filters, tick **I understand this permanently deletes all matching logs**, select **Delete All Matching Filters**, and confirm in the modal.

Only Super Admin users can delete audit logs.

## All Timesheets And Export

Use **All Timesheets** to filter, review, and export records.

1. Go to **All Timesheets**.
2. Filter by week, year, department, employee, or status.
3. Select **Apply Filters**.
4. Select **View** to open a timesheet.
5. Use **Export Excel** to download an Excel workbook using the current filters.

Exports are protected from repeated or duplicate requests. If an export is already running, or too many exports are requested in a short time, MEC Portal shows a warning and asks you to wait before trying again.

The export includes:

- A **Project Weekly Summary** worksheet for project-chargeable hours.
- An **Attendance Code Summary** worksheet for leave and other non-project hours.
- Optional individual employee weekly timesheet worksheets.
- Employee job title where available, or `-` when blank.
- Regular hours, overtime hours, total hours, attendance/project codes, weekend columns, totals, and remarks.

Use the Attendance Code Summary to reconcile payroll/manhour totals with project totals when employees submit leave codes without a project/job number.

## Approval Rules

Super Admins can approve or reject submitted timesheets where needed, but cannot approve or reject their own timesheet.

Rejecting a timesheet requires a comment. The comment is visible to the timesheet owner.

## Voiding Approved Timesheets

Use voiding when an already approved timesheet has an error that should be replaced by a corrected employee submission.

1. Go to **All Timesheets**.
2. Open the approved timesheet.
3. In **Correction action**, enter a clear void reason.
4. Select **Void timesheet** and confirm.

Important voiding rules:

- Only Super Admins can void timesheets.
- Only approved timesheets can be voided.
- Super Admins cannot void their own timesheet.
- A void reason is required and is stored with the audit log.
- The original timesheet remains visible with status **Voided**.
- Voided timesheets are excluded from normal Excel exports and submission-complete checks.
- The employee can create and submit a corrected timesheet for the same weekly period.

## Submit Your Own Timesheet

Super Admins can use **My Timesheets** for their own weekly timesheets only if they are assigned to a department.

The weekly timesheet form groups entries by day. Each day header shows the full date, ISO date, weekday, and RT/OT totals. Use **Add project** for a blank same-day row, **Duplicate** to copy a row directly below it, **Copy Day** to paste one day's rows onto selected target days, and **Remove** to clear or delete a row.

If you are not assigned to a department, MEC Portal disables timesheet creation for your account.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| User cannot create a timesheet | No department is assigned or no open period exists. | Assign a department or open/create the correct weekly period. |
| User cannot submit a timesheet | Required fields are missing, no hours were entered, or the period is closed. | Ask them to correct form errors or open the period if appropriate. |
| Cannot delete a user | The user may be your own account or an assigned HOD without a replacement. | Select an active replacement HOD or deactivate the user. |
| Cannot delete a project or department | It has historical usage or assigned users/HOD. | Deactivate it instead. |
| Automation did not run | It may be disabled or no eligible period exists. | Check **Automations**, **Weekly Periods**, and **Audit Logs**. |
