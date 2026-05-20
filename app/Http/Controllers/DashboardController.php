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
        $openPeriod = $this->latestOpenPeriod();
        $reportingPeriod = $this->latestCompletedPeriod() ?? $openPeriod;

        return match ($user->role) {
            'super_admin' => view('dashboards.super_admin', [
                'totalUsers' => User::count(),
                'activeDepartments' => Department::where('is_active', true)->count(),
                'activeProjects' => Project::where('is_active', true)->count(),
                'period' => $openPeriod,
                'summary' => $this->summary($openPeriod),
                'submissionPeriod' => $reportingPeriod,
                'regionalSubmissionSummary' => $this->regionalSubmissionSummary($reportingPeriod),
            ]),
            'admin' => view('dashboards.admin', [
                'period' => $reportingPeriod,
                'summary' => $this->summary($reportingPeriod),
                'missing' => $this->missingCount($reportingPeriod),
                'departments' => Department::withCount(['timesheets' => fn ($q) => $reportingPeriod
                    ? $q->where('timesheet_period_id', $reportingPeriod->id)
                    : $q->whereRaw('1 = 0'),
                ])->get(),
                'regionalSubmissionSummary' => $this->regionalSubmissionSummary($reportingPeriod),
            ]),
            'hod' => view('dashboards.hod', [
                'period' => $reportingPeriod,
                'pending' => $this->departmentTimesheets($reportingPeriod)->where('status', 'submitted')->count(),
                'approved' => $this->departmentTimesheets($reportingPeriod)->where('status', 'approved')->count(),
                'rejected' => $this->departmentTimesheets($reportingPeriod)->where('status', 'rejected')->count(),
                'missing' => $this->departmentMissing($reportingPeriod)->count(),
                'regionalSubmissionSummary' => $this->regionalSubmissionSummary($reportingPeriod, $user->department_id),
            ]),
            default => view('dashboards.employee', [
                'period' => $openPeriod,
                'current' => $openPeriod ? Timesheet::with('period')->where('user_id', $user->id)->where('timesheet_period_id', $openPeriod->id)->first() : null,
                'drafts' => Timesheet::with('period')->where('user_id', $user->id)->where('status', 'draft')->latest()->get(),
                'rejected' => Timesheet::with('period')->where('user_id', $user->id)->where('status', 'rejected')->latest()->get(),
                'recent' => Timesheet::with('period')->where('user_id', $user->id)->latest()->limit(5)->get(),
            ]),
        };
    }

    private function latestOpenPeriod(): ?TimesheetPeriod
    {
        return TimesheetPeriod::where('status', 'open')->latest('start_date')->first();
    }

    private function latestCompletedPeriod(): ?TimesheetPeriod
    {
        return TimesheetPeriod::whereDate('end_date', '<', now()->toDateString())
            ->latest('end_date')
            ->first();
    }

    private function summary(?TimesheetPeriod $period): array
    {
        if (! $period) {
            return [
                'submitted' => 0,
                'approved' => 0,
                'rejected' => 0,
            ];
        }

        $query = Timesheet::query()->where('timesheet_period_id', $period->id);

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

    private function regionalSubmissionSummary(?TimesheetPeriod $period, ?int $departmentId = null): array
    {
        $summary = [
            'uae' => ['label' => 'United Arab Emirates', 'submitted' => 0, 'not_submitted' => 0],
            'ph' => ['label' => 'Philippines', 'submitted' => 0, 'not_submitted' => 0],
            'unknown' => ['label' => 'Unknown', 'submitted' => 0, 'not_submitted' => 0],
        ];

        if (! $period) {
            return [
                'regions' => $summary,
                'total' => 0,
                'submitted' => 0,
                'not_submitted' => 0,
            ];
        }

        User::with(['timesheets' => fn ($query) => $query
            ->where('timesheet_period_id', $period->id)
            ->select('id', 'user_id', 'timesheet_period_id', 'status')
        ])
            ->where('role', 'employee')
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get(['id', 'department_id', 'employee_code'])
            ->each(function (User $employee) use (&$summary) {
                $region = $this->employeeRegion($employee->employee_code);
                $statusKey = $employee->timesheets->contains(fn (Timesheet $timesheet) => in_array($timesheet->status, ['submitted', 'approved'], true))
                    ? 'submitted'
                    : 'not_submitted';

                $summary[$region][$statusKey]++;
            });

        $submitted = collect($summary)->sum('submitted');
        $notSubmitted = collect($summary)->sum('not_submitted');

        return [
            'regions' => $summary,
            'total' => $submitted + $notSubmitted,
            'submitted' => $submitted,
            'not_submitted' => $notSubmitted,
        ];
    }

    private function employeeRegion(?string $employeeCode): string
    {
        return match (true) {
            is_string($employeeCode) && str_starts_with($employeeCode, 'MEC-PHIL-HR-') => 'ph',
            is_string($employeeCode) && (
                str_starts_with($employeeCode, 'MEC-HR-')
                || str_starts_with($employeeCode, 'MCE-HR-')
            ) => 'uae',
            default => 'unknown',
        };
    }

    private function departmentTimesheets(?TimesheetPeriod $period)
    {
        return Timesheet::where('department_id', auth()->user()->department_id)
            ->when(
                $period,
                fn ($q) => $q->where('timesheet_period_id', $period->id),
                fn ($q) => $q->whereRaw('1 = 0')
            );
    }

    private function departmentMissing(?TimesheetPeriod $period)
    {
        if (! $period) {
            return User::whereRaw('1 = 0');
        }

        return User::where('department_id', auth()->user()->department_id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->whereDoesntHave('timesheets', fn ($t) => $t->where('timesheet_period_id', $period->id));
    }
}
