<?php

namespace App\Services;

use App\Models\LeavePlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminApprovedLeaveService
{
    public const HISTORY_ACTION = 'leave_plan_admin_created_approved';

    public const CSV_HEADERS = [
        'employee_code',
        'attendance_code',
        'start_date',
        'end_date',
        'duration_type',
        'half_day_period',
        'bereavement_relationship',
        'approved_at',
        'reason',
        'policy_exception_reason',
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly LeaveEntitlementService $entitlements,
        private readonly LeavePlanStatusHistoryService $history,
    ) {
    }

    public function csvHeaders(): array
    {
        return self::CSV_HEADERS;
    }

    public function normalizeCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [
                'rows' => [],
                'errors' => ['Unable to read the uploaded CSV file.'],
            ];
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                return [
                    'rows' => [],
                    'errors' => ['The CSV file is empty.'],
                ];
            }

            $headers = array_map(fn ($header) => trim((string) $header), $headers);

            if ($headers !== self::CSV_HEADERS) {
                return [
                    'rows' => [],
                    'errors' => ['The CSV headers must match the template exactly and stay in the documented order.'],
                ];
            }

            $rows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->csvRowIsBlank($values)) {
                    continue;
                }

                $values = array_pad($values, count(self::CSV_HEADERS), '');
                $values = array_slice($values, 0, count(self::CSV_HEADERS));
                $row = array_combine(self::CSV_HEADERS, array_map(fn ($value) => trim((string) $value), $values));
                $row['row_number'] = $lineNumber;
                $rows[] = $row;
            }

            if (empty($rows)) {
                return [
                    'rows' => [],
                    'errors' => ['The CSV file does not contain any leave rows.'],
                ];
            }

            return [
                'rows' => $rows,
                'errors' => [],
            ];
        } finally {
            fclose($handle);
        }
    }

    public function previewRows(array $rows): array
    {
        $pending = collect();

        return collect($rows)
            ->map(function (array $row) use ($pending) {
                $result = $this->validateRow($row, $pending);

                if ($result['valid']) {
                    $pending->push($this->makeUnsavedLeavePlan($result['attributes'], $result['employee']));
                }

                return $result;
            })
            ->all();
    }

    public function validateSingleEntry(array $data): array
    {
        $employee = User::query()
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->find($data['employee_id'] ?? null);

        $row = [
            'employee_code' => $employee?->employee_code,
            'attendance_code' => $data['attendance_code'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'duration_type' => $data['duration_type'] ?? null,
            'half_day_period' => $data['half_day_period'] ?? null,
            'bereavement_relationship' => $data['bereavement_relationship'] ?? null,
            'approved_at' => $data['approved_at'] ?? null,
            'reason' => $data['reason'] ?? null,
            'policy_exception_reason' => $data['policy_exception_reason'] ?? null,
        ];

        $result = $this->validateRow($row);

        if (! $employee) {
            $result['errors'][] = 'Select an active employee or HOD.';
            $result['valid'] = false;
        } elseif (($result['employee']?->id ?? null) !== $employee->id) {
            $result['errors'][] = 'The selected employee could not be matched to a valid employee number.';
            $result['valid'] = false;
        }

        return $result;
    }

    public function createApprovedLeave(array $attributes, User $employee): LeavePlan
    {
        return DB::transaction(function () use ($attributes, $employee) {
            $approvedAt = Carbon::parse($attributes['approved_at']);

            $leavePlan = LeavePlan::create([
                'user_id' => $employee->id,
                'department_id' => $employee->department_id,
                'attendance_code' => $attributes['attendance_code'],
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'],
                'duration_type' => $attributes['duration_type'],
                'half_day_period' => $attributes['duration_type'] === 'half_day' ? $attributes['half_day_period'] : null,
                'bereavement_relationship' => $attributes['bereavement_relationship'] ?: null,
                'reason' => $attributes['reason'] ?: null,
                'policy_exception_reason' => $attributes['policy_exception_reason'] ?: null,
                'status' => LeavePlan::STATUS_APPROVED,
                'approval_stage' => null,
                'submitted_at' => $approvedAt,
                'approved_at' => $approvedAt,
                'approved_by' => auth()->id(),
            ]);

            $new = $leavePlan->fresh()->toArray();
            $this->audit->record(self::HISTORY_ACTION, $leavePlan, null, $new);
            $this->history->record(self::HISTORY_ACTION, $leavePlan, null, $new);

            return $leavePlan;
        });
    }

    public function importApprovedLeaves(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            $created = [];

            foreach ($rows as $row) {
                $result = $this->validateRow($row);

                if (! $result['valid']) {
                    throw new \RuntimeException('Row '.$row['row_number'].' is no longer valid: '.implode(' ', $result['errors']));
                }

                $created[] = $this->createApprovedLeave($result['attributes'], $result['employee']);
            }

            return $created;
        });
    }

    private function validateRow(array $row, ?Collection $pendingLeavePlans = null): array
    {
        $normalized = $this->normalizeRow($row);
        $errors = [];
        $policyErrors = [];

        $validator = Validator::make($normalized, [
            'employee_code' => ['required', 'string'],
            'attendance_code' => ['required', Rule::in(config('timesheet.leave_attendance_codes', []))],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration_type' => ['required', Rule::in(['full_day', 'half_day'])],
            'half_day_period' => ['nullable', 'required_if:duration_type,half_day', Rule::in(['morning', 'afternoon'])],
            'bereavement_relationship' => ['nullable', Rule::in(LeavePlan::BEREAVEMENT_RELATIONSHIPS)],
            'approved_at' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'policy_exception_reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'attendance_code.in' => 'Select a valid leave attendance code.',
            'half_day_period.required_if' => 'Select morning or afternoon for half-day leave.',
            'bereavement_relationship.in' => 'Select a valid bereavement relationship.',
            'approved_at.required' => 'Enter the original approval date.',
            'approved_at.date_format' => 'Approval date must use YYYY-MM-DD.',
        ]);

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        if (($normalized['duration_type'] ?? null) === 'half_day'
            && ($normalized['start_date'] ?? null)
            && ($normalized['end_date'] ?? null)
            && $normalized['start_date'] !== $normalized['end_date']) {
            $errors[] = 'Half-day leave must use the same start and end date.';
        }

        $employee = User::query()
            ->where('employee_code', $normalized['employee_code'] ?? null)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->first();

        if (! $employee) {
            $errors[] = 'Employee number must belong to an active employee or HOD.';
        } elseif (! $employee->department_id) {
            $errors[] = 'Employee must be assigned to a department before leave can be added.';
        }

        if ($errors || ! $employee) {
            return $this->rowResult($row, $normalized, $employee, $errors);
        }

        $startDate = Carbon::parse($normalized['start_date']);

        if (! $this->entitlements->userIsEligibleFor($employee, $normalized['attendance_code'], $startDate)) {
            $policyErrors[] = $this->entitlements->eligibilityMessage($normalized['attendance_code'], $employee, $startDate)
                ?? 'This leave type is not available for the employee profile.';
        }

        if ($normalized['attendance_code'] !== LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
            && $normalized['bereavement_relationship']) {
            $errors[] = 'Leave bereavement_relationship blank unless attendance_code is L180.';
        }

        if ($normalized['attendance_code'] !== LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE) {
            $normalized['bereavement_relationship'] = null;
        }

        if ($normalized['attendance_code'] === LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
            && $this->entitlements->regionFor($employee) === 'uae'
            && ! $normalized['bereavement_relationship']) {
            $errors[] = 'Select the bereavement relationship for this leave request.';
        }

        if ($normalized['attendance_code'] === LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
            && $this->entitlements->regionFor($employee) === 'uae'
            && ! $this->entitlements->userIsEligibleForBereavementRelationship($employee, $normalized['bereavement_relationship'])) {
            $errors[] = $this->entitlements->bereavementRelationshipEligibilityMessage($normalized['bereavement_relationship']);
        }

        $submissionAttributes = $this->submissionAttributes($normalized);

        foreach ($this->entitlements->submissionViolations($employee, $submissionAttributes) as $violation) {
            $policyErrors[] = $this->entitlements->violationMessage($violation);
        }

        foreach ($this->entitlements->bereavementSubmissionViolations($employee, $submissionAttributes) as $violation) {
            $errors[] = $this->entitlements->bereavementViolationMessage($violation);
        }

        if ($this->overlapsExistingLeave($employee, $normalized)) {
            $errors[] = 'This leave overlaps an existing active leave plan for the same employee.';
        }

        if ($pendingLeavePlans && $this->overlapsPendingLeave($employee, $normalized, $pendingLeavePlans)) {
            $errors[] = 'This leave overlaps another row in the uploaded CSV for the same employee.';
        }

        if ($policyErrors && $normalized['policy_exception_reason'] === '') {
            $errors = array_merge($errors, $policyErrors, [
                'Add a policy_exception_reason to confirm this leave was already approved as a discretionary exception outside normal policy.',
            ]);
        }

        return $this->rowResult($row, $normalized, $employee, $errors, $policyErrors);
    }

    private function rowResult(array $row, array $normalized, ?User $employee, array $errors, array $policyErrors = []): array
    {
        return [
            'row_number' => $row['row_number'] ?? null,
            'row' => $row,
            'attributes' => $normalized,
            'employee' => $employee,
            'employee_name' => $employee?->name,
            'errors' => $errors,
            'policy_errors' => $policyErrors,
            'policy_exception_applied' => empty($errors) && $policyErrors !== [] && $normalized['policy_exception_reason'] !== '',
            'valid' => empty($errors),
        ];
    }

    private function normalizeRow(array $row): array
    {
        return [
            'employee_code' => trim((string) ($row['employee_code'] ?? '')),
            'attendance_code' => trim((string) ($row['attendance_code'] ?? '')),
            'start_date' => trim((string) ($row['start_date'] ?? '')),
            'end_date' => trim((string) ($row['end_date'] ?? '')),
            'duration_type' => trim((string) ($row['duration_type'] ?? '')),
            'half_day_period' => trim((string) ($row['half_day_period'] ?? '')),
            'bereavement_relationship' => trim((string) ($row['bereavement_relationship'] ?? '')),
            'approved_at' => trim((string) ($row['approved_at'] ?? '')),
            'reason' => trim((string) ($row['reason'] ?? '')),
            'policy_exception_reason' => trim((string) ($row['policy_exception_reason'] ?? '')),
        ];
    }

    private function submissionAttributes(array $normalized): array
    {
        return [
            'attendance_code' => $normalized['attendance_code'],
            'start_date' => $normalized['start_date'],
            'end_date' => $normalized['end_date'],
            'duration_type' => $normalized['duration_type'],
            'half_day_period' => $normalized['duration_type'] === 'half_day' ? $normalized['half_day_period'] : null,
            'bereavement_relationship' => $normalized['bereavement_relationship'] ?: null,
            'policy_exception_reason' => $normalized['policy_exception_reason'] ?: null,
        ];
    }

    private function overlapsExistingLeave(User $employee, array $normalized): bool
    {
        $candidate = $this->makeUnsavedLeavePlan($normalized, $employee);
        $candidateDates = $this->entitlements->countedLeaveDatesForPlan($candidate);

        if ($candidateDates->isEmpty()) {
            return false;
        }

        return LeavePlan::query()
            ->where('user_id', $employee->id)
            ->whereIn('status', LeavePlan::ACTIVE_OVERLAP_STATUSES)
            ->whereDate('start_date', '<=', $normalized['end_date'])
            ->whereDate('end_date', '>=', $normalized['start_date'])
            ->get()
            ->contains(fn (LeavePlan $leavePlan) => $this->entitlements
                ->countedLeaveDatesForPlan($leavePlan)
                ->intersect($candidateDates)
                ->isNotEmpty());
    }

    private function overlapsPendingLeave(User $employee, array $normalized, Collection $pendingLeavePlans): bool
    {
        $candidate = $this->makeUnsavedLeavePlan($normalized, $employee);
        $candidateDates = $this->entitlements->countedLeaveDatesForPlan($candidate);

        if ($candidateDates->isEmpty()) {
            return false;
        }

        return $pendingLeavePlans
            ->filter(fn (LeavePlan $leavePlan) => (int) $leavePlan->user_id === (int) $employee->id)
            ->contains(fn (LeavePlan $leavePlan) => $this->entitlements
                ->countedLeaveDatesForPlan($leavePlan)
                ->intersect($candidateDates)
                ->isNotEmpty());
    }

    private function makeUnsavedLeavePlan(array $attributes, User $employee): LeavePlan
    {
        $leavePlan = new LeavePlan([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'attendance_code' => $attributes['attendance_code'],
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'duration_type' => $attributes['duration_type'],
            'half_day_period' => $attributes['duration_type'] === 'half_day' ? $attributes['half_day_period'] : null,
            'bereavement_relationship' => $attributes['bereavement_relationship'] ?: null,
            'status' => LeavePlan::STATUS_APPROVED,
        ]);
        $leavePlan->setRelation('user', $employee);

        return $leavePlan;
    }

    private function csvRowIsBlank(array $values): bool
    {
        return collect($values)->every(fn ($value) => trim((string) $value) === '');
    }
}
