<?php

namespace App\Http\Controllers;

use App\Exports\LeavePlansExport;
use App\Http\Controllers\Concerns\GuardsExports;
use App\Http\Requests\AdminApprovedLeaveRequest;
use App\Models\Department;
use App\Models\LeavePlan;
use App\Models\User;
use App\Services\AdminApprovedLeaveService;
use App\Services\LeaveEntitlementService;
use App\Services\LeavePlanCalendarService;
use App\Services\LeavePlanReviewCalendarService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class AdminLeavePlanController extends Controller
{
    use GuardsExports;

    private const IMPORT_SESSION_KEY = 'admin_approved_leave_import_preview';

    public function index(Request $request)
    {
        if ($request->boolean('employee_lookup')) {
            return response()->json($this->employeeLookup($request));
        }

        $filters = $this->validatedFilters($request);

        $selectedEmployeeIds = $this->selectedEmployeeIds($filters);

        $leavePlans = LeavePlan::with(['user', 'department'])
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.leave-plans.index', [
            'leavePlans' => $leavePlans,
            'departments' => Department::orderBy('name')->get(),
            'attendanceCodes' => $this->leaveAttendanceCodes(),
            'selectedEmployees' => $this->selectedEmployees($selectedEmployeeIds),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $fileName = 'leave_plans_'.now()->format('Ymd_His').'.xlsx';

        return $this->guardedExport(fn () => Excel::download(
            new LeavePlansExport($this->filteredQuery($filters)),
            $fileName,
            ExcelWriter::XLSX
        ));
    }

    public function calendar(Request $request, LeavePlanCalendarService $calendar)
    {
        $calendarData = $calendar->build(
            request: $request,
            query: LeavePlan::query(),
            showRoute: 'admin.leave-plans.show',
            showEmployee: true,
        ) + [
            'departments' => Department::orderBy('name')->get(),
            'employees' => User::whereIn('role', ['employee', 'hod'])->where('is_active', true)->orderBy('name')->get(),
            'attendanceCodes' => $this->leaveAttendanceCodes(),
        ];

        if ($request->query('calendar_fragment') === 'calendar') {
            return view('shared.leave_plan_calendar', $calendarData);
        }

        return view('admin.leave-plans.calendar', $calendarData);
    }

    public function create()
    {
        session()->forget(self::IMPORT_SESSION_KEY);

        return view('admin.leave-plans.create', [
            'employees' => $this->eligibleEmployees(),
            'attendanceCodes' => $this->leaveAttendanceCodes(),
            'bereavementRelationships' => LeavePlan::bereavementRelationshipOptions(),
        ]);
    }

    public function store(AdminApprovedLeaveRequest $request, AdminApprovedLeaveService $approvedLeaves)
    {
        $result = $approvedLeaves->validateSingleEntry($request->validated());

        if (! $result['valid']) {
            return back()
                ->withInput()
                ->withErrors(['admin_approved_leave' => implode(' ', $result['errors'])]);
        }

        $leavePlan = $approvedLeaves->createApprovedLeave($result['attributes'], $result['employee']);

        return redirect()
            ->route('admin.leave-plans.show', $leavePlan)
            ->with('success', 'Approved leave added for '.$leavePlan->user->name.'.');
    }

    public function import(AdminApprovedLeaveService $approvedLeaves)
    {
        return view('admin.leave-plans.import', [
            'headers' => $approvedLeaves->csvHeaders(),
            'previewRows' => session(self::IMPORT_SESSION_KEY.'.previewRows', []),
            'rawRows' => session(self::IMPORT_SESSION_KEY.'.rawRows', []),
            'uploadErrors' => session(self::IMPORT_SESSION_KEY.'.uploadErrors', []),
        ]);
    }

    public function previewImport(Request $request, AdminApprovedLeaveService $approvedLeaves)
    {
        session()->forget(self::IMPORT_SESSION_KEY);

        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'csv_file.required' => 'Upload a CSV file before previewing.',
            'csv_file.mimes' => 'Upload a valid CSV file.',
        ]);

        $uploadedFile = $validated['csv_file'];
        $path = $uploadedFile->getRealPath();

        try {
            $parsed = $path ? $approvedLeaves->normalizeCsvRows($path) : [
                'rows' => [],
                'errors' => ['Unable to read the uploaded CSV file.'],
            ];
        } finally {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }

        if ($parsed['errors']) {
            session()->put(self::IMPORT_SESSION_KEY, [
                'previewRows' => [],
                'rawRows' => [],
                'uploadErrors' => $parsed['errors'],
            ]);

            return redirect()
                ->route('admin.leave-plans.import')
                ->with('warning', 'CSV preview could not be prepared. Please fix the file and upload it again.');
        }

        $previewRows = $approvedLeaves->previewRows($parsed['rows']);
        session()->put(self::IMPORT_SESSION_KEY, [
            'previewRows' => $this->serializablePreviewRows($previewRows),
            'rawRows' => $parsed['rows'],
            'uploadErrors' => [],
        ]);

        $invalidCount = collect($previewRows)->where('valid', false)->count();

        return redirect()
            ->route('admin.leave-plans.import')
            ->with($invalidCount > 0 ? 'warning' : 'success', $invalidCount > 0
                ? "CSV preview found {$invalidCount} row(s) that must be fixed before import."
                : 'CSV preview is valid. Review the rows and import when ready.');
    }

    public function storeImport(Request $request, AdminApprovedLeaveService $approvedLeaves)
    {
        $rawRows = session(self::IMPORT_SESSION_KEY.'.rawRows', []);

        if (empty($rawRows)) {
            return redirect()
                ->route('admin.leave-plans.import')
                ->with('warning', 'Upload and preview a CSV before importing.');
        }

        $previewRows = $approvedLeaves->previewRows($rawRows);
        $invalidCount = collect($previewRows)->where('valid', false)->count();

        if ($invalidCount > 0) {
            session()->put(self::IMPORT_SESSION_KEY, [
                'previewRows' => $this->serializablePreviewRows($previewRows),
                'rawRows' => $rawRows,
                'uploadErrors' => [],
            ]);

            return redirect()
                ->route('admin.leave-plans.import')
                ->with('warning', "CSV import was blocked because {$invalidCount} row(s) are invalid.");
        }

        try {
            $created = $approvedLeaves->importApprovedLeaves($rawRows);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('admin.leave-plans.import')
                ->with('warning', $exception->getMessage());
        }

        session()->forget(self::IMPORT_SESSION_KEY);

        return redirect()
            ->route('admin.leave-plans.index')
            ->with('success', 'Imported '.count($created).' approved leave '.(count($created) === 1 ? 'record' : 'records').'.');
    }

    public function leaveEntitlements(Request $request, LeaveEntitlementService $entitlements)
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->whereIn('role', ['employee', 'hod'])
                ->where('is_active', true)
            )],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) ($filters['year'] ?? now()->year);

        $employees = User::with('department')
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->whereKey($employeeId))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $employees->getCollection()->transform(function (User $employee) use ($entitlements, $year) {
            $employee->leaveBalances = $entitlements->visibleBalancesFor($employee, $year, viewer: auth()->user());

            return $employee;
        });

        return view('admin.leave-entitlements.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'filterEmployees' => User::whereIn('role', ['employee', 'hod'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code']),
            'selectedDepartmentId' => $filters['department_id'] ?? null,
            'selectedEmployee' => isset($filters['employee_id'])
                ? User::whereIn('role', ['employee', 'hod'])->where('is_active', true)->whereKey($filters['employee_id'])->first(['id', 'name', 'employee_code'])
                : null,
            'year' => $year,
        ]);
    }

    public function show(LeavePlan $leavePlan, LeavePlanReviewCalendarService $reviewCalendar)
    {
        $leavePlan->load(['user', 'department', 'approver', 'rejector', 'hodApprover', 'directorApprover', 'hrApprover', 'canceller', 'recaller', 'voider']);

        return view('hod.leave-plans.show', [
            'leavePlan' => $leavePlan,
            'reviewCalendarMonths' => $reviewCalendar->build($leavePlan, LeavePlan::query()),
        ]);
    }

    public function history(LeavePlan $leavePlan)
    {
        return view('shared.leave_plan_history_timeline', [
            'leavePlan' => $leavePlan->load('statusHistories.user'),
        ]);
    }

    private function leaveAttendanceCodes(): array
    {
        return collect(config('timesheet.attendance_codes'))
            ->only(config('timesheet.leave_attendance_codes', []))
            ->all();
    }

    private function eligibleEmployees()
    {
        return User::query()
            ->with('department')
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'department_id']);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'date_from' => ['nullable', 'date', 'required_with:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in([
                LeavePlan::STATUS_SUBMITTED,
                LeavePlan::STATUS_APPROVED,
                LeavePlan::STATUS_REJECTED,
                LeavePlan::STATUS_CANCELLATION_REQUESTED,
                LeavePlan::STATUS_RECALLED,
                LeavePlan::STATUS_CANCELLED,
                LeavePlan::STATUS_VOIDED,
                LeavePlan::STATUS_DRAFT,
            ])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'attendance_code' => ['nullable', Rule::in(array_keys($this->leaveAttendanceCodes()))],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ], [
            'date_from.required_with' => 'Enter From Date when using To Date.',
            'date_to.after_or_equal' => 'To Date must be greater than or equal to From Date.',
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        return LeavePlan::query()
            ->with([
                'user',
                'department',
                'approver',
                'rejector',
                'hodApprover',
                'directorApprover',
                'hrApprover',
                'canceller',
                'recaller',
                'voider',
            ])
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->latest();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        [$rangeStart, $rangeEnd] = $this->dateRange($filters);
        $selectedEmployeeIds = $this->selectedEmployeeIds($filters);

        $query
            ->when($rangeStart && $rangeEnd, fn (Builder $q) => $q
                ->whereDate('start_date', '<=', $rangeEnd)
                ->whereDate('end_date', '>=', $rangeStart))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['department_id'] ?? null, fn (Builder $q, int|string $departmentId) => $q->where('department_id', $departmentId))
            ->when($filters['attendance_code'] ?? null, fn (Builder $q, string $attendanceCode) => $q->where('attendance_code', $attendanceCode))
            ->when($selectedEmployeeIds->isNotEmpty(), fn (Builder $q) => $q->whereIn('user_id', $selectedEmployeeIds));
    }

    private function dateRange(array $filters): array
    {
        $start = $filters['date_from'] ?? null;
        $end = $filters['date_to'] ?? null;

        if (! $start && ! $end && ($filters['year'] ?? null)) {
            $start = ((int) $filters['year']).'-01-01';
            $end = ((int) $filters['year']).'-12-31';
        }

        if ($start && ! $end) {
            $end = $start;
        }

        if (! $start || ! $end) {
            return [null, null];
        }

        return [
            Carbon::parse($start)->toDateString(),
            Carbon::parse($end)->toDateString(),
        ];
    }

    private function selectedEmployeeIds(array $filters)
    {
        return collect($filters['employee_ids'] ?? [])
            ->when($filters['employee_id'] ?? null, fn ($ids, $employeeId) => $ids->push($employeeId))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    private function selectedEmployees($selectedEmployeeIds)
    {
        if ($selectedEmployeeIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $selectedEmployeeIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);
    }

    private function employeeLookup(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $users = User::query()
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['id', 'name', 'employee_code'])
            ->values();

        return [
            'results' => $users->take($perPage)->map(fn (User $user) => [
                'value' => (string) $user->id,
                'text' => $user->name,
            ])->all(),
            'has_more' => $users->count() > $perPage,
            'page' => $page,
        ];
    }

    private function serializablePreviewRows(array $previewRows): array
    {
        return collect($previewRows)->map(fn (array $previewRow) => [
            'row_number' => $previewRow['row_number'],
            'attributes' => $previewRow['attributes'],
            'employee_name' => $previewRow['employee_name'],
            'errors' => $previewRow['errors'],
            'valid' => $previewRow['valid'],
        ])->all();
    }
}
