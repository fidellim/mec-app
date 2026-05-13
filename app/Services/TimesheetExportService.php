<?php

namespace App\Services;

use App\Models\Timesheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimesheetExportService
{
    public function csv(array $filters): StreamedResponse
    {
        $fileName = 'timesheets_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee name', 'employee number', 'department', 'week number', 'year', 'date', 'day', 'attendance code',
                'project code', 'project name', 'regular hours', 'overtime hours', 'total hours',
                'description', 'remarks', 'status', 'approved by', 'approved date',
            ]);

            Timesheet::query()
                ->with(['user', 'department', 'period', 'entries.project', 'approver'])
                ->when($filters['week_number'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('week_number', $v)))
                ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('year', $v)))
                ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
                ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->orderByDesc('id')
                ->chunk(100, function ($timesheets) use ($handle) {
                    foreach ($timesheets as $timesheet) {
                        foreach ($timesheet->entries as $entry) {
                            fputcsv($handle, [
                                $timesheet->user->name,
                                $timesheet->user->employee_code,
                                $timesheet->department->name,
                                $timesheet->period->week_number,
                                $timesheet->period->year,
                                $entry->work_date->toDateString(),
                                $entry->day_name,
                                $entry->attendance_code,
                                $entry->project?->project_code,
                                $entry->project?->project_name,
                                $entry->regular_hours,
                                $entry->overtime_hours,
                                $entry->regular_hours + $entry->overtime_hours,
                                $entry->description,
                                $entry->remarks,
                                $timesheet->status,
                                $timesheet->approver?->name,
                                $timesheet->approved_at?->toDateString(),
                            ]);
                        }
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
