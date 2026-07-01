<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LeavePlan;
use App\Models\User;
use App\Services\LeavePlanCalendarService;
use App\Services\LeavePlanReviewCalendarService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLeavePlanController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('employee_lookup')) {
            return response()->json($this->employeeLookup($request));
        }

        $filters = $request->validate([
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
        ]);

        $selectedEmployeeIds = collect($filters['employee_ids'] ?? [])
            ->when($filters['employee_id'] ?? null, fn ($ids, $employeeId) => $ids->push($employeeId))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $leavePlans = LeavePlan::with(['user', 'department'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['department_id'] ?? null, fn ($q, $departmentId) => $q->where('department_id', $departmentId))
            ->when($filters['attendance_code'] ?? null, fn ($q, $attendanceCode) => $q->where('attendance_code', $attendanceCode))
            ->when($selectedEmployeeIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $selectedEmployeeIds))
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

    public function calendar(Request $request, LeavePlanCalendarService $calendar)
    {
        return view('admin.leave-plans.calendar', $calendar->build(
            request: $request,
            query: LeavePlan::query(),
            showRoute: 'admin.leave-plans.show',
            showEmployee: true,
        ) + [
            'departments' => Department::orderBy('name')->get(),
            'employees' => User::whereIn('role', ['employee', 'hod'])->where('is_active', true)->orderBy('name')->get(),
            'attendanceCodes' => $this->leaveAttendanceCodes(),
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
}
