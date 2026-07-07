# Philippines Head Of Department Onboarding Guide

Welcome to **MEC Group Portal**. This guide explains how Philippines Heads of Department manage their own weekly timesheets, review department submissions, and review leave plans.

## Signing In

1. Open the MEC Group Portal URL provided by the company.
2. Enter your email and password on the **Sign in** page.
3. Select **Login**.
4. Use **Logout** from the top bar when finished.

If you forget your password, select **Forgot password?** on the sign-in page. Password reset emails are sent only to active existing user accounts, and each reset link expires after 1 hour.

## Main Menu

Heads of Department can access:

| Menu item | What it is for |
| --- | --- |
| ![Dashboard](/images/sidebar/dashboard.svg) **Dashboard** | Shows managed-department submission counts and review shortcuts. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Timesheets** | Shows your personal weekly timesheet history and lets you create your own weekly timesheet. |
| ![My Timesheets](/images/sidebar/my-timesheets.svg) **My Leave Plans** | Lets you plan your own leave and open your department leave calendar. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Department Timesheets** | Lets you review employee timesheets in departments you manage. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Department Leave Plans** | Lets you review and visualize leave plans in departments you manage. |
| ![Department Timesheets](/images/sidebar/department-timesheets.svg) **Assigned Leave Plans** | Appears when you are configured as the Director or Philippines HR leave approver. |
| ![Submission Tracker](/images/sidebar/submission-tracker.svg) **Submission Tracker** | Shows who has submitted in your managed departments and lets you send reminder emails. |
| ![Help Guide](/images/sidebar/guide.svg) **Help Guide** | Opens this Philippines HOD guide whenever you need a refresher. |

## Submit Your Own Timesheet

As a Head of Department, you can use **My Timesheets** for your own weekly timesheet.

1. Go to **My Timesheets**.
2. Select **Create Weekly Timesheet**.
3. Choose an open weekly period.
4. Enter daily attendance, project/job number, regular hours, overtime hours, and remarks.
5. For leave codes, use regular hours only and leave the project/job number blank if the time should not be charged to a project.
6. Select **Save Draft** or **Submit for Approval**.

You need to be assigned to a department before you can create or submit your own timesheet.

## Review Department Timesheets

1. Go to **Department Timesheets**.
2. Filter by department, status, employee, week number, or year if needed.
3. Select **Review** on a timesheet.
4. Check the employee, department, regular hours, overtime hours, total hours, attendance codes, project/job numbers, and remarks.
5. Select **Approve** if the submission is correct.
6. Enter a rejection comment and select **Reject** if the employee needs to correct it.
7. For an already approved timesheet that needs correction, enter a recall reason and select **Recall approved timesheet**.

You can approve or reject employee timesheets in departments assigned to you. You cannot approve or reject your own timesheet, another HOD's timesheet, records outside your managed departments, or records that are not currently submitted.

If a Super Admin configured a HOD exception for a specific employee, you may stop receiving emails for that employee, see their record without approval buttons, or not see their record in your managed-department views.

## Submission Tracker

Use **Submission Tracker** to monitor submission progress for departments you manage. Choose a weekly period, select a department if needed, then use **Send Reminder** or **Notify All Missing** for employees who have not submitted.

Submitted and approved timesheets are treated as complete. Employees with no timesheet, a draft, rejected, withdrawn, or recalled timesheet can appear as needing a reminder.

## Review Department Leave Plans

Use **Department Leave Plans** to review leave requests for departments you manage.

1. Go to **Department Leave Plans**.
2. Filter by department, status, or employee if needed.
3. Select **Review** on a leave plan.
4. Select **Approve** if the planned leave is accepted by the department.
5. Enter a rejection comment and select **Reject** if the employee needs to revise it.
6. Use **Calendar** to view submitted, approved, cancellation-requested leave, and company holidays by month.

Your approval is the first step. After you approve a submitted leave plan, it moves to the configured Director of Engineering & Project Management approver, then to the Philippines HR approver. The plan is fully approved only after HR approval.

For Philippines employees, employee numbers beginning with `MEC-PHIL-HR-` use Philippines leave settings and Philippines HR approval. Philippines employees can see `L190 - Service Incentive Leave`, which defaults to 5 days. Maternity leave is available only when gender is set to Female, and parental leave is available only when marital status is set to Married.

Most Philippines leave types use working leave days: Saturdays, Sundays, and active applicable company holidays are excluded from leave usage. Submitted, approved, and cancellation-requested leave plans reserve allowance. Rejected, cancelled, recalled, and voided plans do not reserve allowance.

Holiday entries are read-only and include region labels where applicable.

## Email Notifications

You receive email when an employee in one of your managed departments submits or resubmits a timesheet, submits or resubmits a leave plan, or requests cancellation. Employees receive email when you approve, reject, or recall their submissions where the workflow sends notifications.

## Quick Troubleshooting

| Problem | Likely reason | What to do |
| --- | --- | --- |
| Cannot create your own timesheet | No department is assigned or no open period exists. | Contact the system administrator. |
| Cannot approve a timesheet | It may be your own timesheet, another HOD's timesheet, outside your managed departments, or not submitted. | Follow the approval rules above. |
| Reminder says none were sent | The employee may already have submitted or approved the period, or all missing employees may still be on reminder cooldown. | Check the tracker status and cooldown message for that employee. |
| Employee asks about UAE leave allowances | Philippines employees use separate Philippines leave settings. | Direct them to their leave balance cards or Philippines HR. |
