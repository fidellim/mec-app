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

class ProjectSummaryWorksheetExport implements FromView, WithColumnWidths, WithEvents, WithTitle
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

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 62,
            'C' => 22,
            'D' => 16,
            'E' => 16,
            'F' => 16,
        ];
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

                foreach ($this->rows->values() as $index => $row) {
                    $worksheetRow = $index + 4;
                    $projectName = $this->wrapCellText((string) ($row['project_name'] ?? ''), 62);
                    $clientName = $this->wrapCellText((string) ($row['client_name'] ?? ''), 22);
                    $sheet->setCellValue("B{$worksheetRow}", $projectName);
                    $sheet->setCellValue("C{$worksheetRow}", $clientName);

                    $projectLineCount = substr_count($projectName, "\n") + 1;
                    $clientLineCount = substr_count($clientName, "\n") + 1;
                    $lineCount = max(
                        1,
                        $projectLineCount,
                        $clientLineCount,
                    );

                    $sheet->getRowDimension($worksheetRow)->setRowHeight(max(20, $lineCount * 15));
                }

                $sheet->getStyle("B4:C{$lastRow}")->applyFromArray([
                    'alignment' => [
                        'wrapText' => true,
                        'vertical' => Alignment::VERTICAL_TOP,
                    ],
                ]);
            },
        ];
    }

    private function wrapCellText(string $value, int $width): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return wordwrap($value, $width, "\n", false);
    }
}
