<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LeavePlan;
use App\Models\User;
use App\Services\LeavePlanCalendarService;
use App\Services\LeavePlanReviewCalendarService;
use Illuminate\Http\Request;

class AdminLeavePlanController extends Controller
{
    public function index()
    {
        $leavePlans = LeavePlan::with(['user', 'department'])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('department_id'), fn ($q, $departmentId) => $q->where('department_id', $departmentId))
            ->when(request('employee_id'), fn ($q, $employeeId) => $q->where('user_id', $employeeId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.leave-plans.index', [
            'leavePlans' => $leavePlans,
            'departments' => Department::orderBy('name')->get(),
            'employees' => User::whereIn('role', ['employee', 'hod'])->where('is_active', true)->orderBy('name')->get(),
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

    private function leaveAttendanceCodes(): array
    {
        return collect(config('timesheet.attendance_codes'))
            ->only(config('timesheet.leave_attendance_codes', []))
            ->all();
    }
}
