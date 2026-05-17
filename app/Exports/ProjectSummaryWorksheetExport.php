<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectSummaryWorksheetExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Project Summary';
    }

    public function view(): View
    {
        return view('exports.project_summary_excel', [
            'rows' => $this->rows,
            'totalRegular' => $this->rows->sum('regular_hours'),
            'totalOvertime' => $this->rows->sum('overtime_hours'),
            'totalHours' => $this->rows->sum('total_hours'),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, $this->rows->count() + 4);

                $sheet->getDefaultRowDimension()->setRowHeight(20);
                $sheet->getStyle("A1:F{$lastRow}")->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:F{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A3:F3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E258B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle("A3:F{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle("D4:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$lastRow}:F{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F4']],
                ]);

                foreach (['A' => 16, 'B' => 45, 'C' => 22, 'D' => 16, 'E' => 16, 'F' => 16] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }
}
