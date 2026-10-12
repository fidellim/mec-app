<?php

namespace Tests\Feature;

use App\Exports\TimesheetsExcelExport;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendanceSummaryLayoutTest extends TestCase
{
    public static function layouts(): array
    {
        return [
            'weekly without costing' => [false, 'weekly', 1],
            'weekly with costing' => [true, 'weekly', 1],
            'week range without costing' => [false, 'weekly', 2],
            'week range with costing' => [true, 'weekly', 2],
            'monthly without costing' => [false, 'monthly', 1],
            'monthly with costing' => [true, 'monthly', 1],
            'month range without costing' => [false, 'monthly', 2],
            'month range with costing' => [true, 'monthly', 2],
        ];
    }

    #[DataProvider('layouts')]
    public function test_leave_sections_are_compact_and_mixed_totals_reconcile(bool $costing, string $mode, int $periodCount): void
    {
        $rows = collect();
        foreach (config('timesheet.attendance_codes') as $code => $label) {
            for ($period = 0; $period < $periodCount; $period++) {
                $leave = in_array($code, config('timesheet.leave_attendance_codes'), true);
                $rows->push([
                    'attendance_code' => $code, 'attendance_label' => $label,
                    'project_code' => 'Non-project', 'employee_id' => 'EMP-TEST',
                    'initials' => 'TU', 'employee_name' => 'Test User',
                    'department_name' => 'Engineering', 'job_title' => 'Engineer', 'status' => 'approved',
                    'week_number' => 20 + $period, 'year' => 2026,
                    'period_key' => $mode.'-'.$period,
                    'period_label' => $mode === 'monthly'
                        ? Carbon::parse('2026-05-01')->addMonths($period)->format('F Y')
                        : 'Week '.(20 + $period).', 2026',
                    'week_start' => Carbon::parse('2026-05-11')->addWeeks($period),
                    'week_end' => Carbon::parse('2026-05-17')->addWeeks($period),
                    'regular_hours' => 8, 'overtime_hours' => $leave ? 0 : 2,
                    'total_hours' => $leave ? 8 : 10,
                ]);
            }
        }
        $bytes = Excel::raw(new TimesheetsExcelExport(
            collect(), attendanceSummaryRows: $rows, reportMode: $mode, showCosting: $costing
        ), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'attendance-layout-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getSheet(1);
            $row = 3;
            $first = $costing ? 9 : 8;
            foreach ($rows->pluck('attendance_code')->unique()->sort() as $code) {
                $leave = in_array($code, config('timesheet.leave_attendance_codes'), true);
                $span = $leave ? ($costing ? 2 : 1) : ($costing ? 6 : 3);
                $this->assertStringContainsString($mode === 'monthly' ? 'May 2026' : 'Week 20, 2026', $sheet->getCell([$first, $row + 1])->getValue());
                if ($span > 1) {
                    $this->assertContains(Coordinate::stringFromColumnIndex($first).($row + 1).':'.Coordinate::stringFromColumnIndex($first + $span - 1).($row + 1), $sheet->getMergeCells());
                }
                $this->assertSame($leave ? 'Leave Hours' : 'Regular Hours', $sheet->getCell([$first, $row + 2])->getValue());
                $this->assertEquals(8, $sheet->getCell([$first, $row + 3])->getCalculatedValue());
                if (! $leave) {
                    $this->assertSame('Overtime Hours', $sheet->getCell([$first + 1, $row + 2])->getValue());
                    $this->assertEquals(2, $sheet->getCell([$first + 1, $row + 3])->getCalculatedValue());
                }
                if ($costing) {
                    $sheet->setCellValue('F'.($row + 3), 20);
                    $costOffset = $leave ? 1 : 5;
                    $this->assertEquals($leave ? 160 : 210, $sheet->getCell([$first + $costOffset, $row + 3])->getCalculatedValue());
                    if ($leave) {
                        $this->assertSame('Leave Cost', $sheet->getCell([$first + 1, $row + 2])->getValue());
                        $this->assertSame('"AED" #,##0.00', $sheet->getStyle(Coordinate::stringFromColumnIndex($first + 1).($row + 3))->getNumberFormat()->getFormatCode());
                        $this->assertSame('General', $sheet->getStyle(Coordinate::stringFromColumnIndex($first).($row + 3))->getNumberFormat()->getFormatCode());
                    }
                }
                if ($periodCount > 1) {
                    $selected = $first + $periodCount * $span;
                    $this->assertSame('Selected Period Total', $sheet->getCell([$selected, $row + 1])->getValue());
                    $this->assertEquals(16, $sheet->getCell([$selected, $row + 3])->getCalculatedValue());
                    if ($costing) {
                        $this->assertEquals($leave ? 320 : 420, $sheet->getCell([$selected + ($leave ? 1 : 5), $row + 3])->getCalculatedValue());
                    }
                }
                $row += 6;
            }
            $this->assertSame('Overall Totals', $sheet->getCell('A'.$row)->getValue());
            $row++;
            $this->assertSame('Grand Total', $sheet->getCell('A'.$row)->getValue());
            $this->assertEquals(120, $sheet->getCell([$first, $row])->getCalculatedValue());
            $this->assertEquals(6, $sheet->getCell([$first + 1, $row])->getCalculatedValue());
            if ($costing) {
                $this->assertEquals(2400, $sheet->getCell([$first + 3, $row])->getCalculatedValue());
                $this->assertEquals(150, $sheet->getCell([$first + 4, $row])->getCalculatedValue());
                $this->assertEquals(2550, $sheet->getCell([$first + 5, $row])->getCalculatedValue());
                if ($periodCount > 1) {
                    $this->assertEquals(5100, $sheet->getCell([$first + 2 * 6 + 5, $row])->getCalculatedValue());
                }
            }
        } finally {
            unlink($path);
        }
    }
}
