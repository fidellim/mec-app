<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectAnalyticsService
{
    public function build(Project $project, array $filters): array
    {
        $base = DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED]);

        // Aggregate in SQL: never load individual entries just to draw a chart.
        $daily = (clone $base)
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('entries.work_date', '<=', $date))
            ->groupBy(DB::raw('DATE(entries.work_date)'), 'timesheets.status')
            ->selectRaw('DATE(entries.work_date) as work_date, timesheets.status, SUM(entries.regular_hours) as regular, SUM(entries.overtime_hours) as overtime')
            ->orderBy('work_date')->get();

        $from = $filters['date_from'] ?? $daily->first()?->work_date ?? $filters['date_to'] ?? now()->toDateString();
        $to = $filters['date_to'] ?? max($from, $daily->last()?->work_date ?? $from);
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

        return [
            'months' => $months,
            'employees' => $employees,
            'allocated' => (float) $project->departmentAllocations()->sum('allocated_hours'),
            'hasAllocation' => $project->departmentAllocations()->exists(),
            'approved' => $approved,
            'pending' => $pending,
            'openingHours' => $openingHours,
            'regular' => (float) $selected->sum('regular'),
            'overtime' => (float) $selected->sum('overtime'),
            'from' => $from,
            'to' => $to,
            'hasEntries' => $selected->isNotEmpty(),
        ];
    }
}
