<?php

namespace App\Exports;

use App\Models\LeavePlan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeavePlansExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
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

    public function title(): string
    {
        return 'Leave Plans';
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Employee Code',
            'Job Title',
            'Department',
            'Leave Type Code',
            'Leave Type',
            'Start Date',
            'End Date',
            'Duration Type',
            'Half Day Period',
            'Counted Leave Days',
            'Status',
            'Approval Stage / Progress',
            'Submitted At',
            'HOD Approved By',
            'HOD Approved At',
            'Director Approved By',
            'Director Approved At',
            'HR Approved By',
            'HR Approved At',
        ];
    }

    public function map($leavePlan): array
    {
        /** @var LeavePlan $leavePlan */
        $attendanceCodes = config('timesheet.attendance_codes', []);

        return [
            $this->cell($leavePlan->user?->name),
            $this->cell($leavePlan->user?->employee_code),
            $this->cell($leavePlan->user?->job_title),
            $this->cell($leavePlan->department?->name),
            $this->cell($leavePlan->attendance_code),
            $this->cell($attendanceCodes[$leavePlan->attendance_code] ?? $leavePlan->attendance_code),
            $this->date($leavePlan->start_date),
            $this->date($leavePlan->end_date),
            $this->cell(str_replace('_', ' ', ucfirst((string) $leavePlan->duration_type))),
            $this->cell($leavePlan->half_day_period),
            $leavePlan->countedLeaveDayCount(),
            $this->cell($leavePlan->status),
            $this->cell($leavePlan->approvalProgressLabel()),
            $this->dateTime($leavePlan->submitted_at),
            $this->cell($leavePlan->hodApprover?->name),
            $this->dateTime($leavePlan->hod_approved_at),
            $this->cell($leavePlan->directorApprover?->name),
            $this->dateTime($leavePlan->director_approved_at),
            $this->cell($leavePlan->hrApprover?->name),
            $this->dateTime($leavePlan->hr_approved_at),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getStyle('A:T')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('M:M')->getAlignment()->setWrapText(true);

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

    private function date($value): ?string
    {
        return $value ? $value->format('Y-m-d') : null;
    }

    private function dateTime($value): ?string
    {
        return $value ? $value->format('Y-m-d H:i:s') : null;
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
