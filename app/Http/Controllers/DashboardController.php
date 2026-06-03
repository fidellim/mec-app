<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Services\DashboardSummaryService;

class DashboardController extends Controller
{
    public function __invoke(DashboardSummaryService $dashboard)
    {
        $user = auth()->user();
        $openPeriod = $this->latestOpenPeriod();
        $reportingPeriod = $this->latestCompletedPeriod() ?? $openPeriod;

        return match ($user->role) {
            'super_admin' => view('dashboards.super_admin', $dashboard->superAdminTotals() + [
                'period' => $openPeriod,
                'summary' => $dashboard->summary($openPeriod),
                'submissionPeriod' => $reportingPeriod,
                'regionalSubmissionSummary' => $dashboard->regionalSubmissionSummary($reportingPeriod),
            ]),
            'admin' => view('dashboards.admin', [
                'period' => $reportingPeriod,
                'summary' => $dashboard->summary($reportingPeriod),
                'missing' => $dashboard->missingCount($reportingPeriod),
                'departments' => $dashboard->departmentsWithTimesheetCount($reportingPeriod),
                'regionalSubmissionSummary' => $dashboard->regionalSubmissionSummary($reportingPeriod),
            ]),
            'hod' => view('dashboards.hod', $dashboard->departmentCountsForDepartmentIds($reportingPeriod, $user->managedDepartmentIds()->all()) + [
                'period' => $reportingPeriod,
                'departments' => Department::whereIn('id', $user->managedDepartmentIds())->orderBy('name')->get(),
                'regionalSubmissionSummary' => $dashboard->regionalSubmissionSummaryForDepartmentIds($reportingPeriod, $user->managedDepartmentIds()->all()),
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
}
