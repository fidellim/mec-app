<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeRatesWorksheetExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Employee Rates';
    }

    public function view(): View
    {
        return view('exports.employee_rates_excel', [
            'rows' => $this->rows,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 12,
            'C' => 30,
            'D' => 28,
            'E' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(2, $this->rows->count() + 1);

                $sheet->getDefaultRowDimension()->setRowHeight(20);
                $sheet->getStyle("A1:E{$lastRow}")->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:E{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:E{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A1:E{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);
                $sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEBF1DE']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("E2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->freezePane('A2');
            },
        ];
    }
}
