<?php

namespace App\Services;

use App\Exports\AttendanceSummaryWorksheetExport;
use App\Exports\ProjectSummaryWorksheetExport;
use App\Exports\TimesheetsExcelExport;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimesheetExportService
{
    public function excel(array $filters): BinaryFileResponse
    {
        $monthly = $this->isMonthly($filters);
        $timesheets = $this->query($filters)
            ->with($this->exportRelations($filters, includeApprover: true))
            ->orderByDesc('id')
            ->get();

        $includeEmployeeSheets = ! $monthly && $this->includeEmployeeSheets($filters);
        $payload = $includeEmployeeSheets
            ? $timesheets->map(fn (Timesheet $timesheet) => $this->buildWorksheet($timesheet))
            : collect();
        $showCosting = $monthly || ! $includeEmployeeSheets;
        $summaryTimesheets = $monthly ? $this->timesheetsWithMonthlyEntries($timesheets) : $timesheets;
        $projectWeeklySummary = $this->buildProjectWeeklySummary($summaryTimesheets, $filters['project_id'] ?? null, $filters);
        $attendanceSummary = $this->buildAttendanceSummary($summaryTimesheets, $filters);
        $employeeRateRows = $showCosting ? $this->buildEmployeeRateRows($summaryTimesheets) : collect();
        $fileName = $monthly
            ? 'monthly_timesheet_report_'.$this->monthlyDateRange($filters)['start']->format('Y_m').'_'.now()->format('Ymd_His').'.xlsx'
            : 'employee_weekly_timesheets_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new TimesheetsExcelExport($payload, $projectWeeklySummary, $attendanceSummary, $includeEmployeeSheets, $monthly ? 'monthly' : 'weekly', $employeeRateRows, $showCosting), $fileName, ExcelWriter::XLSX);
    }

    public function matchingTimesheetCount(array $filters): int
    {
        return $this->query($filters)->count();
    }

    public function summaryPreview(array $filters): array
    {
        $timesheets = $this->summaryTimesheets($filters);
        $summaryTimesheets = $this->isMonthly($filters) ? $this->timesheetsWithMonthlyEntries($timesheets) : $timesheets;
        $projectSummaryRows = $this->buildProjectWeeklySummary($summaryTimesheets, $filters['project_id'] ?? null, $filters);
        $attendanceSummaryRows = $this->buildAttendanceSummary($summaryTimesheets, $filters);

        return [
            'project' => (new ProjectSummaryWorksheetExport($projectSummaryRows, $this->isMonthly($filters) ? 'monthly' : 'weekly'))->data(),
            'attendance' => (new AttendanceSummaryWorksheetExport($attendanceSummaryRows, $this->isMonthly($filters) ? 'monthly' : 'weekly'))->data(),
            'timesheet_count' => $summaryTimesheets->count(),
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
                ->with($this->exportRelations($filters, includeApprover: true))
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

        $leaveCodes = config('timesheet.leave_attendance_codes', []);

        $rows = $groups->map(function ($entries) use ($weekdayDates, $saturday, $sunday, $leaveCodes) {
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
            $isLeave = in_array($attendanceCode, $leaveCodes, true);
            $isAbsent = $attendanceCode === 'L130';
            $holidayHours = $attendanceCode === 'L140' ? $regular + $overtime : 0;
            $leaveHours = $isLeave && ! $isAbsent && $attendanceCode !== 'L140' ? $regular + $overtime : 0;
            $absentHours = $isAbsent ? $regular + $overtime : 0;

            return [
                'project_code' => $this->spreadsheetText($first->project?->project_code ?: '-'),
                'attendance_code' => $this->spreadsheetText($attendanceCode),
                'weekdays' => $weekdayValues,
                'saturday' => (float) $saturdayEntries->sum('regular_hours') + (float) $saturdayEntries->sum('overtime_hours'),
                'sunday' => (float) $sundayEntries->sum('regular_hours') + (float) $sundayEntries->sum('overtime_hours'),
                'holiday' => $holidayHours,
                'regular' => $regular,
                'overtime' => $overtime,
                'leave' => $leaveHours,
                'absent' => $absentHours,
                'total' => $regular + $overtime,
                'remarks' => $this->spreadsheetText(trim((string) $first->remarks)),
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
            'employee_name' => $this->spreadsheetText($timesheet->user->name),
            'employee_code' => $this->spreadsheetText($timesheet->user->employee_code),
            'department_name' => $this->spreadsheetText($timesheet->department->name),
            'job_title' => $this->spreadsheetText($timesheet->user->job_title ?: '-'),
            'dates' => $dates,
            'weekday_dates' => $weekdayDates,
            'saturday' => $saturday,
            'sunday' => $sunday,
            'rows' => $rows,
            'initials' => $this->spreadsheetText($timesheet->user->initials ?: $this->initialsFromName($timesheet->user->name)),
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
        return $this->query($filters)
            ->with($this->exportRelations($filters))
            ->orderByDesc('id')
            ->get();
    }

    private function query(array $filters)
    {
        return Timesheet::query()
            ->tap(fn ($query) => $this->applyFilters($query, $filters));
    }

    private function exportRelations(array $filters, bool $includeApprover = false): array
    {
        $monthly = $this->isMonthly($filters);
        $monthRange = $monthly ? $this->monthlyDateRange($filters) : null;
        $projectId = $filters['project_id'] ?? null;
        $relations = [
            'user:id,name,employee_code,initials,job_title',
            'department:id,name',
            'period:id,week_number,year,start_date,end_date',
            'entries' => function ($query) use ($monthly, $monthRange, $projectId) {
                $query
                    ->select([
                        'id', 'timesheet_id', 'work_date', 'day_name', 'attendance_code',
                        'project_id', 'regular_hours', 'overtime_hours', 'remarks',
                    ])
                    ->when($monthly, fn ($entry) => $entry->whereBetween('work_date', [
                        $monthRange['start']->toDateString(),
                        $monthRange['end']->toDateString(),
                    ]))
                    ->when($projectId, fn ($entry) => $entry->where('project_id', $projectId));
            },
            'entries.project:id,project_code,project_name,client_name',
        ];

        if ($includeApprover) {
            $relations[] = 'approver:id,name';
        }

        return $relations;
    }

    private function applyFilters($query, array $filters): void
    {
        $monthly = $this->isMonthly($filters);
        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;
        $weekTo = $filters['week_to'] ?? $weekFrom;
        $monthRange = $monthly ? $this->monthlyDateRange($filters) : null;

        $query
            ->where('status', '!=', Timesheet::STATUS_VOIDED)
            ->when(! $monthly && $weekFrom, fn ($q) => $q->whereHas('period', fn ($p) => $p->whereBetween('week_number', [(int) $weekFrom, (int) $weekTo])))
            ->when(! $monthly && ($filters['year'] ?? null), fn ($q) => $q->whereHas('period', fn ($p) => $p->where('year', $filters['year'])))
            ->when($monthly, fn ($q) => $q
                ->whereHas('period', fn ($p) => $p
                    ->whereDate('start_date', '<=', $monthRange['end']->toDateString())
                    ->whereDate('end_date', '>=', $monthRange['start']->toDateString()))
                ->whereHas('entries', fn ($entry) => $entry
                    ->whereBetween('work_date', [
                        $monthRange['start']->toDateString(),
                        $monthRange['end']->toDateString(),
                    ])))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($user) => $user->where('role', $v)))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->whereHas('entries', fn ($entry) => $entry
                ->where('project_id', $v)
                ->when($monthly, fn ($entry) => $entry->whereBetween('work_date', [
                    $monthRange['start']->toDateString(),
                    $monthRange['end']->toDateString(),
                ]))));
    }

    private function buildProjectWeeklySummary($timesheets, $projectId = null, array $filters = [])
    {
        $monthly = $this->isMonthly($filters);

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
                $monthly ? $this->monthlyDateRange($filters)['start']->format('Y-m') : $entry['timesheet']->timesheet_period_id,
                $entry['entry']->project_id,
                $entry['timesheet']->user_id,
            ]))
            ->map(function ($entries) use ($filters) {
                return $this->projectEmployeeSummaryRow($entries, $filters);
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

    private function projectEmployeeSummaryRow($entries, array $filters = []): array
    {
        $first = $entries->first();
        $timesheet = $first['timesheet'];
        $entry = $first['entry'];
        $monthly = $this->isMonthly($filters);
        $monthRange = $monthly ? $this->monthlyDateRange($filters) : null;
        $regular = (float) $entries->sum(fn ($item) => (float) $item['entry']->regular_hours);
        $overtime = (float) $entries->sum(fn ($item) => (float) $item['entry']->overtime_hours);
        $user = $timesheet->user;

        return [
            'week_number' => $monthly ? (int) $monthRange['start']->format('n') : $timesheet->period->week_number,
            'year' => $monthly ? (int) $monthRange['start']->format('Y') : $timesheet->period->year,
            'week_start' => $monthly ? $monthRange['start'] : $timesheet->period->start_date,
            'week_end' => $monthly ? $monthRange['end'] : $timesheet->period->end_date,
            'period_key' => $monthly ? $monthRange['start']->format('Y-m') : null,
            'period_label' => $monthly ? $monthRange['start']->format('F Y') : null,
            'project_id' => $entry->project_id,
            'project_code' => $this->spreadsheetText($entry->project?->project_code ?? '-'),
            'project_name' => $this->spreadsheetText($entry->project?->project_name ?? 'Unknown Project'),
            'client_name' => $this->spreadsheetText($entry->project?->client_name ?? ''),
            'employee_id' => $this->spreadsheetText($user->employee_code ?? ''),
            'initials' => $this->spreadsheetText($user->initials ?: $this->initialsFromName($user->name)),
            'employee_name' => $this->spreadsheetText($user->name),
            'job_title' => $this->spreadsheetText($user->job_title ?: '-'),
            'regular_hours' => $regular,
            'overtime_hours' => $overtime,
            'total_hours' => $regular + $overtime,
        ];
    }

    private function buildAttendanceSummary($timesheets, array $filters = [])
    {
        $monthly = $this->isMonthly($filters);
        $monthRange = $monthly ? $this->monthlyDateRange($filters) : null;
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
                $monthly ? $monthRange['start']->format('Y-m') : $row['timesheet']->timesheet_period_id,
                $row['timesheet']->user_id,
                $row['entry']->attendance_code ?: '-',
                $row['entry']->project_id ?: '-',
                $row['timesheet']->status,
            ]))
            ->map(function ($rows) use ($attendanceLabels, $monthly, $monthRange) {
                $first = $rows->first();
                $timesheet = $first['timesheet'];
                $entry = $first['entry'];
                $user = $timesheet->user;
                $regular = (float) $rows->sum(fn ($row) => (float) $row['entry']->regular_hours);
                $overtime = (float) $rows->sum(fn ($row) => (float) $row['entry']->overtime_hours);
                $attendanceCode = $entry->attendance_code ?: '-';

                return [
                    'week_number' => $monthly ? (int) $monthRange['start']->format('n') : $timesheet->period->week_number,
                    'year' => $monthly ? (int) $monthRange['start']->format('Y') : $timesheet->period->year,
                    'week_start' => $monthly ? $monthRange['start'] : $timesheet->period->start_date,
                    'week_end' => $monthly ? $monthRange['end'] : $timesheet->period->end_date,
                    'period_key' => $monthly ? $monthRange['start']->format('Y-m') : null,
                    'period_label' => $monthly ? $monthRange['start']->format('F Y') : null,
                    'employee_id' => $this->spreadsheetText($user->employee_code ?? ''),
                    'initials' => $this->spreadsheetText($user->initials ?: $this->initialsFromName($user->name)),
                    'employee_name' => $this->spreadsheetText($user->name),
                    'department_name' => $this->spreadsheetText($timesheet->department->name),
                    'job_title' => $this->spreadsheetText($user->job_title ?: '-'),
                    'attendance_code' => $this->spreadsheetText($attendanceCode),
                    'attendance_label' => $this->spreadsheetText($attendanceLabels[$attendanceCode] ?? 'Uncoded non-project hours'),
                    'project_code' => $this->spreadsheetText($entry->project?->project_code ?? 'Non-project'),
                    'regular_hours' => $regular,
                    'overtime_hours' => $overtime,
                    'total_hours' => $regular + $overtime,
                    'status' => $this->spreadsheetText($timesheet->status),
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

    private function timesheetsWithMonthlyEntries(Collection $timesheets): Collection
    {
        return $timesheets
            ->filter(fn (Timesheet $timesheet) => $timesheet->entries->isNotEmpty())
            ->values();
    }

    private function buildEmployeeRateRows(Collection $timesheets): Collection
    {
        return $timesheets
            ->map(function (Timesheet $timesheet) {
                $user = $timesheet->user;

                return [
                    'employee_id' => $this->spreadsheetText($user->employee_code ?? ''),
                    'initials' => $this->spreadsheetText($user->initials ?: $this->initialsFromName($user->name)),
                    'employee_name' => $this->spreadsheetText($user->name),
                    'job_title' => $this->spreadsheetText($user->job_title ?: '-'),
                ];
            })
            ->unique(fn (array $row) => implode('|', [
                $row['employee_id'],
                $row['employee_name'],
            ]))
            ->sortBy([
                ['employee_name', 'asc'],
                ['employee_id', 'asc'],
            ])
            ->values();
    }

    private function isMonthly(array $filters): bool
    {
        return ($filters['filter_mode'] ?? 'weekly') === 'monthly';
    }

    private function monthlyDateRange(array $filters): array
    {
        $start = CarbonImmutable::create((int) $filters['year'], (int) $filters['month'], 1)->startOfMonth();

        return [
            'start' => $start,
            'end' => $start->endOfMonth(),
        ];
    }

    private function spreadsheetText(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return $value;
        }

        return preg_match('/^[=\-+@]/', $value) ? "'".$value : $value;
    }
}
