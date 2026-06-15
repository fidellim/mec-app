<?php

namespace App\Services;

use App\Exports\AttendanceSummaryWorksheetExport;
use App\Exports\ProjectSummaryWorksheetExport;
use App\Exports\TimesheetsExcelExport;
use App\Models\Timesheet;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
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
            ->tap(fn ($query) => $this->applyFilters($query, $filters))
            ->orderByDesc('id')
            ->get();

        $includeEmployeeSheets = $this->includeEmployeeSheets($filters);
        $payload = $includeEmployeeSheets
            ? $timesheets->map(fn (Timesheet $timesheet) => $this->buildWorksheet($timesheet))
            : collect();
        $projectWeeklySummary = $this->buildProjectWeeklySummary($timesheets, $filters['project_id'] ?? null);
        $attendanceSummary = $this->buildAttendanceSummary($timesheets);
        $fileName = 'employee_weekly_timesheets_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new TimesheetsExcelExport($payload, $projectWeeklySummary, $attendanceSummary, $includeEmployeeSheets), $fileName, ExcelWriter::XLSX);
    }

    public function summaryPreview(array $filters): array
    {
        $timesheets = $this->summaryTimesheets($filters);
        $projectSummaryRows = $this->buildProjectWeeklySummary($timesheets, $filters['project_id'] ?? null);
        $attendanceSummaryRows = $this->buildAttendanceSummary($timesheets);

        return [
            'project' => (new ProjectSummaryWorksheetExport($projectSummaryRows))->data(),
            'attendance' => (new AttendanceSummaryWorksheetExport($attendanceSummaryRows))->data(),
            'timesheet_count' => $timesheets->count(),
            'project_row_count' => $projectSummaryRows->count(),
            'attendance_row_count' => $attendanceSummaryRows->count(),
        ];
    }

    public function csv(array $filters): StreamedResponse
    {
        $fileName = 'timesheets_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee name', 'employee number', 'job title', 'department', 'week number', 'year', 'date', 'day', 'attendance code',
                'project code', 'project name', 'regular hours', 'overtime hours', 'total hours',
                'remarks', 'status', 'approved by', 'approved date',
            ]);

            Timesheet::query()
                ->with(['user', 'department', 'period', 'entries.project', 'approver'])
                ->tap(fn ($query) => $this->applyFilters($query, $filters))
                ->orderByDesc('id')
                ->chunk(100, function ($timesheets) use ($handle) {
                    foreach ($timesheets as $timesheet) {
                        foreach ($timesheet->entries as $entry) {
                            fputcsv($handle, [
                                $timesheet->user->name,
                                $timesheet->user->employee_code,
                                $timesheet->user->job_title ?: '-',
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
            'initials' => $timesheet->user->initials ?: $this->initialsFromName($timesheet->user->name),
        ];
    }

    private function initialsFromName(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->implode('');
    }

    private function includeEmployeeSheets(array $filters): bool
    {
        return filter_var($filters['include_employee_sheets'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function summaryTimesheets(array $filters): Collection
    {
        return Timesheet::query()
            ->with(['user', 'department', 'period', 'entries.project'])
            ->tap(fn ($query) => $this->applyFilters($query, $filters))
            ->orderByDesc('id')
            ->get();
    }

    private function applyFilters($query, array $filters): void
    {
        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;
        $weekTo = $filters['week_to'] ?? $weekFrom;

        $query
            ->where('status', '!=', Timesheet::STATUS_VOIDED)
            ->when($weekFrom, fn ($q) => $q->whereHas('period', fn ($p) => $p->whereBetween('week_number', [(int) $weekFrom, (int) $weekTo])))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('year', $v)))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($user) => $user->where('role', $v)))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->whereHas('entries', fn ($entry) => $entry->where('project_id', $v)));
    }

    private function buildProjectWeeklySummary($timesheets, $projectId = null)
    {
        return $timesheets
            ->flatMap(fn (Timesheet $timesheet) => $timesheet->entries->map(fn ($entry) => [
                'timesheet' => $timesheet,
                'entry' => $entry,
            ]))
            ->filter(function ($entry) {
                return $entry['entry']->project_id
                    && ((float) $entry['entry']->regular_hours > 0 || (float) $entry['entry']->overtime_hours > 0);
            })
            ->when($projectId, fn ($entries) => $entries->filter(fn ($entry) => (int) $entry['entry']->project_id === (int) $projectId))
            ->groupBy(fn ($entry) => implode('|', [
                $entry['timesheet']->timesheet_period_id,
                $entry['entry']->project_id,
                $entry['timesheet']->user_id,
            ]))
            ->map(function ($entries) {
                return $this->projectEmployeeSummaryRow($entries);
            })
            ->sortBy([
                ['year', 'asc'],
                ['week_number', 'asc'],
                ['project_code', 'asc'],
                ['total_hours', 'desc'],
                ['employee_name', 'asc'],
            ])
            ->values();
    }

    private function projectEmployeeSummaryRow($entries): array
    {
        $first = $entries->first();
        $timesheet = $first['timesheet'];
        $entry = $first['entry'];
        $regular = (float) $entries->sum(fn ($item) => (float) $item['entry']->regular_hours);
        $overtime = (float) $entries->sum(fn ($item) => (float) $item['entry']->overtime_hours);
        $user = $timesheet->user;

        return [
            'week_number' => $timesheet->period->week_number,
            'year' => $timesheet->period->year,
            'week_start' => $timesheet->period->start_date,
            'week_end' => $timesheet->period->end_date,
            'project_id' => $entry->project_id,
            'project_code' => $entry->project?->project_code ?? '-',
            'project_name' => $entry->project?->project_name ?? 'Unknown Project',
            'client_name' => $entry->project?->client_name ?? '',
            'employee_id' => $user->employee_code ?? '',
            'initials' => $user->initials ?: $this->initialsFromName($user->name),
            'employee_name' => $user->name,
            'job_title' => $user->job_title ?: '-',
            'regular_hours' => $regular,
            'overtime_hours' => $overtime,
            'total_hours' => $regular + $overtime,
        ];
    }

    private function buildAttendanceSummary($timesheets)
    {
        $leaveCodes = config('timesheet.leave_attendance_codes', []);
        $attendanceLabels = config('timesheet.attendance_codes', []);

        return $timesheets
            ->flatMap(fn (Timesheet $timesheet) => $timesheet->entries->map(fn ($entry) => [
                'timesheet' => $timesheet,
                'entry' => $entry,
            ]))
            ->filter(function ($row) use ($leaveCodes) {
                $entry = $row['entry'];
                $hasHours = (float) $entry->regular_hours > 0 || (float) $entry->overtime_hours > 0;
                $isLeaveCode = in_array($entry->attendance_code, $leaveCodes, true);

                return $hasHours && ($isLeaveCode || ! $entry->project_id);
            })
            ->groupBy(fn ($row) => implode('|', [
                $row['timesheet']->timesheet_period_id,
                $row['timesheet']->user_id,
                $row['entry']->attendance_code ?: '-',
                $row['entry']->project_id ?: '-',
                $row['timesheet']->status,
            ]))
            ->map(function ($rows) use ($attendanceLabels) {
                $first = $rows->first();
                $timesheet = $first['timesheet'];
                $entry = $first['entry'];
                $user = $timesheet->user;
                $regular = (float) $rows->sum(fn ($row) => (float) $row['entry']->regular_hours);
                $overtime = (float) $rows->sum(fn ($row) => (float) $row['entry']->overtime_hours);
                $attendanceCode = $entry->attendance_code ?: '-';

                return [
                    'week_number' => $timesheet->period->week_number,
                    'year' => $timesheet->period->year,
                    'week_start' => $timesheet->period->start_date,
                    'week_end' => $timesheet->period->end_date,
                    'employee_id' => $user->employee_code ?? '',
                    'initials' => $user->initials ?: $this->initialsFromName($user->name),
                    'employee_name' => $user->name,
                    'department_name' => $timesheet->department->name,
                    'job_title' => $user->job_title ?: '-',
                    'attendance_code' => $attendanceCode,
                    'attendance_label' => $attendanceLabels[$attendanceCode] ?? 'Uncoded non-project hours',
                    'project_code' => $entry->project?->project_code ?? 'Non-project',
                    'regular_hours' => $regular,
                    'overtime_hours' => $overtime,
                    'total_hours' => $regular + $overtime,
                    'status' => $timesheet->status,
                ];
            })
            ->sortBy([
                ['year', 'asc'],
                ['week_number', 'asc'],
                ['employee_name', 'asc'],
                ['attendance_code', 'asc'],
                ['project_code', 'asc'],
            ])
            ->values();
    }
}
