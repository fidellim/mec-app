<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditLogsExport implements FromQuery, ShouldAutoSize, WithColumnWidths, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    private const MAX_CELL_LENGTH = 32000;

    public function __construct(private readonly Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Date',
            'User',
            'User Email',
            'Action',
            'Record Type',
            'Record ID',
            'Old Values',
            'New Values',
            'IP Address',
        ];
    }

    public function map($log): array
    {
        /** @var \App\Models\AuditLog $log */
        return [
            $this->cell($log->created_at?->format('Y-m-d H:i:s')),
            $this->cell($log->user?->name ?? 'System'),
            $this->cell($log->user?->email),
            $this->cell($log->action),
            $this->cell(class_basename($log->auditable_type) ?: null),
            $log->auditable_id,
            $this->cell($this->encodeJson($log->old_values)),
            $this->cell($this->encodeJson($log->new_values)),
            $this->cell($log->ip_address),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 24,
            'C' => 30,
            'D' => 30,
            'E' => 20,
            'F' => 12,
            'G' => 55,
            'H' => 55,
            'I' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A:I')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('G:H')->getAlignment()->setWrapText(true);
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2F3A4A'],
                ],
            ],
        ];
    }

    private function encodeJson(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '[Unable to encode values]' : $json;
    }

    private function cell(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_strlen($value) > self::MAX_CELL_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_CELL_LENGTH).'... [truncated]';
        }

        return preg_match('/^[=\-+@]/', $value) ? "'".$value : $value;
    }
}
