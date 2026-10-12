<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceSummaryWorksheetExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly string $reportMode = 'weekly',
        private readonly ?bool $showCosting = null,
        private readonly string $summarySort = 'total_hours'
    ) {}

    public function title(): string
    {
        return 'Attendance Code Summary';
    }

    public function view(): View
    {
        return view('exports.attendance_summary_excel', $this->data());
    }

    public function data(): array
    {
        $weeks = $this->weeks($this->rows);

        return [
            'groups' => $this->groups(),
            'weeks' => $weeks,
            'showRangeTotals' => $this->showRangeTotals($weeks),
            'grandTotalsByWeek' => $this->weekTotals($this->rows, $weeks),
            'totalColumns' => $this->totalColumns(),
            'totalRegular' => $this->rows->sum('regular_hours'),
            'totalOvertime' => $this->rows->sum('overtime_hours'),
            'totalHours' => $this->rows->sum('total_hours'),
            'reportTitle' => $this->title(),
            'showCosting' => $this->showCosting(),
            'periodColumnSpan' => $this->periodColumnCount(),
        ];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 22,
            'B' => 12,
            'C' => 26,
            'D' => 18,
            'E' => 28,
            'F' => $this->showCosting() ? 15 : 18,
            'G' => $this->showCosting() ? 18 : 14,
        ];

        if ($this->showCosting()) {
            $widths['H'] = 14;
        }

        for ($column = $this->firstPeriodColumnIndex(); $column <= $this->totalColumns(); $column++) {
            $widths[Coordinate::stringFromColumnIndex($column)] = 15;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $groups = $this->groups();
                $lastRow = $this->lastRow();
                $lastColumn = $this->lastColumn();

                $sheet->getDefaultRowDimension()->setRowHeight(20);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                foreach ($this->attendanceTableRanges() as $groupIndex => [$startRow, $endRow]) {
                    $lastColumn = $this->groupLastColumn($groups[$groupIndex]);
                    $sheet->getStyle("A{$startRow}:{$lastColumn}{$endRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ],
                    ]);
                }

                $lastColumn = $this->lastColumn();
                $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $firstPeriodColumn = Coordinate::stringFromColumnIndex($this->firstPeriodColumnIndex());
                $sheet->getStyle("{$firstPeriodColumn}3:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                foreach ($this->attendanceHeaderRows() as $groupIndex => $rowNumber) {
                    $lastColumn = $this->groupLastColumn($groups[$groupIndex]);
                    $sheet->mergeCells("A{$rowNumber}:{$lastColumn}{$rowNumber}");
                    $sheet->getRowDimension($rowNumber)->setRowHeight(32);
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F6228']],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_TOP,
                        ],
                    ]);
                }

                foreach ($this->weekHeaderRows() as $groupIndex => $rowNumber) {
                    $lastColumn = $this->groupLastColumn($groups[$groupIndex]);
                    $sheet->getRowDimension($rowNumber)->setRowHeight(36);
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD8E4BC']],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);

                    foreach ($this->weekColumnRanges($groups[$groupIndex]['compact_leave']) as [$startColumn, $endColumn]) {
                        $sheet->mergeCells("{$startColumn}{$rowNumber}:{$endColumn}{$rowNumber}");
                    }
                }

                foreach ($this->tableHeaderRows() as $groupIndex => $rowNumber) {
                    $lastColumn = $this->groupLastColumn($groups[$groupIndex]);
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEBF1DE']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach ($this->attendanceTotalRows() as $groupIndex => $rowNumber) {
                    $lastColumn = $this->groupLastColumn($groups[$groupIndex]);
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                    ]);
                }

                $lastColumn = $this->lastColumn();
                if ($this->rows->isNotEmpty()) {
                    $overallHeaderRow = $lastRow - 1;
                    $sheet->getRowDimension($overallHeaderRow)->setRowHeight(36);
                    $sheet->getStyle("A{$overallHeaderRow}:{$lastColumn}{$overallHeaderRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEBF1DE']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                }
                $this->styleSelectedPeriodTotalColumns($sheet);
                $this->styleCostColumns($sheet);

                $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F4']],
                ]);

                foreach ($this->spacerRows() as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_NONE,
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNumber)->setRowHeight(14);
                }
            },
        ];
    }

    private function groups(): Collection
    {
        return $this->rows
            ->groupBy(fn (array $row) => implode('|', [
                $row['attendance_code'],
                $row['project_code'] === 'Non-project' ? '-' : $row['project_code'],
            ]))
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $weeks = $this->weeks($this->rows);
                $employees = $this->employees($rows, $weeks);

                return [
                    'attendance_code' => $first['attendance_code'],
                    'attendance_label' => $first['attendance_label'],
                    // Keep historical overtime visible even if current validation disallows it.
                    'compact_leave' => in_array($first['attendance_code'], config('timesheet.leave_attendance_codes', []), true)
                        && ! $rows->contains(fn (array $row) => (float) $row['overtime_hours'] !== 0.0),
                    'project_code' => $first['project_code'],
                    'weeks' => $weeks,
                    'employees' => $employees,
                    'week_totals' => $this->weekTotals($rows, $weeks),
                    'regular_hours' => $rows->sum('regular_hours'),
                    'overtime_hours' => $rows->sum('overtime_hours'),
                    'total_hours' => $rows->sum('total_hours'),
                ];
            })
            ->sortBy([
                ['attendance_code', 'asc'],
                ['project_code', 'asc'],
            ])
            ->values();
    }

    private function weeks(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $this->weekKey($row))
            ->map(function (Collection $weekRows) {
                $first = $weekRows->first();

                return [
                    'key' => $this->weekKey($first),
                    'number' => $first['week_number'],
                    'year' => $first['year'],
                    'label' => $first['period_label'] ?? 'Week '.$first['week_number'].', '.$first['year'],
                    'dates' => $first['week_start']->format('d-M-y').' to '.$first['week_end']->format('d-M-y'),
                ];
            })
            ->sortBy([
                ['year', 'asc'],
                ['number', 'asc'],
            ])
            ->values();
    }

    private function employees(Collection $rows, Collection $weeks): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => implode('|', [
                $row['employee_id'],
                $row['initials'],
                $row['employee_name'],
                $row['department_name'],
                $row['job_title'],
                $row['status'],
            ]))
            ->map(function (Collection $employeeRows) use ($weeks) {
                $first = $employeeRows->first();
                $hoursByWeek = $employeeRows->keyBy(fn (array $row) => $this->weekKey($row));

                return [
                    'employee_id' => $first['employee_id'],
                    'initials' => $first['initials'],
                    'employee_name' => $first['employee_name'],
                    'department_name' => $first['department_name'],
                    'job_title' => $first['job_title'],
                    'status' => $first['status'],
                    'weeks' => $weeks->mapWithKeys(function (array $week) use ($hoursByWeek) {
                        $row = $hoursByWeek->get($week['key']);

                        return [$week['key'] => [
                            'regular_hours' => $row['regular_hours'] ?? 0,
                            'overtime_hours' => $row['overtime_hours'] ?? 0,
                            'total_hours' => $row['total_hours'] ?? 0,
                        ]];
                    }),
                    'regular_hours' => $employeeRows->sum('regular_hours'),
                    'overtime_hours' => $employeeRows->sum('overtime_hours'),
                    'total_hours' => $employeeRows->sum('total_hours'),
                ];
            })
            ->sortBy($this->summarySort === 'name'
                ? fn (array $employee) => mb_strtolower($employee['employee_name'])
                : [
                    ['total_hours', 'desc'],
                    ['employee_name', 'asc'],
                ])
            ->values();
    }

    private function weekTotals(Collection $rows, Collection $weeks): Collection
    {
        $rowsByWeek = $rows->groupBy(fn (array $row) => $this->weekKey($row));

        return $weeks->mapWithKeys(function (array $week) use ($rowsByWeek) {
            $weekRows = $rowsByWeek->get($week['key'], collect());

            return [$week['key'] => [
                'regular_hours' => $weekRows->sum('regular_hours'),
                'overtime_hours' => $weekRows->sum('overtime_hours'),
                'total_hours' => $weekRows->sum('total_hours'),
            ]];
        });
    }

    private function totalColumns(): int
    {
        $weeks = $this->weeks($this->rows);
        $weekCount = $weeks->count() ?: 1;
        $rangeTotalColumns = $this->showRangeTotals($weeks) ? $this->rangeTotalColumnCount() : 0;

        return max(10, $this->fixedColumnCount() + ($weekCount * $this->periodColumnCount()) + $rangeTotalColumns);
    }

    private function groupLastColumn(array $group): string
    {
        $periodCount = $group['weeks']->count() + ($this->showRangeTotals($group['weeks']) ? 1 : 0);

        return Coordinate::stringFromColumnIndex(
            $this->fixedColumnCount() + $periodCount * $this->periodColumnCount($group['compact_leave'])
        );
    }

    private function lastColumn(): string
    {
        return Coordinate::stringFromColumnIndex($this->totalColumns());
    }

    private function lastRow(): int
    {
        if ($this->rows->isEmpty()) {
            return 4;
        }

        return 4 + $this->groups()->sum(fn (array $group) => $group['employees']->count() + 5);
    }

    private function attendanceHeaderRows(): array
    {
        $rows = [];
        $rowNumber = 3;

        foreach ($this->groups() as $group) {
            $rows[] = $rowNumber;
            $rowNumber += $group['employees']->count() + 5;
        }

        return $rows;
    }

    private function weekHeaderRows(): array
    {
        $rows = [];
        $rowNumber = 4;

        foreach ($this->groups() as $group) {
            $rows[] = $rowNumber;
            $rowNumber += $group['employees']->count() + 5;
        }

        return $rows;
    }

    private function tableHeaderRows(): array
    {
        $rows = [];
        $rowNumber = 5;

        foreach ($this->groups() as $group) {
            $rows[] = $rowNumber;
            $rowNumber += $group['employees']->count() + 5;
        }

        return $rows;
    }

    private function attendanceTotalRows(): array
    {
        $rows = [];
        $rowNumber = 3;

        foreach ($this->groups() as $group) {
            $rows[] = $rowNumber + $group['employees']->count() + 3;
            $rowNumber += $group['employees']->count() + 5;
        }

        return $rows;
    }

    private function attendanceTableRanges(): array
    {
        $ranges = [];
        $rowNumber = 3;

        foreach ($this->groups() as $group) {
            $ranges[] = [
                $rowNumber,
                $rowNumber + $group['employees']->count() + 3,
            ];
            $rowNumber += $group['employees']->count() + 5;
        }

        return $ranges;
    }

    private function spacerRows(): array
    {
        $rows = [2];
        $rowNumber = 3;

        foreach ($this->groups() as $group) {
            $rows[] = $rowNumber + $group['employees']->count() + 4;
            $rowNumber += $group['employees']->count() + 5;
        }

        return $rows;
    }

    private function weekColumnRanges(bool $compactLeave = false): array
    {
        $ranges = [];
        $weekCount = $this->weeks($this->rows)->count();

        for ($column = $this->firstPeriodColumnIndex(); $column < $this->firstPeriodColumnIndex() + ($weekCount * $this->periodColumnCount($compactLeave)); $column += $this->periodColumnCount($compactLeave)) {
            $ranges[] = [
                Coordinate::stringFromColumnIndex($column),
                Coordinate::stringFromColumnIndex($column + $this->periodColumnCount($compactLeave) - 1),
            ];
        }

        if ($range = $this->selectedPeriodTotalColumnRange($compactLeave)) {
            $ranges[] = [
                $range[0],
                $range[1],
            ];
        }

        return $ranges;
    }

    private function selectedPeriodTotalColumnRange(bool $compactLeave = false): ?array
    {
        $weeks = $this->weeks($this->rows);

        if (! $this->showRangeTotals($weeks)) {
            return null;
        }

        $startColumnIndex = $this->firstPeriodColumnIndex() + ($weeks->count() * $this->periodColumnCount($compactLeave));

        return [
            Coordinate::stringFromColumnIndex($startColumnIndex),
            Coordinate::stringFromColumnIndex($startColumnIndex + $this->periodColumnCount($compactLeave) - 1),
        ];
    }

    private function styleSelectedPeriodTotalColumns($sheet): void
    {
        foreach ($this->attendanceTableRanges() as $groupIndex => [$startRow, $endRow]) {
            $range = $this->selectedPeriodTotalColumnRange($this->groups()[$groupIndex]['compact_leave']);
            if (! $range) {
                continue;
            }
            [$startColumn, $endColumn] = $range;
            $weekHeaderRow = $startRow + 1;
            $tableHeaderRow = $startRow + 2;
            $firstBodyRow = $startRow + 3;
            $attendanceTotalRow = $endRow;

            $sheet->getStyle("{$startColumn}{$weekHeaderRow}:{$endColumn}{$tableHeaderRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8CBAD']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            if ($firstBodyRow < $attendanceTotalRow) {
                $sheet->getStyle("{$startColumn}{$firstBodyRow}:{$endColumn}".($attendanceTotalRow - 1))->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFCE4D6']],
                ]);
            }

            $sheet->getStyle("{$startColumn}{$attendanceTotalRow}:{$endColumn}{$attendanceTotalRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8DFD0']],
            ]);
        }
    }

    private function showRangeTotals(Collection $weeks): bool
    {
        return $weeks->count() > 1;
    }

    private function showCosting(): bool
    {
        return $this->showCosting ?? ($this->reportMode === 'monthly');
    }

    private function periodColumnCount(bool $compactLeave = false): int
    {
        if ($compactLeave) {
            return $this->showCosting() ? 2 : 1;
        }

        return $this->showCosting() ? 6 : 3;
    }

    private function rangeTotalColumnCount(): int
    {
        return $this->showCosting() ? 6 : 3;
    }

    private function fixedColumnCount(): int
    {
        return $this->showCosting() ? 8 : 7;
    }

    private function firstPeriodColumnIndex(): int
    {
        return $this->fixedColumnCount() + 1;
    }

    private function styleCostColumns($sheet): void
    {
        if (! $this->showCosting()) {
            return;
        }

        $ranges = $this->attendanceTableRanges();
        foreach ($this->groups() as $index => $group) {
            [$startRow, $endRow] = $ranges[$index];
            $span = $this->periodColumnCount($group['compact_leave']);
            $count = $group['weeks']->count() + ($this->showRangeTotals($group['weeks']) ? 1 : 0);
            for ($period = 0; $period < $count; $period++) {
                $start = $this->firstPeriodColumnIndex() + $period * $span;
                $offsets = $group['compact_leave'] ? [1] : [3, 4, 5];
                foreach ($offsets as $offset) {
                    $column = Coordinate::stringFromColumnIndex($start + $offset);
                    $sheet->getStyle("{$column}{$startRow}:{$column}{$endRow}")
                        ->getNumberFormat()->setFormatCode('"AED" #,##0.00');
                }
            }
        }
        foreach ($this->weekColumnRanges() as [$startColumn, $endColumn]) {
            $start = Coordinate::columnIndexFromString($startColumn);
            foreach ([3, 4, 5] as $offset) {
                $column = Coordinate::stringFromColumnIndex($start + $offset);
                $row = $this->lastRow();
                $sheet->getStyle("{$column}{$row}")->getNumberFormat()->setFormatCode('"AED" #,##0.00');
            }
        }
    }

    private function weekKey(array $row): string
    {
        if (isset($row['period_key'])) {
            return $row['period_key'];
        }

        return $row['year'].'-'.$row['week_number'].'-'.$row['week_start']->toDateString();
    }
}
