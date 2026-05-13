<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $period = TimesheetPeriod::where('status', 'open')->latest('start_date')->first();

        return match ($user->role) {
            'super_admin' => view('dashboards.super_admin', [
                'totalUsers' => User::count(),
                'activeDepartments' => Department::count(),
                'activeProjects' => Project::where('is_active', true)->count(),
                'period' => $period,
                'summary' => $this->summary($period),
            ]),
            'admin' => view('dashboards.admin', [
                'period' => $period,
                'summary' => $this->summary($period),
                'missing' => $this->missingCount($period),
                'departments' => Department::withCount(['timesheets' => fn ($q) => $period ? $q->where('timesheet_period_id', $period->id) : $q])->get(),
            ]),
            'hod' => view('dashboards.hod', [
                'period' => $period,
                'pending' => $this->departmentTimesheets($period)->where('status', 'submitted')->count(),
                'approved' => $this->departmentTimesheets($period)->where('status', 'approved')->count(),
                'rejected' => $this->departmentTimesheets($period)->where('status', 'rejected')->count(),
                'missing' => $this->departmentMissing($period)->count(),
            ]),
            default => view('dashboards.employee', [
                'period' => $period,
                'current' => $period ? Timesheet::with('period')->where('user_id', $user->id)->where('timesheet_period_id', $period->id)->first() : null,
                'drafts' => Timesheet::with('period')->where('user_id', $user->id)->where('status', 'draft')->latest()->get(),
                'rejected' => Timesheet::with('period')->where('user_id', $user->id)->where('status', 'rejected')->latest()->get(),
                'recent' => Timesheet::with('period')->where('user_id', $user->id)->latest()->limit(5)->get(),
            ]),
        };
    }

    private function summary(?TimesheetPeriod $period): array
    {
        $query = Timesheet::query()->when($period, fn ($q) => $q->where('timesheet_period_id', $period->id));

        return [
            'submitted' => (clone $query)->where('status', 'submitted')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];
    }

    private function missingCount(?TimesheetPeriod $period): int
    {
        if (! $period) {
            return 0;
        }

        return User::where('role', 'employee')
            ->where('is_active', true)
            ->whereDoesntHave('timesheets', fn ($q) => $q->where('timesheet_period_id', $period->id))
            ->count();
    }

    private function departmentTimesheets(?TimesheetPeriod $period)
    {
        return Timesheet::where('department_id', auth()->user()->department_id)
            ->when($period, fn ($q) => $q->where('timesheet_period_id', $period->id));
    }

    private function departmentMissing(?TimesheetPeriod $period)
    {
        return User::where('department_id', auth()->user()->department_id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->when($period, fn ($q) => $q->whereDoesntHave('timesheets', fn ($t) => $t->where('timesheet_period_id', $period->id)));
    }
}
