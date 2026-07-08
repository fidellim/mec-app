<?php

namespace App\Http\Controllers;

use App\Models\AnnualLeaveCarryOver;
use App\Models\Department;
use App\Models\User;
use App\Services\AnnualLeaveCarryOverService;
use App\Services\AuditLogService;
use App\Services\LeaveEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAnnualLeaveCarryOverController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                AnnualLeaveCarryOver::STATUS_PENDING,
                AnnualLeaveCarryOver::STATUS_APPROVED,
                AnnualLeaveCarryOver::STATUS_REJECTED,
                AnnualLeaveCarryOver::STATUS_VOIDED,
            ])],
            'source' => ['nullable', Rule::in([
                AnnualLeaveCarryOver::SOURCE_MANUAL_OPENING_BALANCE,
                AnnualLeaveCarryOver::SOURCE_YEAR_END_GENERATED,
                AnnualLeaveCarryOver::SOURCE_MANUAL_ADJUSTMENT,
            ])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->whereIn('role', ['employee', 'hod'])
                ->where('is_active', true)
            )],
            'from_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'to_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $carryOvers = AnnualLeaveCarryOver::query()
            ->with(['user.department', 'creator', 'approver', 'rejector', 'voider'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('user_id', $employeeId))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->whereHas('user', fn ($userQuery) => $userQuery->where('department_id', $departmentId)))
            ->when($filters['from_year'] ?? null, fn ($query, $year) => $query->where('from_year', $year))
            ->when($filters['to_year'] ?? null, fn ($query, $year) => $query->where('to_year', $year))
            ->orderByDesc('to_year')
            ->orderBy('status')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.annual-leave-carry-overs.index', [
            'carryOvers' => $carryOvers,
            'departments' => Department::orderBy('name')->get(),
            'employees' => $this->eligibleEmployeeOptions(),
            'filters' => $filters,
            'statuses' => [
                AnnualLeaveCarryOver::STATUS_PENDING => 'Pending',
                AnnualLeaveCarryOver::STATUS_APPROVED => 'Approved',
                AnnualLeaveCarryOver::STATUS_REJECTED => 'Rejected',
                AnnualLeaveCarryOver::STATUS_VOIDED => 'Voided',
            ],
            'sources' => [
                AnnualLeaveCarryOver::SOURCE_MANUAL_OPENING_BALANCE => 'Manual opening balance',
                AnnualLeaveCarryOver::SOURCE_YEAR_END_GENERATED => 'Year-end generated',
                AnnualLeaveCarryOver::SOURCE_MANUAL_ADJUSTMENT => 'Manual adjustment',
            ],
        ]);
    }

    public function store(Request $request, AnnualLeaveCarryOverService $carryOverService, AuditLogService $audit)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->whereIn('role', ['employee', 'hod'])
                ->where('is_active', true)
            )],
            'from_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'to_year' => ['required', 'integer', 'min:2000', 'max:2100', 'gt:from_year'],
            'approved_days' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'source' => ['required', Rule::in([
                AnnualLeaveCarryOver::SOURCE_MANUAL_OPENING_BALANCE,
                AnnualLeaveCarryOver::SOURCE_MANUAL_ADJUSTMENT,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::findOrFail($data['user_id']);
        abort_unless(app(LeaveEntitlementService::class)->userIsEligibleFor($user, LeaveEntitlementService::ANNUAL_LEAVE_CODE), 422);

        $carryOver = AnnualLeaveCarryOver::updateOrCreate(
            [
                'user_id' => $user->id,
                'from_year' => $data['from_year'],
                'to_year' => $data['to_year'],
                'attendance_code' => LeaveEntitlementService::ANNUAL_LEAVE_CODE,
            ],
            [
                'suggested_days' => $data['approved_days'],
                'approved_days' => $data['approved_days'],
                'status' => AnnualLeaveCarryOver::STATUS_APPROVED,
                'source' => $data['source'],
                'notes' => $data['notes'] ?? null,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'voided_by' => null,
                'voided_at' => null,
                'void_reason' => null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ],
        )->fresh('user.department');

        $audit->record('annual_leave_carry_over_saved', $carryOver, null, $carryOver->toArray());

        return redirect()
            ->route('admin.annual-leave-carry-overs.index', ['to_year' => $data['to_year']])
            ->with('success', 'Annual leave carry-over saved.');
    }

    public function generate(Request $request, AnnualLeaveCarryOverService $carryOverService, AuditLogService $audit)
    {
        $data = $request->validate([
            'from_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $generated = $carryOverService->generatePendingForYear((int) $data['from_year'], $request->user());

        $audit->record('annual_leave_carry_over_generated', null, null, [
            'from_year' => (int) $data['from_year'],
            'to_year' => (int) $data['from_year'] + 1,
            'count' => $generated->count(),
        ]);

        return redirect()
            ->route('admin.annual-leave-carry-overs.index', [
                'from_year' => (int) $data['from_year'],
                'to_year' => (int) $data['from_year'] + 1,
                'status' => AnnualLeaveCarryOver::STATUS_PENDING,
            ])
            ->with('success', "Generated {$generated->count()} pending annual leave carry-over suggestion(s).");
    }

    public function approve(Request $request, AnnualLeaveCarryOver $carryOver, AnnualLeaveCarryOverService $carryOverService, AuditLogService $audit)
    {
        $data = $request->validate([
            'approved_days' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($carryOver->status === AnnualLeaveCarryOver::STATUS_PENDING, 403);

        $old = $carryOver->toArray();
        $carryOver = $carryOverService->approve($carryOver, (float) $data['approved_days'], $request->user(), $data['notes'] ?? null);

        $audit->record('annual_leave_carry_over_approved', $carryOver, $old, $carryOver->toArray());

        return back()->with('success', 'Annual leave carry-over approved.');
    }

    public function reject(Request $request, AnnualLeaveCarryOver $carryOver, AnnualLeaveCarryOverService $carryOverService, AuditLogService $audit)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($carryOver->status === AnnualLeaveCarryOver::STATUS_PENDING, 403);

        $old = $carryOver->toArray();
        $carryOver = $carryOverService->reject($carryOver, $request->user(), $data['notes'] ?? null);

        $audit->record('annual_leave_carry_over_rejected', $carryOver, $old, $carryOver->toArray());

        return back()->with('success', 'Annual leave carry-over rejected.');
    }

    public function void(Request $request, AnnualLeaveCarryOver $carryOver, AnnualLeaveCarryOverService $carryOverService, AuditLogService $audit)
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless($carryOver->status === AnnualLeaveCarryOver::STATUS_APPROVED, 403);

        $old = $carryOver->toArray();
        $carryOver = $carryOverService->void($carryOver, trim($data['void_reason']), $request->user());

        $audit->record('annual_leave_carry_over_voided', $carryOver, $old, $carryOver->toArray());

        return back()->with('success', 'Annual leave carry-over voided.');
    }

    public function import(Request $request, AuditLogService $audit)
    {
        $request->validate([
            'carry_over_csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('carry_over_csv')->getRealPath();
        $expected = ['employee_code', 'from_year', 'to_year', 'approved_days', 'notes'];
        $errors = [];
        $created = 0;
        $rows = [];
        $handle = null;

        try {
            $handle = $path ? fopen($path, 'r') : false;
            $header = $handle ? fgetcsv($handle) : false;

            if ($header !== $expected) {
                return back()->withErrors(['carry_over_csv' => 'The CSV header must be employee_code,from_year,to_year,approved_days,notes.']);
            }

            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $rows[] = array_combine($expected, array_pad($row, count($expected), '')) + ['line' => $line];
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if ($path && is_file($path)) {
                @unlink($path);
            }
        }

        foreach ($rows as $row) {
            $user = User::where('employee_code', trim((string) $row['employee_code']))
                ->whereIn('role', ['employee', 'hod'])
                ->where('is_active', true)
                ->first();
            $fromYear = filter_var($row['from_year'], FILTER_VALIDATE_INT);
            $toYear = filter_var($row['to_year'], FILTER_VALIDATE_INT);
            $days = is_numeric($row['approved_days']) ? (float) $row['approved_days'] : null;

            if (! $user) {
                $errors[] = "Line {$row['line']}: employee_code was not found for an active Employee/HOD.";
            } elseif (! app(LeaveEntitlementService::class)->userIsEligibleFor($user, LeaveEntitlementService::ANNUAL_LEAVE_CODE)) {
                $errors[] = "Line {$row['line']}: employee is not eligible for annual leave.";
            }
            if (! $fromYear || $fromYear < 2000 || $fromYear > 2100) {
                $errors[] = "Line {$row['line']}: from_year must be between 2000 and 2100.";
            }
            if (! $toYear || $toYear < 2000 || $toYear > 2100 || $toYear <= $fromYear) {
                $errors[] = "Line {$row['line']}: to_year must be after from_year and between 2000 and 2100.";
            }
            if ($days === null || $days < 0.5 || fmod($days * 10, 5.0) !== 0.0) {
                $errors[] = "Line {$row['line']}: approved_days must be at least 0.5 and use 0.5-day increments.";
            }
        }

        if ($errors !== []) {
            return back()->withErrors(['carry_over_csv' => implode(' ', $errors)]);
        }

        foreach ($rows as $row) {
            $user = User::where('employee_code', trim((string) $row['employee_code']))->firstOrFail();
            $carryOver = AnnualLeaveCarryOver::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'from_year' => (int) $row['from_year'],
                    'to_year' => (int) $row['to_year'],
                    'attendance_code' => LeaveEntitlementService::ANNUAL_LEAVE_CODE,
                ],
                [
                    'suggested_days' => (float) $row['approved_days'],
                    'approved_days' => (float) $row['approved_days'],
                    'status' => AnnualLeaveCarryOver::STATUS_APPROVED,
                    'source' => AnnualLeaveCarryOver::SOURCE_MANUAL_OPENING_BALANCE,
                    'notes' => trim((string) $row['notes']) ?: null,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'voided_by' => null,
                    'voided_at' => null,
                    'void_reason' => null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ],
            );
            $audit->record('annual_leave_carry_over_imported', $carryOver, null, $carryOver->toArray());
            $created++;
        }

        return redirect()
            ->route('admin.annual-leave-carry-overs.index')
            ->with('success', "Imported {$created} approved annual leave carry-over record(s).");
    }

    private function eligibleEmployeeOptions()
    {
        return User::whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);
    }
}
