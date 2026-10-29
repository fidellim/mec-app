<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectAnalyticsService
{
    public function build(Project $project, array $filters, bool $includeCostBasis = false): array
    {
        $today = now()->toDateString();
        $to = $filters['date_to'] ?? max($today, $filters['date_from'] ?? $today);

        $base = DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED]);

        // Aggregate in SQL: never load individual entries just to draw a chart.
        $daily = (clone $base)
            ->whereDate('entries.work_date', '<=', $to)
            ->groupBy(DB::raw('DATE(entries.work_date)'), 'timesheets.status')
            ->selectRaw('DATE(entries.work_date) as work_date, timesheets.status, SUM(entries.regular_hours) as regular, SUM(entries.overtime_hours) as overtime')
            ->orderBy('work_date')->get();

        // Clamp only the implicit start so future projects and end-only filters have a valid range.
        $from = $filters['date_from'] ?? min($project->start_date?->toDateString() ?? $daily->first()?->work_date ?? $today, $to);
        $opening = $daily->filter(fn ($row) => $row->work_date < $from);
        $approved = (float) $opening->where('status', Timesheet::STATUS_APPROVED)->sum(fn ($row) => $row->regular + $row->overtime);
        $pending = (float) $opening->where('status', Timesheet::STATUS_SUBMITTED)->sum(fn ($row) => $row->regular + $row->overtime);
        $openingHours = $approved + $pending;
        $selected = $daily->filter(fn ($row) => $row->work_date >= $from && $row->work_date <= $to);
        $byMonth = $selected->groupBy(fn ($row) => substr($row->work_date, 0, 7));
        $months = collect();
        $lastMonth = Carbon::parse($to)->startOfMonth();
        for ($month = Carbon::parse($from)->startOfMonth(); $month->lte($lastMonth); $month->addMonth()) {
            $rows = $byMonth->get($month->format('Y-m'), collect());
            $approved += (float) $rows->where('status', Timesheet::STATUS_APPROVED)->sum(fn ($row) => $row->regular + $row->overtime);
            $pending += (float) $rows->where('status', Timesheet::STATUS_SUBMITTED)->sum(fn ($row) => $row->regular + $row->overtime);
            $months->push([
                'label' => $month->format('M Y'),
                'regular' => (float) $rows->sum('regular'),
                'overtime' => (float) $rows->sum('overtime'),
                'approved' => $approved,
                'pending' => $pending,
            ]);
        }

        $employees = (clone $base)->join('users', 'users.id', '=', 'timesheets.user_id')
            ->whereDate('entries.work_date', '>=', $from)
            ->whereDate('entries.work_date', '<=', $to)
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id, users.name, SUM(entries.regular_hours) as regular, SUM(entries.overtime_hours) as overtime, SUM(entries.regular_hours + entries.overtime_hours) as total')
            ->orderByDesc(($filters['rank_by'] ?? 'overtime') === 'total' ? 'total' : 'overtime')
            ->orderByDesc(($filters['rank_by'] ?? 'overtime') === 'total' ? 'overtime' : 'total')
            ->orderBy('users.name')->orderBy('users.id')->get();

        // Only nonfinancial hour aggregates leave Laravel. Rates are never accepted here.
        $periodRows = (clone $base)
            ->join('users', 'users.id', '=', 'timesheets.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'entries.department_id')
            ->whereDate('entries.work_date', '>=', $from)
            ->whereDate('entries.work_date', '<=', $to)
            ->groupBy('users.id', 'users.employee_code', 'users.name', 'entries.department_id', 'departments.name', 'entries.manpower_category_snapshot', 'timesheets.status', DB::raw('SUBSTR(entries.work_date, 1, 7)'))
            ->selectRaw('users.id as employee_id, users.employee_code, users.name as employee_name, entries.department_id, departments.name as department_name, entries.manpower_category_snapshot as category, timesheets.status, SUBSTR(entries.work_date, 1, 7) as month, SUM(entries.regular_hours) as regular, SUM(entries.overtime_hours) as overtime')
            ->orderBy('month')->orderBy('users.id')->get();

        $departments = $periodRows->groupBy(fn ($row) => $row->department_id ?? 'unclassified')->map(fn ($rows) => [
            'name' => $rows->first()->department_name ?? 'Unclassified department',
            'regular' => (float) $rows->sum('regular'),
            'overtime' => (float) $rows->sum('overtime'),
            'approved' => (float) $rows->where('status', Timesheet::STATUS_APPROVED)->sum(fn ($row) => $row->regular + $row->overtime),
            'pending' => (float) $rows->where('status', Timesheet::STATUS_SUBMITTED)->sum(fn ($row) => $row->regular + $row->overtime),
        ])->sortByDesc(fn ($row) => $row['regular'] + $row['overtime'])->values();

        return [
            'months' => $months,
            'employees' => $employees,
            'departments' => $departments,
            'periodApproved' => (float) $selected->where('status', Timesheet::STATUS_APPROVED)->sum(fn ($row) => $row->regular + $row->overtime),
            'periodPending' => (float) $selected->where('status', Timesheet::STATUS_SUBMITTED)->sum(fn ($row) => $row->regular + $row->overtime),
            'costBasis' => $includeCostBasis ? $periodRows->map(fn ($row) => [
                'employeeCode' => $row->employee_code,
                'employeeName' => $row->employee_name,
                'department' => $row->department_name ?? 'Unclassified department',
                'category' => config('manpower_categories.labels.'.$row->category, 'Not categorized'),
                'month' => $row->month,
                'status' => $row->status,
                'regular' => number_format((float) $row->regular, 2, '.', ''),
                'overtime' => number_format((float) $row->overtime, 2, '.', ''),
            ])->values()->all() : null,
            'allocated' => (float) $project->departmentAllocations()->sum('allocated_hours'),
            'managementCostBasis' => $includeCostBasis ? (clone $base)
                ->join('users', 'users.id', '=', 'timesheets.user_id')
                ->where('timesheets.status', Timesheet::STATUS_APPROVED)
                ->whereDate('entries.work_date', '<=', $to)
                ->groupBy('users.id', 'users.employee_code', 'users.name', DB::raw('SUBSTR(entries.work_date, 1, 7)'), 'entries.department_id', 'entries.manpower_category_snapshot')
                ->selectRaw('users.employee_code, users.name, SUBSTR(entries.work_date, 1, 7) as month, SUM(entries.regular_hours) as regular, SUM(entries.overtime_hours) as overtime')
                ->get()->map(fn ($row) => [
                    'employeeCode' => $row->employee_code, 'employeeName' => $row->name,
                    'month' => $row->month, 'department' => '', 'category' => '', 'status' => 'approved',
                    'regular' => number_format((float) $row->regular, 2, '.', ''),
                    'overtime' => number_format((float) $row->overtime, 2, '.', ''),
                ])->all() : null,
            'hasAllocation' => $project->departmentAllocations()->exists(),
            'approved' => $approved,
            'pending' => $pending,
            'openingHours' => $openingHours,
            'regular' => (float) $selected->sum('regular'),
            'overtime' => (float) $selected->sum('overtime'),
            'from' => $from,
            'to' => $to,
            'projectNotStarted' => $project->start_date?->toDateString() > $today,
            'hasEntries' => $selected->isNotEmpty(),
        ];
    }
}
