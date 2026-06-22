<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use App\Services\MissingTimesheetReminderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminHodTimesheetController extends Controller
{
    public function index()
    {
        $filters = request()->validate([
            'status' => ['nullable', 'in:draft,submitted,approved,rejected,withdrawn,recalled,voided'],
            'hod_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'hod')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'week_number' => ['nullable', 'integer', 'between:1,53'],
            'year' => ['nullable', 'integer', 'between:2000,2100', 'required_with:week_number'],
        ], [
            'year.required_with' => 'Year is required when filtering by week.',
        ]);

        $timesheets = Timesheet::with(['user', 'department', 'period'])
            ->whereHas('user', fn ($query) => $query->where('role', 'hod'))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['hod_id'] ?? null, fn ($query, $hodId) => $query->where('user_id', $hodId))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['week_number'] ?? null, fn ($query, $weekNumber) => $query->whereHas('period', fn ($period) => $period->where('week_number', $weekNumber)))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereHas('period', fn ($period) => $period->where('year', $year)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.hod-timesheets.index', [
            'timesheets' => $timesheets,
            'hods' => $this->hods(),
            'departments' => Department::orderBy('name')->get(),
            'periods' => TimesheetPeriod::orderByDesc('year')->orderByDesc('week_number')->get(['week_number', 'year']),
        ]);
    }

    public function tracker(MissingTimesheetReminderService $reminders)
    {
        $filters = request()->validate([
            'period_id' => ['nullable', Rule::exists('timesheet_periods', 'id')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
        ]);

        $periods = TimesheetPeriod::orderByDesc('year')
            ->orderByDesc('week_number')
            ->get();

        $period = ! empty($filters['period_id'])
            ? $periods->firstWhere('id', (int) $filters['period_id'])
            : TimesheetPeriod::where('status', 'open')->latest('start_date')->first();

        $selectedDepartmentId = ! empty($filters['department_id']) ? (int) $filters['department_id'] : null;

        $hods = User::with([
            'department',
            'timesheets' => fn ($query) => $period ? $query->where('timesheet_period_id', $period->id) : $query,
        ])
            ->where('role', 'hod')
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->when($selectedDepartmentId, fn ($query) => $query->where('department_id', $selectedDepartmentId))
            ->orderBy('name')
            ->get();

        $reminderCooldowns = $period
            ? $hods->mapWithKeys(fn (User $hod) => [
                $hod->id => $reminders->reminderCooldownLabel($hod, $period),
            ])
            : collect();

        return view('admin.hod-timesheets.tracker', [
            'hods' => $hods,
            'period' => $period,
            'periods' => $periods,
            'departments' => Department::orderBy('name')->get(),
            'selectedDepartmentId' => $selectedDepartmentId,
            'reminderCooldowns' => $reminderCooldowns,
        ]);
    }

    public function remindMissing(Request $request, MissingTimesheetReminderService $reminders)
    {
        $validated = $request->validate([
            'period_id' => ['required', Rule::exists('timesheet_periods', 'id')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'hod_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'hod')],
        ]);

        $period = TimesheetPeriod::findOrFail($validated['period_id']);
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $hodIds = null;

        if (! empty($validated['hod_id'])) {
            $hod = User::where('role', 'hod')
                ->where('is_active', true)
                ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
                ->findOrFail($validated['hod_id']);
            $hodIds = [$hod->id];
        }

        $result = $reminders->sendForPeriodDetailed(
            period: $period,
            departmentId: $departmentId,
            source: 'manual_admin',
            employeeIds: $hodIds,
            roles: ['hod'],
        );

        $sent = $result['sent'];
        $skippedCooldown = $result['skipped_cooldown'];

        if ($sent === 0 && $skippedCooldown > 0) {
            return back()->with('warning', 'No reminder was sent. The selected HOD(s) were already reminded recently.');
        }

        return back()->with(
            $sent > 0 ? 'success' : 'warning',
            $sent > 0
                ? "Sent {$sent} missing HOD timesheet reminder(s)."
                : 'No missing HOD timesheet reminders were sent. The selected HOD(s) may already be submitted or approved.'
        );
    }

    private function hods()
    {
        return User::where('role', 'hod')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

}
