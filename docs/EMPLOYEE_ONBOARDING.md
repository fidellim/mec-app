# Employee Onboarding Guide

Welcome to **MEC Portal**. This guide explains how employees use the portal to create, save, submit, and track weekly timesheets.

## Signing In

1. Open the MEC Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

## Main Menu

Employees can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows your current period, drafts, rejected timesheets, and recent submissions. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history and lets you create a new weekly timesheet. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this onboarding guide whenever you need a refresher. |

## Timesheet Statuses

| Status | Meaning | What happens next |
| --- | --- | --- |
| ![Draft](/images/status/draft.svg) | The timesheet is saved but not submitted. | You can keep editing it or delete it. |
| ![Submitted](/images/status/submitted.svg) | The timesheet has been sent for review. | It is locked unless you recall it before review. |
| ![Approved](/images/status/approved.svg) | The timesheet has been accepted. | You can view it, but cannot edit it. |
| ![Rejected](/images/status/rejected.svg) | The timesheet was returned with a comment. | You can edit and resubmit it. |

## Employee Dashboard

The **Employee Dashboard** shows:

- The current open weekly period.
- Your current timesheet for that period, if one exists.
- Drafts that still need attention.
- Rejected timesheets requiring correction.
- Recent submissions.

If you are not assigned to a department, timesheet creation is disabled and you will see **Department Required**. Contact the system administrator so your department can be assigned.

## Create A Weekly Timesheet

1. Go to **My Timesheets**.
2. Select **Create Weekly Timesheet**.
3. Choose an open weekly period.
4. Select **Continue**.
5. Enter daily entries for the week.
6. Select an attendance code and project/job number for rows where work hours are entered.
7. For leave codes, enter regular hours and leave the project/job number blank if the time should not be charged to a project.
8. Select **Save Draft** if you are not ready to submit.
9. Select **Submit for Approval** when the timesheet is complete.

## Daily Entry Rules

- Regular time is shown as **RT**.
- Overtime is shown as **OT**.
- A row with hours must have an attendance code.
- A row with work hours must have a project/job number.
- Leave codes do not require a project/job number.
- Leave codes accept regular hours only; overtime is not allowed for leave rows.
- Submission requires at least one row with hours greater than zero.
- Each entry date must be within the selected weekly period.
- Hours cannot be negative and cannot exceed 24 per regular or overtime field.
- Remarks are optional.

## Splitting One Day Across Projects

Use **Add project** on a daily row when your work for one day needs to be split across multiple project/job numbers.

If you remove the only row for a day, MEC Portal clears that row instead of deleting the date entirely. This keeps all days in the weekly period visible.

## Edit, Recall, Or Delete

You can:

- Edit **draft** timesheets.
- Edit **rejected** timesheets after reading the rejection comment.
- Recall a **submitted** timesheet before it is reviewed, which returns it to draft.
- Delete a **draft** timesheet.

You cannot:

- Create two timesheets for the same weekly period.
- Edit submitted timesheets unless they are recalled first.
- Edit approved timesheets.
- Submit or resubmit for closed weekly periods.

## After Submission

When you submit or resubmit a timesheet, your reviewer receives an email notification.

When your timesheet is approved or rejected, you receive an email notification. Rejection emails include the comment explaining what needs to be corrected.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot create a timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Cannot submit a timesheet | Required attendance code/project is missing, no hours were entered, or the period is closed. | Correct the highlighted fields or ask whether the period should be open. |
| Cannot edit a timesheet | It is submitted or approved. | Recall it if still submitted. |
| Cannot create another timesheet for the same week | One already exists for that period. | Open and edit the existing draft/rejected timesheet, or view the existing submitted/approved one. |
