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

class AttendanceSummaryWorksheetExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Attendance Code Summary';
    }

    public function view(): View
    {
        return view('exports.attendance_summary_excel', [
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
                $sheet->getStyle("A1:N{$lastRow}")->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:N{$lastRow}")->getAlignment()->setWrapText(true);

                $sheet->mergeCells('A1:N1');
                $sheet->getStyle('A1:N1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle("A3:N{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A3:N3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF111827']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle("K4:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$lastRow}:N{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                ]);
            },
        ];
    }
}
