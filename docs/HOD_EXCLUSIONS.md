# HOD Notification, Approval, And Visibility Exclusions

This feature lets Super Admin users manage exceptions for departments with more than one Head of Department approver. It is designed for cases where a HOD should not receive emails for, or should not approve/reject, specific users in a department they otherwise manage.

The feature is not a general permission system. It does not change department membership, Admin authority, or Super Admin authority. Visibility exclusions are a narrow HOD-only exception for hiding selected submitters from a selected HOD's managed-department work queues and reports.

## Purpose

- Reduce unnecessary HOD approval-request email noise.
- Assign approval responsibility more precisely when multiple HOD approvers manage the same department.
- Prevent a specific HOD from approving or rejecting a specific user's submissions when another HOD should handle them.
- Hide a specific user's managed-department records from a specific HOD when another HOD should handle both visibility and approval.
- Keep every submitter with at least one eligible HOD approver.
- Keep rules safe when users are promoted, moved, deactivated, deleted, or reassigned.

## Exclusion Types

### Email Notification Exclusion

An email notification exclusion means:

- The selected HOD does not receive submission or cancellation-request emails for the selected user.
- The HOD can still view the user's records.
- The HOD can still approve, reject, recall, or review cancellation requests where their normal HOD role allows it.

Use this when the HOD remains a valid backup approver but does not need email notifications for that user.

### Approval Responsibility Exclusion

An approval responsibility exclusion means:

- The selected HOD does not receive approval-request emails for the selected user.
- The HOD can still view the user's records in managed department pages.
- The HOD cannot approve, reject, recall approved records, approve cancellation, or reject cancellation for that user's HOD-reviewed submissions.
- Direct approval POST requests from that HOD return `403`.

Use this when another HOD approver is responsible for that user's timesheets or leave plans.

### Visibility Exclusion

A visibility exclusion means:

- The selected HOD does not see the selected user's records in managed-department timesheet lists, leave-plan lists, calendars, submission trackers, or leave-entitlement pages.
- The selected HOD cannot open or action that user's HOD-reviewed timesheets or leave plans by direct URL.
- Approval-request emails are suppressed because the HOD cannot act on hidden records.
- At least one other active eligible HOD must remain able to see and approve the selected user's records.

Use this when another HOD approver should be the visible owner for that user's submissions.

## Super Admin Workflow

1. Go to **Users**.
2. Edit an existing HOD user.
3. Find **HOD notification and approval exceptions**.
4. Use **Do not email this HOD for submissions from** for email-only exclusions.
5. Use **Do not allow this HOD to approve/reject submissions from** for approval responsibility exclusions.
6. Use **Do not show this employee to this HOD** for visibility exclusions.
7. Save the user.

The selectable users are limited to active Employee/HOD users in departments where that HOD is explicitly assigned as the primary HOD or an additional HOD approver. The edited HOD is not listed as a candidate.

The HOD's own profile department does not qualify by itself. If the HOD should manage exceptions for that department, assign the HOD as the primary HOD or as an additional HOD approver for that department first.

## Safety Rules

- Only Super Admin users manage exclusions.
- Exclusions apply to both primary HODs and additional HOD approvers.
- Exclusions do not apply across unrelated departments.
- Approval exclusions are allowed only when at least one other active eligible HOD approver remains for the submitter.
- Visibility exclusions are allowed only when at least one other active eligible HOD approver remains able to see and approve the submitter.
- Approval exclusions suppress approval-request emails automatically.
- Visibility exclusions suppress approval-request emails automatically.
- Email-only exclusions do not block approval/rejection.
- Admin and Super Admin approval flows are unchanged.
- Approval-excluded HODs can still see records for managed departments unless a visibility exclusion is also configured.

## Automatic Cleanup

Invalid or stale exclusions are removed automatically during normal management actions:

- If a HOD is changed to Employee, Admin, or Super Admin, HOD-side notification, approval, and visibility exclusions are removed.
- If a submitter is changed to Admin or Super Admin, submitter-side exclusions are removed.
- If a submitter moves to a department no longer managed by the HOD, exclusions for that HOD/submitter pair are removed.
- If department HOD assignments change, exclusions for HODs no longer managing that department are removed.
- If users are deleted, exclusion rows are removed by database foreign-key cascade.

## Database Tables

The feature uses three additive pivot tables:

- `hod_notification_exclusions`
- `hod_approval_exclusions`
- `hod_visibility_exclusions`

All three tables store:

- `hod_user_id`
- `employee_user_id`
- timestamps

Both tables enforce a unique HOD/submitter pair and use cascading deletes for user removal. No existing production tables are modified by these migrations beyond creating the new tables.

## Audit Logging

Changes to a HOD user's exclusions are recorded through the existing audit log flow with the action:

```text
user_hod_exclusions_updated
```

The audit entry stores the before/after lists of notification-excluded, approval-excluded, and visibility-excluded user IDs.

## Test Coverage

The feature is covered by `tests/Feature/HodExclusionWorkflowTest.php`.

Covered scenarios include:

- Email exclusion suppresses only HOD email.
- Email-excluded HOD can still approve.
- Approval-excluded HOD does not receive approval-request email.
- Approval-excluded HOD cannot approve or reject by direct request.
- Visibility-excluded HOD cannot see or action selected employee records.
- HOD leave-entitlement pages respect visibility exclusions.
- Leave-plan approval and cancellation-review paths respect exclusions.
- Super Admin cannot save an approval or visibility exclusion that leaves zero eligible HOD approvers.
- Role changes, department moves, and HOD assignment changes remove invalid exclusions.
