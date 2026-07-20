# Job Level Manhour Allocations

Job Level controls protect a project department's manhour budget without storing employee rates or compensation.

## Job Levels

Authorized administrators assign one Job Level on each user profile:

- Entry
- Junior
- Intermediate
- Senior
- Lead/Principal
- Management

Existing users may remain unclassified during rollout. New active employees and Heads of Department require a Job Level. Once a project department enables Job Level controls, an unclassified user cannot submit time to it.

Submitted entries store the Job Level used for allocation accounting. Changing a user's profile affects future submissions only. A correction retains the original entry's Job Level when the original row remains identifiable.

## Allocation states

Each Job Level has one state within a controlled project department:

- **Shared**: consumes the shared remainder with every other Shared level.
- **Reserved**: consumes only hours protected for that Job Level.
- **Not allowed**: cannot charge to the project department.

The shared remainder is the department allocation minus all positive reservations. Reserved hours cannot be borrowed implicitly. If no Job Level is Shared, positive reservations must equal the department allocation exactly. Zero-hour levels remain Not allowed.

Projects without any department allocations remain compatible with the legacy unrestricted workflow. Job Level enforcement begins only when a project has department budgets and a department enables the controls.

## Consumption and submission

- Draft hours do not consume allocation.
- Submitted and approved hours consume allocation.
- Rejected, withdrawn, recalled, and voided hours release allocation.
- Submission is atomic: if one row exceeds a department or Job Level allocation, the entire timesheet remains unchanged.
- The employee sees only: “The allocated hours for this department have been exceeded. Please contact the project administrator.”

Validation and persistence execute in one database transaction. The department allocation rows are locked before usage is checked so concurrent submissions cannot reserve the same remaining hours.

## Changing allocations

Every allocation edit requires a reason and creates a `project_allocations_updated` audit event.

- A department total cannot fall below submitted and approved consumption.
- A reservation cannot fall below submitted and approved reserved consumption.
- A reservation with consumed hours cannot be changed to Shared, changed to Not allowed, or removed.
- The shared remainder cannot fall below submitted and approved shared consumption.
- Reducing the department total must preserve every configured reservation plus consumed shared hours.

To release unused reserved hours, reduce the reservation to no less than its consumed amount. The released amount becomes part of the shared remainder when at least one level is Shared.

## Operational rollout

1. Assign Job Levels to active timesheet users.
2. Open a project and review each department's total allocation.
3. Enable **Control by Job Level** only where needed.
4. Set each level to Shared, Reserved, or Not allowed.
5. Review the project utilization ledger for pending, approved, and Job Level consumption.
6. Update allocations before asking a blocked employee to resubmit.

Actual rates, salaries, and compensation are never stored or shown by this feature.
