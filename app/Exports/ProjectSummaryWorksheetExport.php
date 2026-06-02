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

class ProjectSummaryWorksheetExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Project Weekly Summary';
    }

    public function view(): View
    {
        $weeks = $this->weeks($this->rows);

        return view('exports.project_summary_excel', [
            'groups' => $this->groups(),
            'weeks' => $weeks,
            'showRangeTotals' => $this->showRangeTotals($weeks),
            'grandTotalsByWeek' => $this->weekTotals($this->rows, $weeks),
            'totalColumns' => $this->totalColumns(),
            'totalRegular' => $this->rows->sum('regular_hours'),
            'totalOvertime' => $this->rows->sum('overtime_hours'),
            'totalHours' => $this->rows->sum('total_hours'),
        ]);
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 22,
            'B' => 12,
            'C' => 34,
            'D' => 28,
        ];

        for ($column = 5; $column <= $this->totalColumns(); $column++) {
            $widths[Coordinate::stringFromColumnIndex($column)] = 15;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
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

                foreach ($this->projectTableRanges() as [$startRow, $endRow]) {
                    $sheet->getStyle("A{$startRow}:{$lastColumn}{$endRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ],
                    ]);
                }

                $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle("E3:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F4']],
                ]);

                foreach ($this->projectHeaderRows() as $rowNumber => $height) {
                    $sheet->mergeCells("A{$rowNumber}:{$lastColumn}{$rowNumber}");
                    $sheet->getRowDimension($rowNumber)->setRowHeight($height);
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F6228']],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_TOP,
                        ],
                    ]);
                }

                foreach ($this->weekHeaderRows() as $rowNumber) {
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

                    foreach ($this->weekColumnRanges() as [$startColumn, $endColumn]) {
                        $sheet->mergeCells("{$startColumn}{$rowNumber}:{$endColumn}{$rowNumber}");
                    }
                }

                foreach ($this->tableHeaderRows() as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEBF1DE']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach ($this->projectTotalRows() as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                    ]);
                }

                $this->styleSelectedPeriodTotalColumns($sheet);

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

    private function styleSelectedPeriodTotalColumns($sheet): void
    {
        $range = $this->selectedPeriodTotalColumnRange();

        if (! $range) {
            return;
        }

        [$startColumn, $endColumn] = $range;

        foreach ($this->projectTableRanges() as [$startRow, $endRow]) {
            $weekHeaderRow = $startRow + 1;
            $tableHeaderRow = $startRow + 2;
            $firstBodyRow = $startRow + 3;
            $projectTotalRow = $endRow;

            $sheet->getStyle("{$startColumn}{$weekHeaderRow}:{$endColumn}{$tableHeaderRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8CBAD']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            if ($firstBodyRow < $projectTotalRow) {
                $sheet->getStyle("{$startColumn}{$firstBodyRow}:{$endColumn}".($projectTotalRow - 1))->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFCE4D6']],
                ]);
            }

            $sheet->getStyle("{$startColumn}{$projectTotalRow}:{$endColumn}{$projectTotalRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8DFD0']],
            ]);

            $sheet->getStyle("{$startColumn}{$weekHeaderRow}:{$startColumn}{$projectTotalRow}")->applyFromArray([
                'borders' => [
                    'left' => [
                        'borderStyle' => Border::BORDER_THICK,
                        'color' => ['argb' => 'FFC65911'],
                    ],
                ],
            ]);
        }

        $lastRow = $this->lastRow();
        $sheet->getStyle("{$startColumn}{$lastRow}:{$endColumn}{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8DFD0']],
        ]);
        $sheet->getStyle("{$startColumn}{$lastRow}:{$startColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'left' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['argb' => 'FFC65911'],
                ],
            ],
        ]);
    }

    private function groups(): Collection
    {
        return $this->rows
            ->groupBy('project_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $weeks = $this->weeks($this->rows);
                $employees = $this->employees($rows, $weeks);

                return [
                    'project_code' => $first['project_code'],
                    'project_name' => $this->wrapCellText((string) $first['project_name'], 96),
                    'client_name' => $first['client_name'],
                    'weeks' => $weeks,
                    'employees' => $employees,
                    'week_totals' => $this->weekTotals($rows, $weeks),
                    'regular_hours' => $rows->sum('regular_hours'),
                    'overtime_hours' => $rows->sum('overtime_hours'),
                    'total_hours' => $rows->sum('total_hours'),
                ];
            })
            ->sortBy('project_code')
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
                    'label' => 'Week '.$first['week_number'].', '.$first['year'],
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
                $row['job_title'],
            ]))
            ->map(function (Collection $employeeRows) use ($weeks) {
                $first = $employeeRows->first();
                $hoursByWeek = $employeeRows->keyBy(fn (array $row) => $this->weekKey($row));

                return [
                    'employee_id' => $first['employee_id'],
                    'initials' => $first['initials'],
                    'employee_name' => $first['employee_name'],
                    'job_title' => $first['job_title'],
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
            ->sortBy([
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
        $maxWeekCount = $weeks->count() ?: 1;
        $rangeTotalColumns = $this->showRangeTotals($weeks) ? 3 : 0;

        return max(7, 4 + ($maxWeekCount * 3) + $rangeTotalColumns);
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

        return 3 + $this->groups()->sum(fn (array $group) => $group['employees']->count() + 5);
    }

    private function projectHeaderRows(): array
    {
        $rows = [];
        $rowNumber = 3;

        foreach ($this->groups() as $group) {
            $lineCount = substr_count((string) $group['project_name'], "\n") + 1;
            $clientLine = $group['client_name'] ? 1 : 0;
            $rows[$rowNumber] = max(32, ($lineCount + $clientLine) * 16);
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

    private function projectTotalRows(): array
    {
        $rows = [];
        $rowNumber = 3;

        foreach ($this->groups() as $group) {
            $rows[] = $rowNumber + $group['employees']->count() + 3;
            $rowNumber += $group['employees']->count() + 5;
        }

        return $rows;
    }

    private function projectTableRanges(): array
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

    private function weekColumnRanges(): array
    {
        $ranges = [];
        $groupCount = $this->weeks($this->rows)->count();

        for ($column = 5; $column < 5 + ($groupCount * 3); $column += 3) {
            $ranges[] = [
                Coordinate::stringFromColumnIndex($column),
                Coordinate::stringFromColumnIndex($column + 2),
            ];
        }

        if ($range = $this->selectedPeriodTotalColumnRange()) {
            $ranges[] = [
                $range[0],
                $range[1],
            ];
        }

        return $ranges;
    }

    private function selectedPeriodTotalColumnRange(): ?array
    {
        $weeks = $this->weeks($this->rows);

        if (! $this->showRangeTotals($weeks)) {
            return null;
        }

        $startColumnIndex = 5 + ($weeks->count() * 3);

        return [
            Coordinate::stringFromColumnIndex($startColumnIndex),
            Coordinate::stringFromColumnIndex($startColumnIndex + 2),
        ];
    }

    private function showRangeTotals(Collection $weeks): bool
    {
        return $weeks->count() > 1;
    }

    private function weekKey(array $row): string
    {
        return $row['year'].'-'.$row['week_number'].'-'.$row['week_start']->toDateString();
    }

    private function wrapCellText(string $value, int $width): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return wordwrap($value, $width, "\n", false);
    }
}
