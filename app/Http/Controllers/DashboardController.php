<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Services\DashboardSummaryService;
use App\Services\LeaveEntitlementService;

class DashboardController extends Controller
{
    public function __invoke(DashboardSummaryService $dashboard, LeaveEntitlementService $entitlements)
    {
        $user = auth()->user();
        $openPeriod = $this->latestOpenPeriod();
        $reportingPeriod = $this->latestCompletedPeriod() ?? $openPeriod;
        $leaveBalances = $entitlements->visibleBalancesFor($user, viewer: $user);
        $managedDepartmentIds = $user->role === 'hod' ? $user->managedDepartmentIds() : collect();

        return match ($user->role) {
            'super_admin' => view('dashboards.super_admin', $dashboard->superAdminTotals() + [
                'period' => $openPeriod,
                'summary' => $dashboard->summary($openPeriod),
                'submissionPeriod' => $reportingPeriod,
                'regionalSubmissionSummary' => $dashboard->regionalSubmissionSummary($reportingPeriod),
                'leaveBalances' => $leaveBalances,
            ]),
            'admin' => view('dashboards.admin', [
                'period' => $reportingPeriod,
                'summary' => $dashboard->summary($reportingPeriod),
                'missing' => $dashboard->missingCount($reportingPeriod),
                'departments' => $dashboard->departmentHealth($reportingPeriod),
                'regionalSubmissionSummary' => $dashboard->regionalSubmissionSummary($reportingPeriod),
                'leaveBalances' => $leaveBalances,
            ]),
            'hod' => view('dashboards.hod', $dashboard->departmentCountsForDepartmentIds($reportingPeriod, $managedDepartmentIds->all()) + [
                'period' => $reportingPeriod,
                'departments' => Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get(),
                'regionalSubmissionSummary' => $dashboard->regionalSubmissionSummaryForDepartmentIds($reportingPeriod, $managedDepartmentIds->all()),
                'leaveBalances' => $leaveBalances,
            ]),
            default => view('dashboards.employee', [
                'period' => $openPeriod,
                'current' => $openPeriod ? Timesheet::with('period')->where('user_id', $user->id)->where('timesheet_period_id', $openPeriod->id)->first() : null,
                'drafts' => Timesheet::with('period')->where('user_id', $user->id)->where('status', 'draft')->latest()->get(),
                'rejected' => Timesheet::with('period')->where('user_id', $user->id)->where('status', 'rejected')->latest()->get(),
                'recent' => Timesheet::with('period')->where('user_id', $user->id)->latest()->limit(5)->get(),
                'leaveBalances' => $leaveBalances,
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
