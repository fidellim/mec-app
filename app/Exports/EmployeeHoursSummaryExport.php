<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeHoursSummaryExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    public function __construct(private readonly array $summary) {}

    public function title(): string
    {
        return 'Employee Hours Summary';
    }

    public function view(): View
    {
        return view('exports.employee_hours_summary_excel', [
            ...$this->summary,
            'totalColumns' => $this->totalColumns(),
        ]);
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 22,
            'B' => 22,
            'C' => 28,
            'D' => 24,
            'E' => 24,
        ];

        for ($column = 6; $column <= $this->totalColumns(); $column++) {
            $widths[Coordinate::stringFromColumnIndex($column)] = 18;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex($this->totalColumns());
                $lastRow = max(5, 4 + $this->summary['employees']->count());

                $sheet->getDefaultRowDimension()->setRowHeight(22);
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(22);
                $sheet->getRowDimension(3)->setRowHeight(38);
                $sheet->getRowDimension(4)->setRowHeight(34);
                for ($row = 5; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 15],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['argb' => 'FF475569']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle("A3:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFCBD5E1'],
                        ],
                    ],
                ]);
                $sheet->getStyle("A3:{$lastColumn}4")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD8E4BC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                foreach ($this->periodColumnRanges() as [$startColumn, $endColumn]) {
                    $sheet->mergeCells("{$startColumn}3:{$endColumn}3");
                }

                if ($this->summary['mode'] === 'weekly') {
                    [$startColumn, $endColumn] = $this->selectedWeeksTotalColumnRange();
                    $sheet->mergeCells("{$startColumn}3:{$endColumn}3");
                    $sheet->getStyle("{$startColumn}3:{$endColumn}4")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8DFD0']],
                    ]);
                    $sheet->getStyle("{$startColumn}3:{$startColumn}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'left' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['argb' => 'FFC65911'],
                            ],
                        ],
                    ]);
                }

                if ($this->summary['employees']->isNotEmpty()) {
                    $sheet->getStyle("F5:{$lastColumn}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.00');
                }

                $sheet->freezePane('F5');
                $sheet->setAutoFilter("A4:{$lastColumn}4");
                $sheet->getStyle("F5:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    private function totalColumns(): int
    {
        $periodColumns = $this->summary['periods']->count() * 3;
        $selectedWeeksTotalColumns = $this->summary['mode'] === 'weekly' ? 3 : 0;

        return 5 + $periodColumns + $selectedWeeksTotalColumns;
    }

    private function periodColumnRanges(): array
    {
        return $this->summary['periods']
            ->values()
            ->map(function (array $period, int $index) {
                $start = 6 + ($index * 3);

                return [
                    Coordinate::stringFromColumnIndex($start),
                    Coordinate::stringFromColumnIndex($start + 2),
                ];
            })
            ->all();
    }

    private function selectedWeeksTotalColumnRange(): array
    {
        $start = 6 + ($this->summary['periods']->count() * 3);

        return [
            Coordinate::stringFromColumnIndex($start),
            Coordinate::stringFromColumnIndex($start + 2),
        ];
    }
}
