<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use ZipArchive;

class HistoricalTimesheetWorkbookReader
{
    /** Read saved cell results only. Never evaluate workbook formulas or run macros. */
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This is not a readable Excel workbook.');
        }
        try {
            $bytes = 0;
            if ($zip->numFiles > 2000) {
                throw new RuntimeException('The workbook contains too many embedded files.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $bytes += $zip->statIndex($i)['size'];
                if ($bytes > 32 * 1024 * 1024) {
                    throw new RuntimeException('The expanded workbook exceeds the 32 MB limit.');
                }
            }
        } finally {
            $zip->close();
        }

        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Employee Time Record']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= 110 && strlen($columnAddress) === 1 && $columnAddress <= 'X';
            }
        });
        $calendar = Date::getExcelCalendar();
        Date::setExcelCalendar(Date::CALENDAR_WINDOWS_1900);
        $book = null;
        try {
            $book = $reader->load($path);
            $sheet = $book->getSheetByName('Employee Time Record');
            if (! $sheet || trim((string) $sheet->getCell('B15')->getValue()) !== 'Project No.' || trim((string) $sheet->getCell('C15')->getValue()) !== 'Code') {
                throw new RuntimeException('Use the Employee Time Record template with project and attendance headers in row 15.');
            }
            $value = function (string $address) use ($sheet) {
                $cell = $sheet->getCell($address);
                $result = $cell->getDataType() === DataType::TYPE_FORMULA ? $cell->getOldCalculatedValue() : $cell->getValue();
                if ($cell->getDataType() === DataType::TYPE_ERROR || ($cell->getDataType() === DataType::TYPE_FORMULA && $result === null)) {
                    throw new RuntimeException("Cell $address has no usable saved value. Open the workbook in Excel, recalculate, save, and upload again.");
                }
                if (is_string($result) && mb_strlen($result) > 2000) {
                    throw new RuntimeException("Cell $address exceeds the 2,000 character limit. Shorten it in Excel before uploading again.");
                }

                return is_string($result) ? trim($result) : $result;
            };
            $employeeValue = $value('Q8');
            $employeeCode = (string) $employeeValue;
            $serial = $value('C5');
            if (! is_string($employeeValue) || $employeeCode === '' || ! is_numeric($serial) || $serial < 36526 || $serial > 73050) {
                throw new RuntimeException('A valid employee code in Q8 and weekly start date in C5 are required.');
            }
            // Honour the workbook date system explicitly; do not depend on another reader's global state.
            $start = CarbonImmutable::instance(Date::excelToDateTimeObject((float) $serial));
            if (! $start->isMonday()) {
                throw new RuntimeException('The weekly start date must be a Monday.');
            }
            $entries = [];
            $foundTotal = false;
            $columns = [['D', 'E'], ['F', 'G'], ['H', 'I'], ['J', 'K'], ['L', 'M'], ['O', null], ['P', null]];
            for ($row = 17; $row <= 100; $row++) {
                $code = (string) $value('C'.$row);
                if (strtoupper($code) === 'TOTAL') {
                    $foundTotal = true;
                    break;
                }
                foreach ($columns as $day => [$regularColumn, $overtimeColumn]) {
                    $regular = $value($regularColumn.$row);
                    $overtime = $overtimeColumn ? $value($overtimeColumn.$row) : null;
                    if (($regular === null || $regular === '' || $regular === 0 || $regular === 0.0) && ($overtime === null || $overtime === '' || $overtime === 0 || $overtime === 0.0)) {
                        continue;
                    }
                    $project = (string) $value('B'.$row);
                    $entries[] = [
                        'source_row' => $row,
                        'work_date' => $start->addDays($day)->toDateString(),
                        'attendance_code' => $code === '-' ? '' : $code,
                        'project_code' => $project === '-' ? '' : $project,
                        'department_id' => null,
                        'manpower_category' => null,
                        'regular_hours' => $regular === null || $regular === '' ? 0 : $regular,
                        'overtime_hours' => $overtime === null || $overtime === '' ? 0 : $overtime,
                        'remarks' => (string) $value('X'.$row),
                    ];
                }
            }
            if (! $foundTotal || count($entries) > 500) {
                throw new RuntimeException('The timesheet detail area is invalid or exceeds 500 entries.');
            }

            return [
                'employee_code' => $employeeCode,
                'employee_name' => (string) $value('D8'),
                'start_date' => $start->toDateString(),
                'worksheet' => 'Employee Time Record',
                'entries' => $entries,
            ];
        } finally {
            $book?->disconnectWorksheets();
            Date::setExcelCalendar($calendar);
        }
    }
}
