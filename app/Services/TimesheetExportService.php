<?php

namespace App\Services;

use App\Exports\TimesheetsExcelExport;
use App\Models\Timesheet;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimesheetExportService
{
    public function excel(array $filters): BinaryFileResponse
    {
        $timesheets = Timesheet::query()
            ->with(['user', 'department', 'period', 'entries.project', 'approver'])
            ->when($filters['week_number'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('week_number', $v)))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('year', $v)))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->get();

        $payload = $timesheets->map(fn (Timesheet $timesheet) => $this->buildWorksheet($timesheet));
        $projectSummary = $this->buildProjectSummary($timesheets);
        $fileName = 'employee_weekly_timesheets_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new TimesheetsExcelExport($payload, $projectSummary), $fileName, ExcelWriter::XLSX);
    }

    public function csv(array $filters): StreamedResponse
    {
        $fileName = 'timesheets_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee name', 'employee number', 'department', 'week number', 'year', 'date', 'day', 'attendance code',
                'project code', 'project name', 'regular hours', 'overtime hours', 'total hours',
                'remarks', 'status', 'approved by', 'approved date',
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

    private function buildWorksheet(Timesheet $timesheet): array
    {
        $dates = collect(CarbonPeriod::create($timesheet->period->start_date, $timesheet->period->end_date))
            ->values();

        $weekdayDates = $dates->take(5);
        $saturday = $dates->first(fn ($date) => $date->isSaturday());
        $sunday = $dates->first(fn ($date) => $date->isSunday());

        $groups = $timesheet->entries
            ->groupBy(fn ($entry) => implode('|', [
                $entry->project_id ?: '-',
                $entry->attendance_code ?: '-',
                trim((string) $entry->remarks),
            ]));

        $rows = $groups->map(function ($entries) use ($weekdayDates, $saturday, $sunday) {
            $first = $entries->first();
            $weekdayValues = [];

            foreach ($weekdayDates as $date) {
                $dayEntries = $entries->filter(fn ($entry) => $entry->work_date->isSameDay($date));
                $weekdayValues[$date->toDateString()] = [
                    'regular' => (float) $dayEntries->sum('regular_hours'),
                    'overtime' => (float) $dayEntries->sum('overtime_hours'),
                ];
            }

            $saturdayEntries = $saturday ? $entries->filter(fn ($entry) => $entry->work_date->isSameDay($saturday)) : collect();
            $sundayEntries = $sunday ? $entries->filter(fn ($entry) => $entry->work_date->isSameDay($sunday)) : collect();
            $regular = (float) $entries->sum('regular_hours');
            $overtime = (float) $entries->sum('overtime_hours');
            $attendanceCode = $first->attendance_code ?? 'O100';
            $isLeave = str_starts_with($attendanceCode, 'L');
            $isAbsent = $attendanceCode === 'L130';
            $holidayHours = $attendanceCode === 'L140' ? $regular + $overtime : 0;
            $leaveHours = $isLeave && ! $isAbsent && $attendanceCode !== 'L140' ? $regular + $overtime : 0;
            $absentHours = $isAbsent ? $regular + $overtime : 0;

            return [
                'project_code' => $first->project?->project_code ?: '-',
                'attendance_code' => $attendanceCode,
                'weekdays' => $weekdayValues,
                'saturday' => (float) $saturdayEntries->sum('regular_hours') + (float) $saturdayEntries->sum('overtime_hours'),
                'sunday' => (float) $sundayEntries->sum('regular_hours') + (float) $sundayEntries->sum('overtime_hours'),
                'holiday' => $holidayHours,
                'regular' => $regular,
                'overtime' => $overtime,
                'leave' => $leaveHours,
                'absent' => $absentHours,
                'total' => $regular + $overtime,
                'remarks' => trim((string) $first->remarks),
            ];
        })->values();

        while ($rows->count() < 6) {
            $rows->push([
                'project_code' => '-',
                'attendance_code' => '-',
                'weekdays' => $weekdayDates->mapWithKeys(fn ($date) => [$date->toDateString() => ['regular' => 0, 'overtime' => 0]])->all(),
                'saturday' => 0,
                'sunday' => 0,
                'holiday' => 0,
                'regular' => 0,
                'overtime' => 0,
                'leave' => 0,
                'absent' => 0,
                'total' => 0,
                'remarks' => '',
            ]);
        }

        return [
            'timesheet' => $timesheet,
            'dates' => $dates,
            'weekday_dates' => $weekdayDates,
            'saturday' => $saturday,
            'sunday' => $sunday,
            'rows' => $rows,
            'initials' => collect(explode(' ', $timesheet->user->name))
                ->filter()
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode(''),
        ];
    }

    private function buildProjectSummary($timesheets)
    {
        return $timesheets
            ->flatMap(fn (Timesheet $timesheet) => $timesheet->entries)
            ->filter(function ($entry) {
                return $entry->project_id
                    && ((float) $entry->regular_hours > 0 || (float) $entry->overtime_hours > 0);
            })
            ->groupBy('project_id')
            ->map(function ($entries) {
                $first = $entries->first();
                $regular = (float) $entries->sum('regular_hours');
                $overtime = (float) $entries->sum('overtime_hours');

                return [
                    'project_code' => $first->project?->project_code ?? '-',
                    'project_name' => $first->project?->project_name ?? 'Unknown Project',
                    'client_name' => $first->project?->client_name ?? '',
                    'regular_hours' => $regular,
                    'overtime_hours' => $overtime,
                    'total_hours' => $regular + $overtime,
                ];
            })
            ->sortBy('project_code')
            ->values();
    }
}
