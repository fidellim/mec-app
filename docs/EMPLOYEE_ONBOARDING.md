# Employee Onboarding Guide

Welcome to **MEC Group Portal**. This guide explains how employees use the portal to create, save, submit, and track weekly timesheets.

## Signing In

1. Open the MEC Group Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

If you forget your password, select **Forgot password?** on the sign-in page. Enter your account email, open the reset email, and choose a new password. Reset links expire after 1 hour. Password fields include a show/hide option so you can check what you typed before submitting.

## Main Menu

Employees can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows your current period, drafts, rejected timesheets, and recent submissions. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history and lets you create a new weekly timesheet. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Leave Plans** | Lets you plan leave, submit it for approval, request cancellation, and open your department leave calendar. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this onboarding guide whenever you need a refresher. |

## Timesheet Statuses

| Status | Meaning | What happens next |
| --- | --- | --- |
| ![Draft](/images/status/draft.svg) | The timesheet is saved but not submitted. | You can keep editing it or delete it. |
| ![Submitted](/images/status/submitted.svg) | The timesheet has been sent for review. | It is locked unless you recall it before review. |
| ![Approved](/images/status/approved.svg) | The timesheet has been accepted. | You can view it, but cannot edit it. |
| ![Rejected](/images/status/rejected.svg) | The timesheet was returned with a comment. | You can edit and resubmit it. |
| Withdrawn | You withdrew your own submitted timesheet before approval. | You can edit and resubmit it. |
| Recalled | An authorized reviewer recalled an approved timesheet for correction. | Review the history comment, edit, and resubmit it. |
| Voided | A Super Admin cancelled an approved timesheet because it needs correction. | Create a corrected timesheet for the same weekly period if the period is open. |

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

Each day is shown as its own section. The day header shows the full date, ISO date, weekday, and that day's regular time and overtime totals. The entry rows below the header are where you enter attendance code, project/job number, regular hours, overtime hours, remarks, and row actions.

Pressing **Enter** while editing a timesheet row does not save or submit the form. Use **Save Draft** or **Submit for Approval** when you are ready.

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

Use **Add project** on a daily row when your work for one day needs to be split across multiple project/job numbers. This creates a blank row for the same day with default empty values.

Use **Duplicate** when the next row should start with the same attendance code, project/job number, hours, and remarks as the row you selected. The duplicate row is inserted directly below the selected row so you can quickly adjust only the fields that changed.

Use **Copy Day** when another day should use the same set of rows. Select **Copy Day** in the source day's header, choose one or more target days in the modal, then select **Paste to selected days**. Pasting replaces all existing entries on each selected target day, so review the warning and selected days before confirming. The pasted rows keep the target day's date while copying attendance code, project/job number, regular hours, overtime hours, and remarks from the copied day.

If you remove the only row for a day, MEC Group Portal clears that row instead of deleting the date entirely. This keeps all days in the weekly period visible.

If a day has multiple rows, **Remove** deletes the selected extra row. The day totals update automatically after rows are added, duplicated, copied, pasted, removed, or edited.

## Edit, Recall, Or Delete

You can:

- Edit **draft** timesheets.
- Edit **rejected** timesheets after reading the rejection comment.
- Withdraw a **submitted** timesheet before it is approved, which marks it as withdrawn and lets you edit it again.
- Edit **recalled** timesheets after reading the recall reason in the history log.
- Delete a **draft** timesheet.
- Create a corrected timesheet for a weekly period after a Super Admin voids the previously approved timesheet.

You cannot:

- Create two timesheets for the same weekly period.
- Edit submitted timesheets unless you withdraw them first.
- Edit approved timesheets.
- Submit or resubmit for closed weekly periods, unless an approved timesheet for that period was specifically recalled for correction.

If an approved timesheet is recalled by a Head of Department, Admin, or Super Admin, MEC Group Portal emails you with the reason and keeps the reviewer, timestamp, and comment in the timesheet history under the entry table.

## Leave Plans

Use **My Leave Plans** to plan leave before filling the related weekly timesheet.

1. Go to **My Leave Plans**.
2. Select **Create Leave Plan**.
3. Choose the leave type, start date, end date, duration, and optional reason.
4. Select **Save Draft** if you are not ready, or **Submit for Approval** to send it to your HOD approvers.
5. Use **Calendar** to see submitted, approved, cancellation-requested leave, and applicable company holidays in your department by month.

Draft and rejected leave plans can be edited. Submitted leave plans are locked until reviewed. Approved leave plans can be cancelled only by sending a cancellation request for review.

The leave form shows yearly allowance, used or reserved days, and remaining days for each yearly leave entitlement available to your profile. The allowances refresh every January 1, unused days do not carry over, and submitted leave is blocked if it exceeds your remaining allowance. Maternity leave is available only when your gender is set to Female. Parental leave is available only when your marital status is set to Married. `L190 - Service Incentive Leave` is available only to Philippines employees and defaults to 5 days.

For UAE employees, sick leave and maternity leave count calendar days, including weekends, but applicable company holidays are excluded. Balance cards show the full-pay allowance: 15 sick leave days and 45 maternity leave days. Additional approved days may move into half-pay or unpaid bands during payroll review.

For UAE bereavement / compassionate leave, select whether the request is for spouse or immediate family. Spouse and immediate-family balances are tracked separately for each calendar year and reset each January 1.

Holiday entries are read-only. They help explain why a date range may use fewer counted leave days than the number of calendar days selected.

When a weekly timesheet overlaps approved leave, the timesheet form shows a warning with the planned leave dates and leave code. You still need to enter the correct timesheet row yourself.

## After Submission

When you submit or resubmit a timesheet, your reviewer receives an email notification.

When your timesheet is approved, rejected, or recalled after approval, you receive an email notification. Rejection and approved-recall emails include the comment explaining what needs to be corrected. Withdrawing your own submitted timesheet does not send an email to you.

When you submit or resubmit a leave plan, your HOD approvers receive an email. You receive an email when the leave plan or cancellation request is approved or rejected.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot create a timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Cannot submit a timesheet | Required attendance code/project is missing, no hours were entered, or the period is closed. | Correct the highlighted fields or ask whether the period should be open. |
| Cannot edit a timesheet | It is submitted or approved. | Withdraw it if still submitted, or ask an authorized reviewer to recall it if it was already approved. |
| Cannot create another timesheet for the same week | One active timesheet already exists for that period. | Open and edit the existing draft/rejected/withdrawn/recalled timesheet, or view the existing submitted/approved one. If an approved timesheet needs replacement instead of correction, a Super Admin can void it. |
| Password reset link expired | More than 1 hour passed since the link was requested. | Request a new reset link from the sign-in page. |
