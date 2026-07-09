<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimesheetsExcelExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $worksheets,
        private readonly ?Collection $projectWeeklySummaryRows = null,
        private readonly ?Collection $attendanceSummaryRows = null,
        private readonly bool $includeEmployeeSheets = false,
        private readonly string $reportMode = 'weekly',
        private readonly ?Collection $employeeRateRows = null,
        private readonly bool $showCosting = false
    ) {
    }

    public function sheets(): array
    {
        $sheets = [
            new ProjectSummaryWorksheetExport($this->projectWeeklySummaryRows ?? collect(), $this->reportMode, $this->showCosting),
            new AttendanceSummaryWorksheetExport($this->attendanceSummaryRows ?? collect(), $this->reportMode, $this->showCosting),
        ];

        if ($this->showCosting) {
            $sheets[] = new EmployeeRatesWorksheetExport($this->employeeRateRows ?? collect());
        }

        if (! $this->includeEmployeeSheets || $this->worksheets->isEmpty()) {
            return $sheets;
        }

        return array_merge($sheets, $this->worksheets
            ->values()
            ->map(fn (array $worksheet, int $index) => new TimesheetWorksheetExport(
                $worksheet,
                $this->sheetTitle($worksheet, $index)
            ))
            ->all());
    }

    private function sheetTitle(array $worksheet, int $index): string
    {
        $timesheet = $worksheet['timesheet'];
        $base = trim($timesheet->user->name).' W'.$timesheet->period->week_number;
        $title = preg_replace('/[\[\]\:\*\?\/\\\\]/', '', $base) ?: 'Timesheet';
        $title = mb_substr($title, 0, 28);

        return $index === 0 ? $title : mb_substr($title, 0, 25).' '.($index + 1);
    }
}
