<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardSummaryService
{
    private const SUMMARY_TTL_SECONDS = 60;
    private const REGIONAL_TTL_SECONDS = 120;
    private const TOTALS_TTL_SECONDS = 300;

    public function superAdminTotals(): array
    {
        return Cache::remember($this->superAdminTotalsKey(), self::TOTALS_TTL_SECONDS, fn () => [
            'totalUsers' => User::count(),
            'activeDepartments' => Department::where('is_active', true)->count(),
            'activeProjects' => Project::where('is_active', true)->count(),
        ]);
    }

    public function summary(?TimesheetPeriod $period): array
    {
        if (! $period) {
            return $this->emptySummary();
        }

        return Cache::remember($this->summaryKey($period->id), self::SUMMARY_TTL_SECONDS, function () use ($period) {
            $query = Timesheet::query()->where('timesheet_period_id', $period->id);

            return [
                'submitted' => (clone $query)->where('status', 'submitted')->count(),
                'approved' => (clone $query)->where('status', 'approved')->count(),
                'rejected' => (clone $query)->where('status', 'rejected')->count(),
            ];
        });
    }

    public function missingCount(?TimesheetPeriod $period): int
    {
        if (! $period) {
            return 0;
        }

        return Cache::remember($this->missingKey($period->id), self::SUMMARY_TTL_SECONDS, fn () => User::where('role', 'employee')
            ->where('is_active', true)
            ->whereDoesntHave('timesheets', fn ($q) => $q
                ->where('timesheet_period_id', $period->id)
                ->whereIn('status', ['submitted', 'approved']))
            ->count());
    }

    public function departmentsWithTimesheetCount(?TimesheetPeriod $period)
    {
        return Cache::remember($this->departmentsKey($period?->id), self::SUMMARY_TTL_SECONDS, fn () => Department::withCount(['timesheets' => fn ($q) => $period
            ? $q->where('timesheet_period_id', $period->id)
            : $q->whereRaw('1 = 0'),
        ])->get());
    }

    public function departmentCounts(?TimesheetPeriod $period, ?int $departmentId): array
    {
        return $this->departmentCountsForDepartmentIds($period, $departmentId ? [$departmentId] : []);
    }

    public function departmentCountsForDepartmentIds(?TimesheetPeriod $period, array $departmentIds): array
    {
        $departmentIds = collect($departmentIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if (! $period || empty($departmentIds)) {
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'missing' => 0,
            ];
        }

        return Cache::remember($this->departmentCountsKey($period->id, $departmentIds), self::SUMMARY_TTL_SECONDS, fn () => [
            'pending' => Timesheet::whereIn('department_id', $departmentIds)
                ->where('timesheet_period_id', $period->id)
                ->where('status', 'submitted')
                ->count(),
            'approved' => Timesheet::whereIn('department_id', $departmentIds)
                ->where('timesheet_period_id', $period->id)
                ->where('status', 'approved')
                ->count(),
            'rejected' => Timesheet::whereIn('department_id', $departmentIds)
                ->where('timesheet_period_id', $period->id)
                ->where('status', 'rejected')
                ->count(),
            'missing' => User::whereIn('department_id', $departmentIds)
                ->where('role', 'employee')
                ->where('is_active', true)
                ->whereDoesntHave('timesheets', fn ($t) => $t
                    ->where('timesheet_period_id', $period->id)
                    ->whereIn('status', ['submitted', 'approved']))
                ->count(),
        ]);
    }

    public function regionalSubmissionSummary(?TimesheetPeriod $period, ?int $departmentId = null): array
    {
        return $this->regionalSubmissionSummaryForDepartmentIds($period, $departmentId ? [$departmentId] : null);
    }

    public function regionalSubmissionSummaryForDepartmentIds(?TimesheetPeriod $period, ?array $departmentIds = null): array
    {
        if (! $period) {
            return $this->emptyRegionalSummary();
        }

        $departmentIds = is_array($departmentIds)
            ? collect($departmentIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
            : null;

        return Cache::remember(
            $this->regionalKey($period->id, $departmentIds),
            self::REGIONAL_TTL_SECONDS,
            fn () => $this->calculateRegionalSubmissionSummary($period, $departmentIds)
        );
    }

    public function forgetForTimesheet(Timesheet $timesheet, ?int $previousDepartmentId = null, ?int $previousPeriodId = null): void
    {
        $periodIds = collect([$timesheet->timesheet_period_id, $previousPeriodId])->filter()->unique();
        $departmentIds = collect([$timesheet->department_id, $previousDepartmentId])->filter()->unique();

        foreach ($periodIds as $periodId) {
            Cache::forget($this->summaryKey($periodId));
            Cache::forget($this->missingKey($periodId));
            Cache::forget($this->departmentsKey($periodId));
            Cache::forget($this->regionalKey($periodId));

            foreach ($departmentIds as $departmentId) {
                Cache::forget($this->departmentCountsKey($periodId, [$departmentId]));
                Cache::forget($this->regionalKey($periodId, [$departmentId]));
            }
        }
    }

    public function forgetSuperAdminTotals(): void
    {
        Cache::forget($this->superAdminTotalsKey());
    }

    private function calculateRegionalSubmissionSummary(TimesheetPeriod $period, ?array $departmentIds = null): array
    {
        $summary = $this->emptyRegionalSummary()['regions'];

        User::with(['timesheets' => fn ($query) => $query
            ->where('timesheet_period_id', $period->id)
            ->select('id', 'user_id', 'timesheet_period_id', 'status')
        ])
            ->where('role', 'employee')
            ->where('is_active', true)
            ->when(is_array($departmentIds), fn ($query) => $query->whereIn('department_id', $departmentIds))
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

    private function emptySummary(): array
    {
        return [
            'submitted' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];
    }

    private function emptyRegionalSummary(): array
    {
        return [
            'regions' => [
                'uae' => ['label' => 'United Arab Emirates', 'submitted' => 0, 'not_submitted' => 0],
                'ph' => ['label' => 'Philippines', 'submitted' => 0, 'not_submitted' => 0],
                'unknown' => ['label' => 'Unknown', 'submitted' => 0, 'not_submitted' => 0],
            ],
            'total' => 0,
            'submitted' => 0,
            'not_submitted' => 0,
        ];
    }

    private function superAdminTotalsKey(): string
    {
        return 'dashboard:super-admin:totals';
    }

    private function summaryKey(int $periodId): string
    {
        return "dashboard:summary:period:{$periodId}";
    }

    private function missingKey(int $periodId): string
    {
        return "dashboard:missing:period:{$periodId}";
    }

    private function departmentsKey(?int $periodId): string
    {
        return 'dashboard:departments:period:'.($periodId ?? 'none');
    }

    private function departmentCountsKey(int $periodId, array $departmentIds): string
    {
        sort($departmentIds);

        return "dashboard:hod-counts:period:{$periodId}:departments:".implode('-', $departmentIds);
    }

    private function regionalKey(int $periodId, ?array $departmentIds = null): string
    {
        if (is_array($departmentIds)) {
            sort($departmentIds);
        }

        return "dashboard:regional:period:{$periodId}:departments:".(is_array($departmentIds) ? implode('-', $departmentIds) : 'all');
    }
}
