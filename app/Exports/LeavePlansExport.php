<?php

namespace App\Exports;

use App\Models\LeavePlan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeavePlansExport implements FromQuery, ShouldAutoSize, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
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
            'Leave Plan ID',
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
            'Final Approved By',
            'Final Approved At',
            'Rejected By',
            'Rejected At',
            'Rejection Comment',
            'Cancellation Requested At',
            'Cancellation Reason',
            'Cancelled By',
            'Cancelled At',
            'Cancellation Rejection Comment',
            'Recalled By',
            'Recalled At',
            'Recall Reason',
            'Voided By',
            'Voided At',
            'Void Reason',
            'Employee Reason',
            'Created At',
            'Updated At',
        ];
    }

    public function map($leavePlan): array
    {
        /** @var LeavePlan $leavePlan */
        $attendanceCodes = config('timesheet.attendance_codes', []);

        return [
            $leavePlan->id,
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
            $this->cell($leavePlan->approver?->name),
            $this->dateTime($leavePlan->approved_at),
            $this->cell($leavePlan->rejector?->name),
            $this->dateTime($leavePlan->rejected_at),
            $this->cell($leavePlan->rejection_comment),
            $this->dateTime($leavePlan->cancellation_requested_at),
            $this->cell($leavePlan->cancellation_reason),
            $this->cell($leavePlan->canceller?->name),
            $this->dateTime($leavePlan->cancelled_at),
            $this->cell($leavePlan->cancellation_rejection_comment),
            $this->cell($leavePlan->recaller?->name),
            $this->dateTime($leavePlan->recalled_at),
            $this->cell($leavePlan->recall_reason),
            $this->cell($leavePlan->voider?->name),
            $this->dateTime($leavePlan->voided_at),
            $this->cell($leavePlan->void_reason),
            $this->cell($leavePlan->reason),
            $this->dateTime($leavePlan->created_at),
            $this->dateTime($leavePlan->updated_at),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 26,
            'C' => 18,
            'D' => 22,
            'E' => 22,
            'F' => 16,
            'G' => 24,
            'N' => 28,
            'Z' => 42,
            'AB' => 42,
            'AE' => 42,
            'AH' => 42,
            'AK' => 42,
            'AL' => 42,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getStyle('A:AN')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('Z:Z')->getAlignment()->setWrapText(true);
        $sheet->getStyle('AB:AB')->getAlignment()->setWrapText(true);
        $sheet->getStyle('AE:AE')->getAlignment()->setWrapText(true);
        $sheet->getStyle('AH:AH')->getAlignment()->setWrapText(true);
        $sheet->getStyle('AK:AL')->getAlignment()->setWrapText(true);

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
