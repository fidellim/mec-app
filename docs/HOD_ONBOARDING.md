# Head Of Department Onboarding Guide

Welcome to **MEC Group Portal**. This guide explains how Heads of Department use the portal to manage their own weekly timesheets, review department submissions, and remind employees with missing submissions.

## Signing In

1. Open the MEC Group Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

If you forget your password, select **Forgot password?** on the sign-in page. Password reset emails are only sent to active existing user accounts, and each reset link expires after 1 hour.

The top bar shows your name and role. A theme toggle is available if you prefer light or dark mode.

## Main Menu

Heads of Department can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows managed-department submission counts and review shortcuts. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history and lets you create your own weekly timesheet. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Leave Plans** | Lets you plan your own leave and open your department leave calendar. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Department Timesheets** | Lets you review employee timesheets in departments you manage. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Department Leave Plans** | Lets you review and visualize leave plans in departments you manage. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Assigned Leave Plans** | Appears when you are configured as the Director, UAE HR, or Philippines HR leave approver. |
| ![Submission Tracker](/images/sidebar/submission-tracker.svg) **Submission Tracker** | Shows who has submitted in your managed departments and lets you send reminder emails. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this onboarding guide whenever you need a refresher. |

## Timesheet Statuses

| Status | Meaning | What happens next |
| --- | --- | --- |
| ![Draft](/images/status/draft.svg) | The timesheet is saved but not submitted. | The owner can keep editing it. |
| ![Submitted](/images/status/submitted.svg) | The timesheet has been sent for review. | It can be approved or rejected. |
| ![Approved](/images/status/approved.svg) | The timesheet has been accepted. | No further action is needed. |
| ![Rejected](/images/status/rejected.svg) | The timesheet was returned with a comment. | The owner can correct and resubmit it. |
| ![Withdrawn](/images/status/withdrawn.svg) | The owner withdrew a submitted timesheet before approval. | The owner can edit and resubmit it. |
| ![Recalled](/images/status/recalled.svg) | An approved timesheet was sent back for correction. | The owner can correct and resubmit it. |

## Dashboard

The **Head of Department Dashboard** shows counts for the current open period across the departments you manage:

- Pending approvals.
- Approved this week.
- Rejected this week.
- Missing submissions.

If you manage more than one department, the dashboard also shows the department names included in the summary.

Use **Review Pending Approvals** to jump directly to submitted timesheets.

## Submit Your Own Timesheet

As a Head of Department, you can also use **My Timesheets** for your own weekly timesheet.

1. Go to **My Timesheets**.
2. Select **Create Weekly Timesheet**.
3. Choose an open weekly period.
4. Enter daily attendance, project/job number, regular hours, overtime hours, and remarks.
5. For leave codes, use regular hours only and leave the project/job number blank if the time should not be charged to a project.
6. Select **Save Draft** or **Submit for Approval**.

The weekly timesheet form groups entries by day. Each day header shows the full date, ISO date, weekday, and RT/OT totals. Use **Add project** for a blank same-day row, **Duplicate** to copy a row directly below it, **Copy Day** to paste one day's rows onto selected target days, and **Remove** to clear or delete a row.

Pressing **Enter** while editing a timesheet row does not save or submit the form. Use **Save Draft** or **Submit for Approval** when you are ready.

You need to be assigned to a department before you can create or submit your own timesheet.

When you submit or resubmit your own timesheet, MEC Group Portal emails active Admins and eligible Super Admins so they can review it. You also receive a confirmation email that your own timesheet was submitted.

## Review Department Timesheets

1. Go to **Department Timesheets**.
2. Filter by department, status, employee, week number, or year if needed.
3. Select **Review** on a timesheet.
4. Check the employee, department, regular hours, overtime hours, total hours, attendance codes, project/job numbers, and remarks.
5. Select **Approve** if the submission is correct.
6. Enter a rejection comment and select **Reject** if the employee needs to correct it.
7. For an already approved timesheet that needs correction, enter a recall reason and select **Recall approved timesheet**.

Employee entry forms are grouped by day while editing, and submitted timesheets still show daily attendance, project/job, hour, and remarks details for review.

The rejection comment is required and is visible to the employee.
The approved-recall reason is also required. MEC Group Portal emails the employee and stores the reviewer, timestamp, reason, and IP address in the timesheet history. IP addresses are visible only to Super Admin users.

## Approval Rules

You can approve or reject employee timesheets in departments assigned to you.
You can recall approved employee timesheets in departments assigned to you when a correction is needed after approval.

Your managed departments can include:

- Your own profile department.
- A department where you are the primary Head of Department.
- A department where a Super Admin added you as an additional HOD approver.

You cannot:

- Approve or reject your own timesheet.
- Recall your own approved timesheet.
- Approve or reject another Head of Department's timesheet.
- Review departments outside your managed departments.
- Approve or reject timesheets that are not currently submitted.
- Recall timesheets that are not currently approved.

Super Admins may also assign specific users to another HOD approver in the same department. These exceptions only apply in departments where you are explicitly assigned as the primary HOD or an additional HOD approver. In that case, you may stop receiving email, you may still see the user's record with approval buttons hidden, or the user may be hidden from your managed-department views entirely, depending on the exception type.

## Submission Tracker

Use **Submission Tracker** to monitor submission progress for departments you manage.

1. Go to **Submission Tracker**.
2. Choose a weekly period.
3. Select a department if you want to narrow the list.
4. Select **View Period**.
5. Review each employee's period status.
6. Use **Send Reminder** for one missing employee.
7. Use **Notify All Missing** to remind all missing employees for that period and selected department.

Submitted and approved timesheets are treated as complete. Employees with no timesheet, a draft, rejected, withdrawn, or recalled timesheet can appear as needing a reminder.

Reminder emails have a temporary cooldown per employee and weekly period. If an employee was already reminded recently, **Send Reminder** is disabled and shows when another reminder can be sent. **Notify All Missing** skips employees who are still on cooldown.

## Review Department Leave Plans

Use **Department Leave Plans** to review leave requests for departments you manage.

1. Go to **Department Leave Plans**.
2. Filter by department, status, or employee if needed.
3. Select **Review** on a leave plan.
4. Select **Approve** if the planned leave is accepted by the department.
5. Enter a rejection comment and select **Reject** if the employee needs to revise it.
6. Use **Calendar** to view submitted, approved, cancellation-requested leave, and company holidays by month.

Your approval is the first step. After you approve a submitted leave plan, it moves to the configured Director of Engineering & Project Management approver, then to the employee's regional HR approver. The plan is fully approved only after HR approval.

You can approve or reject cancellation requests for approved leave plans in your managed departments. Cancellation requests stay HOD-only and do not go through Director/HR approval. You cannot approve, reject, or action cancellation for your own leave plan.

For entitled leave types, submitted, approved, and cancellation-requested plans reserve the employee's allowance. Rejected, cancelled, recalled, and voided plans do not reserve allowance.

Holiday entries are read-only and include region labels where applicable.

## Email Notifications

MEC Group Portal sends emails for key workflow events:

- You receive an email when an employee in one of your managed departments submits or resubmits a timesheet for review.
- You receive a confirmation email when you submit or resubmit your own Head of Department timesheet.
- You receive an email when an Admin or Super Admin approves or rejects your own Head of Department timesheet for that weekly period.
- You receive an email when an Admin or Super Admin recalls your approved Head of Department timesheet.
- Employees receive an email when you approve, reject, or recall their approved timesheet.
- Employees receive reminder emails when you send reminders from **Submission Tracker**.
- You receive an email when an employee in a managed department submits or resubmits a leave plan or requests cancellation.
- The configured Director approver receives an email when you approve a leave plan.
- Employees receive an email when a leave plan is rejected, fully approved by HR, or when you approve/reject their cancellation request.

If a Super Admin has set a notification or approval exception for a specific user, you may not receive approval-request emails for that user's submissions even though the records remain visible in your managed department pages.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot create your own timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Cannot approve a timesheet | It may be your own timesheet, another HOD's timesheet, outside your managed departments, or not submitted. | Follow the approval rules above. |
| Reminder says none were sent | The employee may already have submitted or approved the period, or all missing employees may still be on reminder cooldown. | Check the tracker status and cooldown message for that employee. |
| Employee says they cannot submit | Their period may be closed, department may be missing, or required fields may be incomplete. | Ask them to check the highlighted form errors or contact the system administrator. |
