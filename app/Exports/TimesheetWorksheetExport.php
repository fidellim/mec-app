<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TimesheetWorksheetExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        private readonly ?array $worksheet,
        private readonly string $title
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function view(): View
    {
        return view('exports.timesheets_excel', [
            'worksheets' => $this->worksheet ? collect([$this->worksheet]) : collect(),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                if (! $this->worksheet) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();
                $sheet->getDefaultRowDimension()->setRowHeight(18);
                $spreadsheet?->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
                $spreadsheet?->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                foreach (range('A', 'U') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                }

                $widths = [
                    'A' => 11, 'B' => 12,
                    'C' => 6, 'D' => 6, 'E' => 6, 'F' => 6, 'G' => 6, 'H' => 6, 'I' => 6, 'J' => 6, 'K' => 6, 'L' => 6,
                    'M' => 8, 'N' => 8, 'O' => 9, 'P' => 10, 'Q' => 10, 'R' => 9, 'S' => 9, 'T' => 9, 'U' => 36,
                ];

                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $dataStart = 11;
                $dataEnd = $dataStart + $this->worksheet['rows']->count() - 1;
                $totalRow = $dataEnd + 1;

                $this->styleSection($sheet, 1, $dataStart, $dataEnd, $totalRow, $totalRow);
            },
        ];
    }

    private function styleSection($sheet, int $startRow, int $dataStart, int $dataEnd, int $totalRow, int $sectionEnd): void
    {
        $thinBlack = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];

        $sheet->getStyle("A{$startRow}:U{$sectionEnd}")->applyFromArray($thinBlack);
        $sheet->getStyle("A{$startRow}:U{$sectionEnd}")->getAlignment()->setWrapText(true);

        $sheet->getRowDimension($startRow)->setRowHeight(28);
        $sheet->getStyle("A{$startRow}:U{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
        ]);

        $sheet->getStyle('A'.($startRow + 1).':U'.($startRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
        ]);

        $sheet->getStyle('A'.($startRow + 2).':U'.($startRow + 4))->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD9EAFC'],
            ],
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
        ]);

        foreach ([
            'B'.($startRow + 2).':E'.($startRow + 2),
            'K'.($startRow + 2).':M'.($startRow + 2),
            'B'.($startRow + 3).':C'.($startRow + 3),
            'K'.($startRow + 3).':N'.($startRow + 3),
            'B'.($startRow + 4).':C'.($startRow + 4),
        ] as $range) {
            $sheet->getStyle($range)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
                'font' => ['bold' => false],
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
            ]);
        }

        $noteRow = $startRow + 2;
        $sheet->getStyle("N{$noteRow}:U{$noteRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FFFF0000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headerStart = $startRow + 6;
        $headerEnd = $startRow + 9;
        $weekdayNameRow = $headerStart + 2;

        foreach (['C:D', 'E:F', 'G:H', 'I:J', 'K:L'] as $columns) {
            [$startColumn, $endColumn] = explode(':', $columns);
            $sheet->mergeCells("{$startColumn}{$weekdayNameRow}:{$endColumn}{$weekdayNameRow}");
        }

        $sheet->getStyle("A{$headerStart}:U{$headerEnd}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('C'.$headerStart.':L'.$headerStart)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E258B']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        ]);

        $sheet->getStyle("M{$headerStart}:N{$dataEnd}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC9C9C9']],
        ]);

        $sheet->getStyle("Q{$headerStart}:Q{$totalRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E1EA']],
        ]);

        $sheet->getStyle("C{$dataStart}:T{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("U{$dataStart}:U{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->getStyle("A{$totalRow}:U{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F4']],
        ]);

        $sheet->getStyle("S{$dataStart}:S{$totalRow}")->getFont()->getColor()->setARGB('FFFF0000');

        foreach (range($headerStart, $totalRow) as $rowNumber) {
            $sheet->getRowDimension($rowNumber)->setRowHeight($rowNumber < $dataStart ? 22 : 18);
        }

    }
}
