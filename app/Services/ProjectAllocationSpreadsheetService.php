<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectAllocationSpreadsheetService
{
    public const MAX_ROWS = 1000;

    private const MAX_HOURS = 9999999999.99;

    private const HEADERS = [
        'Department Code',
        'Department Name',
        'Included',
        'Total Manhours',
        'Control by Manpower Category',
        'Lead Engineer / Checker Mode',
        'Lead Engineer / Checker Reserved Hours',
        'Senior Engineer Mode',
        'Senior Engineer Reserved Hours',
        'Engineer Mode',
        'Engineer Reserved Hours',
        'Designer Mode',
        'Designer Reserved Hours',
    ];

    private const CATEGORY_COLUMNS = [
        'lead_engineer_checker' => ['mode' => 'F', 'hours' => 'G'],
        'senior_engineer' => ['mode' => 'H', 'hours' => 'I'],
        'engineer' => ['mode' => 'J', 'hours' => 'K'],
        'designer' => ['mode' => 'L', 'hours' => 'M'],
    ];

    public function template(?Project $project = null): Spreadsheet
    {
        $allocatedDepartmentIds = $project
            ? $project->departmentAllocations()->pluck('department_id')
            : collect();
        $departments = Department::query()
            ->where('is_active', true)
            ->when($project, fn ($query) => $query->orWhereIn('id', $allocatedDepartmentIds))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);
        $current = $project ? $this->storedState($project) : collect();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Department Allocations');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        foreach ($departments as $index => $department) {
            $rowNumber = $index + 2;
            $state = $current->get($department->id);
            $included = $state !== null;

            $sheet->setCellValueExplicit("A{$rowNumber}", (string) $department->code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit(
                "B{$rowNumber}",
                $this->spreadsheetText($department->name.($department->is_active ? '' : ' (inactive)')),
                DataType::TYPE_STRING,
            );
            $sheet->setCellValue("C{$rowNumber}", $included ? 'Yes' : 'No');

            if ($included) {
                $sheet->setCellValue("D{$rowNumber}", $state['total_hours']);
                $sheet->setCellValue("E{$rowNumber}", $state['controlled'] ? 'Yes' : 'No');
                if ($state['controlled']) {
                    foreach (self::CATEGORY_COLUMNS as $category => $columns) {
                        $categoryState = $state['categories'][$category];
                        $sheet->setCellValue("{$columns['mode']}{$rowNumber}", $this->modeLabel($categoryState['mode']));
                        if ($categoryState['mode'] === 'reserved') {
                            $sheet->setCellValue("{$columns['hours']}{$rowNumber}", $categoryState['hours']);
                        }
                    }
                }
            }

            $this->yesNoValidation($sheet, "C{$rowNumber}", 'Choose Yes or No.');
            $this->yesNoValidation($sheet, "E{$rowNumber}", 'Choose Yes or No.');
            foreach (self::CATEGORY_COLUMNS as $columns) {
                $this->modeValidation($sheet, "{$columns['mode']}{$rowNumber}");
                $this->hoursValidation($sheet, "{$columns['hours']}{$rowNumber}");
            }
            $this->hoursValidation($sheet, "D{$rowNumber}");
        }

        $lastRow = max(2, $departments->count() + 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:M{$lastRow}");
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF355C9A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle("A2:B{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF1F3F5');
        $sheet->getStyle("C2:M{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE7F0FF');
        $sheet->getStyle("A1:M{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FFD5DAE2');
        $sheet->getStyle("A1:M{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(58);
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setWidth(match ($column) {
                'A' => 20,
                'B' => 28,
                'C', 'E' => 18,
                'D' => 18,
                default => 23,
            });
        }

        $this->addInstructionsSheet($spreadsheet);
        $this->addUsageReferenceSheet($spreadsheet, $project, $departments, $current);

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle(($project?->project_code ? $project->project_code.' ' : '').'Department manhour allocations');

        return $spreadsheet;
    }

    public function preview(string $path, array $input, ?Project $project = null): array
    {
        $parsed = $this->parse($path);
        if ($parsed['errors']) {
            return $this->previewResult($parsed['rows'], $parsed['errors'], collect(), collect());
        }

        $allowedDepartmentIds = $project
            ? $project->departmentAllocations()->pluck('department_id')
            : collect();
        $departments = Department::query()
            ->where('is_active', true)
            ->when($project, fn ($query) => $query->orWhereIn('id', $allowedDepartmentIds))
            ->get(['id', 'code', 'name', 'is_active'])
            ->keyBy(fn (Department $department) => Str::upper(trim((string) $department->code)));
        $current = $this->formState($input);
        $usage = $project ? $this->usageByDepartment($project) : collect();

        $rows = collect($parsed['rows'])->map(function (array $row) use ($departments, $current, $usage) {
            $department = filled($row['department_code'])
                ? $departments->get(Str::upper($row['department_code']))
                : null;

            if (! $department && filled($row['department_code']) && ! $row['has_duplicate']) {
                $row['errors'][] = 'Department Code does not match an active or currently allocated department.';
            }

            $row['department_id'] = $department?->id;
            $row['department_name'] = $department?->name ?: ($row['reference_name'] ?: 'Unknown department');
            $currentState = $department ? $current->get($department->id) : null;
            $importedState = $row['included'] === true ? [
                'total_hours' => $row['total_hours'],
                'controlled' => $row['controlled'],
                'categories' => $row['categories'],
            ] : null;

            $row['current'] = $this->stateDescription($currentState);
            $row['imported'] = $this->stateDescription($importedState);
            $row['change'] = $this->changeType($currentState, $importedState, $row['included']);
            $row['change_label'] = match ($row['change']) {
                'added' => 'Added',
                'updated' => 'Updated',
                'removed' => 'Removed',
                default => 'Unchanged',
            };

            if ($department && $row['included'] !== null) {
                $this->addUsageErrors($row, $usage->get($department->id, $this->emptyUsage()));
            }

            $row['valid'] = $row['errors'] === [];
            unset($row['reference_name'], $row['has_duplicate']);

            return $row;
        })->values();

        $final = $current->map(fn ($state) => $state)->all();
        foreach ($rows as $row) {
            if (! $row['department_id'] || $row['included'] === null) {
                continue;
            }
            if ($row['included']) {
                $final[$row['department_id']] = [
                    'total_hours' => $row['total_hours'],
                    'controlled' => $row['controlled'],
                    'categories' => $row['categories'],
                ];
            } else {
                unset($final[$row['department_id']]);
            }
        }
        $final = collect($final);
        $assignmentWarnings = $this->assignmentWarnings($input, $final);
        $globalErrors = $final->isEmpty()
            ? ['The staged project must include at least one department allocation.']
            : [];

        return $this->previewResult($rows->all(), $globalErrors, $current, $final, $assignmentWarnings);
    }

    private function parse(string $path): array
    {
        $reader = new XlsxReader;
        $reader->setReadDataOnly(false);

        $worksheetNames = $reader->listWorksheetNames($path);
        if (! in_array('Department Allocations', $worksheetNames, true)) {
            return ['rows' => [], 'errors' => ['The workbook must contain a worksheet named Department Allocations.']];
        }

        $reader->setLoadSheetsOnly(['Department Allocations']);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheetByName('Department Allocations');
            if (! $sheet) {
                return ['rows' => [], 'errors' => ['The workbook must contain a worksheet named Department Allocations.']];
            }
            $headerErrors = $this->headerErrors($sheet);
            if ($headerErrors) {
                return ['rows' => [], 'errors' => $headerErrors];
            }

            $highestRow = $sheet->getHighestDataRow();
            if ($highestRow - 1 > self::MAX_ROWS) {
                return ['rows' => [], 'errors' => ['The workbook may contain no more than '.self::MAX_ROWS.' department rows.']];
            }

            $rows = [];
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $values = [];
                foreach (range('A', 'M') as $column) {
                    $values[$column] = trim((string) ($sheet->getCell("{$column}{$rowNumber}")->getValue() ?? ''));
                }
                if (collect($values)->every(fn ($value) => $value === '')) {
                    continue;
                }

                $errors = [];
                foreach (range('A', 'M') as $column) {
                    if ($sheet->getCell("{$column}{$rowNumber}")->getDataType() === DataType::TYPE_FORMULA) {
                        $errors[] = self::HEADERS[ord($column) - ord('A')].' cannot contain a formula.';
                        $values[$column] = '';
                    }
                }

                $departmentCode = Str::upper($values['A']);
                if ($departmentCode === '') {
                    $errors[] = 'Department Code is required.';
                }
                $included = $this->yesNoValue($values['C']);
                if ($included === null) {
                    $errors[] = 'Included must be Yes or No.';
                }

                $totalHours = null;
                $controlled = null;
                $categories = $this->blankCategories();

                if ($included === false) {
                    if (collect($values)->only(range('D', 'M'))->contains(fn ($value) => $value !== '')) {
                        $errors[] = 'Clear Total Manhours and every control/category cell when Included is No.';
                    }
                } elseif ($included === true) {
                    $totalHours = $this->hoursValue($sheet, "D{$rowNumber}", 'Total Manhours', $errors);
                    $controlled = $this->yesNoValue($values['E']);
                    if ($controlled === null) {
                        $errors[] = 'Control by Manpower Category must be Yes or No.';
                    } elseif ($controlled === false) {
                        if (collect($values)->only(range('F', 'M'))->contains(fn ($value) => $value !== '')) {
                            $errors[] = 'Clear every category mode and reserved-hours cell when category control is No.';
                        }
                    } else {
                        $hasShared = false;
                        $reservedTotal = 0.0;
                        $allowedCount = 0;
                        foreach (self::CATEGORY_COLUMNS as $category => $columns) {
                            $mode = $this->modeValue($values[$columns['mode']]);
                            $label = config('manpower_categories.labels.'.$category);
                            if ($mode === null) {
                                $errors[] = "{$label} Mode must be Shared, Reserved, or Not allowed.";

                                continue;
                            }

                            $hoursCell = "{$columns['hours']}{$rowNumber}";
                            $hours = null;
                            if ($mode === 'reserved') {
                                $hours = $this->hoursValue($sheet, $hoursCell, "{$label} Reserved Hours", $errors);
                                if ($hours !== null) {
                                    $reservedTotal += $hours;
                                }
                                $allowedCount++;
                            } elseif ($values[$columns['hours']] !== '') {
                                $errors[] = "Clear {$label} Reserved Hours when its mode is {$this->modeLabel($mode)}.";
                            } else {
                                $hasShared = $hasShared || $mode === 'shared';
                                $allowedCount += $mode === 'shared' ? 1 : 0;
                            }
                            $categories[$category] = ['mode' => $mode, 'hours' => $hours];
                        }

                        if ($allowedCount === 0) {
                            $errors[] = 'At least one Manpower Category must be Shared or Reserved.';
                        }
                        if ($totalHours !== null) {
                            if ($hasShared && $reservedTotal > $totalHours + 0.0001) {
                                $errors[] = 'Reserved Manpower Category hours cannot exceed Total Manhours.';
                            }
                            if (! $hasShared && abs($reservedTotal - $totalHours) > 0.0001) {
                                $errors[] = 'When no category is Shared, reserved hours must equal Total Manhours.';
                            }
                        }
                    }
                }

                $rows[] = [
                    'row_number' => $rowNumber,
                    'department_code' => $departmentCode,
                    'reference_name' => $values['B'],
                    'included' => $included,
                    'total_hours' => $totalHours,
                    'controlled' => $controlled,
                    'categories' => $categories,
                    'errors' => array_values(array_unique($errors)),
                    'warnings' => [],
                    'has_duplicate' => false,
                ];
            }

            if ($rows === []) {
                return ['rows' => [], 'errors' => ['The Department Allocations worksheet does not contain any department rows.']];
            }

            $counts = collect($rows)->pluck('department_code')->filter()->countBy();
            foreach ($rows as &$row) {
                if (($counts[$row['department_code']] ?? 0) > 1) {
                    $row['errors'][] = 'Department Code appears more than once in the workbook.';
                    $row['errors'] = array_values(array_unique($row['errors']));
                    $row['has_duplicate'] = true;
                }
            }
            unset($row);

            return ['rows' => $rows, 'errors' => []];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function formState(array $input): Collection
    {
        $controls = collect($input['job_level_controls'] ?? []);
        $categoryInput = $input['job_level_allocations'] ?? [];

        return collect($input['department_allocations'] ?? [])
            ->filter(fn ($hours) => filled($hours) && is_numeric($hours) && (float) $hours > 0)
            ->mapWithKeys(function ($hours, $departmentId) use ($controls, $categoryInput) {
                $departmentId = (int) $departmentId;
                $controlled = filter_var($controls->get($departmentId, false), FILTER_VALIDATE_BOOL);
                $categories = $this->blankCategories();
                if ($controlled) {
                    foreach (array_keys(self::CATEGORY_COLUMNS) as $category) {
                        $mode = $categoryInput[$departmentId][$category]['mode'] ?? 'shared';
                        $categories[$category] = [
                            'mode' => in_array($mode, ['shared', 'reserved', 'not_allowed'], true) ? $mode : 'shared',
                            'hours' => $mode === 'reserved' && is_numeric($categoryInput[$departmentId][$category]['hours'] ?? null)
                                ? round((float) $categoryInput[$departmentId][$category]['hours'], 2)
                                : null,
                        ];
                    }
                }

                return [$departmentId => [
                    'total_hours' => round((float) $hours, 2),
                    'controlled' => $controlled,
                    'categories' => $categories,
                ]];
            });
    }

    private function storedState(Project $project): Collection
    {
        return $project->departmentAllocations()
            ->with('manpowerCategoryAllocations')
            ->get()
            ->mapWithKeys(function ($allocation) {
                $stored = $allocation->manpowerCategoryAllocations->keyBy('manpower_category');
                $controlled = $stored->isNotEmpty();
                $categories = $this->blankCategories();
                if ($controlled) {
                    foreach (array_keys(self::CATEGORY_COLUMNS) as $category) {
                        $value = $stored->get($category)?->allocated_hours;
                        $categories[$category] = [
                            'mode' => ! $stored->has($category)
                                ? 'not_allowed'
                                : ($value === null ? 'shared' : ((float) $value === 0.0 ? 'not_allowed' : 'reserved')),
                            'hours' => $value !== null && (float) $value > 0 ? round((float) $value, 2) : null,
                        ];
                    }
                }

                return [(int) $allocation->department_id => [
                    'total_hours' => round((float) $allocation->allocated_hours, 2),
                    'controlled' => $controlled,
                    'categories' => $categories,
                ]];
            });
    }

    private function usageByDepartment(Project $project): Collection
    {
        $categories = array_keys(config('manpower_categories.labels'));
        $usage = collect();
        $rows = DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->whereNotNull('entries.department_id')
            ->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->select([
                'entries.department_id',
                'entries.manpower_category_snapshot',
                'entries.allocation_bucket_snapshot',
                'timesheets.status',
            ])
            ->selectRaw('SUM(entries.regular_hours + entries.overtime_hours) as consumed_hours')
            ->groupBy(
                'entries.department_id',
                'entries.manpower_category_snapshot',
                'entries.allocation_bucket_snapshot',
                'timesheets.status',
            )
            ->get();

        foreach ($rows as $row) {
            $departmentId = (int) $row->department_id;
            $metrics = $usage->get($departmentId, $this->emptyUsage());
            $hours = (float) $row->consumed_hours;
            $metrics[$row->status] += $hours;
            $category = $row->manpower_category_snapshot;
            $bucket = $row->allocation_bucket_snapshot;

            if ($bucket === 'reserved' && in_array($category, $categories, true)) {
                $metrics['reserved'][$category] += $hours;
            } elseif ($bucket === 'shared' && in_array($category, $categories, true)) {
                $metrics['shared'] += $hours;
            } else {
                $metrics['legacy'] += $hours;
            }
            $usage->put($departmentId, $metrics);
        }

        return $usage;
    }

    private function addUsageErrors(array &$row, array $usage): void
    {
        $totalUsage = $usage['submitted'] + $usage['approved'];
        if ($row['included'] === false && $totalUsage > 0) {
            $row['errors'][] = 'Cannot remove this department: current submitted usage is '
                .$this->hours($usage['submitted']).', approved usage is '.$this->hours($usage['approved'])
                .', and the minimum allocation is '.$this->hours($totalUsage).'.';

            return;
        }
        if ($row['included'] !== true || $row['total_hours'] === null) {
            return;
        }
        if ($totalUsage > $row['total_hours'] + 0.0001) {
            $row['errors'][] = 'Imported Total Manhours '.$this->hours($row['total_hours'])
                .' is below the minimum '.$this->hours($totalUsage)
                .' (submitted '.$this->hours($usage['submitted']).'; approved '.$this->hours($usage['approved']).').';
        }
        if ($row['controlled'] !== true) {
            if (collect($usage['reserved'])->sum() > 0) {
                $row['errors'][] = 'Category control cannot be removed because reserved usage is '
                    .$this->hours(collect($usage['reserved'])->sum()).'.';
            }

            return;
        }

        foreach (self::CATEGORY_COLUMNS as $category => $columns) {
            $minimum = $usage['reserved'][$category];
            if ($minimum <= 0) {
                continue;
            }
            $imported = $row['categories'][$category]['mode'] === 'reserved'
                ? (float) ($row['categories'][$category]['hours'] ?? 0)
                : 0.0;
            if ($imported + 0.0001 < $minimum) {
                $row['errors'][] = config('manpower_categories.labels.'.$category)
                    .' imported reservation '.$this->hours($imported)
                    .' is below its submitted/approved reserved usage and minimum '.$this->hours($minimum).'.';
            }
        }

        $reservedTotal = collect($row['categories'])
            ->where('mode', 'reserved')
            ->sum(fn ($category) => (float) ($category['hours'] ?? 0));
        $importedShared = $row['total_hours'] - $reservedTotal;
        $minimumShared = $usage['shared'] + $usage['legacy'];
        if ($importedShared + 0.0001 < $minimumShared) {
            $row['errors'][] = 'Imported shared remainder '.$this->hours($importedShared)
                .' is below the minimum '.$this->hours($minimumShared)
                .' (current shared '.$this->hours($usage['shared'])
                .'; Legacy / Unclassified '.$this->hours($usage['legacy']).').';
        }
    }

    private function assignmentWarnings(array $input, Collection $final): array
    {
        $assignedIds = collect($input['assigned_user_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($assignedIds->isEmpty()) {
            return [];
        }

        $categories = collect($input['assigned_user_categories'] ?? [])
            ->mapWithKeys(fn ($category, $userId) => [(int) $userId => filled($category) ? (string) $category : null]);
        $hasUncontrolled = $final->contains(fn ($state) => ! $state['controlled']);
        $allowed = $final->filter(fn ($state) => $state['controlled'])
            ->flatMap(fn ($state) => collect($state['categories'])
                ->filter(fn ($category) => in_array($category['mode'], ['shared', 'reserved'], true))
                ->keys())
            ->unique();
        $users = User::query()
            ->whereIn('id', $assignedIds)
            ->get(['id', 'name', 'employee_code'])
            ->keyBy('id');
        $warnings = [];

        foreach ($assignedIds as $userId) {
            $category = $categories->get($userId);
            $user = $users->get($userId);
            $identity = ($user?->name ?? 'Unknown user').' ('.($user?->employee_code ?? "user {$userId}").')';
            if ($category === null && ! $hasUncontrolled) {
                $warnings[] = $identity.' has no Manpower Category, but the staged allocations contain no uncontrolled department.';
            } elseif ($category !== null && ! $allowed->contains($category)) {
                $warnings[] = $identity."'s ".config('manpower_categories.labels.'.$category, $category)
                    .' category is not Shared or Reserved in any staged controlled department.';
            }
        }

        return $warnings;
    }

    private function previewResult(
        array $rows,
        array $globalErrors,
        Collection $current,
        Collection $final,
        array $assignmentWarnings = [],
    ): array {
        $collection = collect($rows);
        $errorRows = $collection->filter(fn ($row) => ($row['errors'] ?? []) !== []);
        $currentTotal = $current->sum(fn ($state) => $state['total_hours']);
        $finalTotal = $final->sum(fn ($state) => $state['total_hours']);

        return [
            'valid' => $globalErrors === [] && $errorRows->isEmpty(),
            'errors' => array_values($globalErrors),
            'assignment_warnings' => array_values($assignmentWarnings),
            'rows' => $collection->sortBy(fn ($row) => match ($row['change'] ?? 'unchanged') {
                'removed' => 0,
                'added' => 1,
                'updated' => 2,
                default => 3,
            })->values()->all(),
            'summary' => [
                'added' => $collection->where('change', 'added')->count(),
                'updated' => $collection->where('change', 'updated')->count(),
                'removed' => $collection->where('change', 'removed')->count(),
                'category_changed' => $collection->filter(fn ($row) => ($row['change'] ?? null) === 'updated'
                    && (
                        ($row['current']['controlled'] ?? null) !== ($row['imported']['controlled'] ?? null)
                        || ($row['current']['categories'] ?? []) !== ($row['imported']['categories'] ?? [])
                    ))->count(),
                'unchanged' => $collection->where('change', 'unchanged')->count(),
                'errors' => $errorRows->count() + count($globalErrors),
                'assignment_warnings' => count($assignmentWarnings),
                'current_total' => round($currentTotal, 2),
                'imported_total' => round($finalTotal, 2),
                'net_change' => round($finalTotal - $currentTotal, 2),
                'controlled_total' => round($final->filter(fn ($state) => $state['controlled'])->sum(fn ($state) => $state['total_hours']), 2),
                'uncontrolled_total' => round($final->reject(fn ($state) => $state['controlled'])->sum(fn ($state) => $state['total_hours']), 2),
            ],
        ];
    }

    private function addUsageReferenceSheet(
        Spreadsheet $spreadsheet,
        ?Project $project,
        Collection $departments,
        Collection $current,
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Usage Reference');
        $headers = [
            'Department Code',
            'Department Name',
            'Current Allocation',
            'Submitted Usage',
            'Approved Usage',
            'Current Shared Usage',
            'Legacy / Unclassified Usage',
            'Lead Engineer / Checker Reserved Usage / Minimum',
            'Senior Engineer Reserved Usage / Minimum',
            'Engineer Reserved Usage / Minimum',
            'Designer Reserved Usage / Minimum',
            'Minimum Total Allocation',
            'Minimum Shared Remainder',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $usage = $project ? $this->usageByDepartment($project) : collect();

        foreach ($departments as $index => $department) {
            $row = $index + 2;
            $metrics = $usage->get($department->id, $this->emptyUsage());
            $sheet->fromArray([
                $department->code,
                $department->name,
                $current->get($department->id)['total_hours'] ?? null,
                $metrics['submitted'],
                $metrics['approved'],
                $metrics['shared'],
                $metrics['legacy'],
                $metrics['reserved']['lead_engineer_checker'],
                $metrics['reserved']['senior_engineer'],
                $metrics['reserved']['engineer'],
                $metrics['reserved']['designer'],
                $metrics['submitted'] + $metrics['approved'],
                $metrics['shared'] + $metrics['legacy'],
            ], null, "A{$row}");
        }

        $lastRow = max(2, $departments->count() + 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:M{$lastRow}");
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF5B6573']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A1:M{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FFD5DAE2');
        $sheet->getStyle("A1:M{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(28);
        foreach (range('C', 'M') as $column) {
            $sheet->getColumnDimension($column)->setWidth(21);
        }
        $sheet->getStyle("C2:M{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getProtection()->setSheet(true);
    }

    private function addInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');
        $instructions = [
            ['Department manhour allocation template'],
            ['1. Edit only the blue cells on Department Allocations. Match departments using Department Code; Department Name is reference-only.'],
            ['2. Included = Yes creates or updates an allocation. Included = No removes it and requires every remaining allocation/control cell on that row to be blank.'],
            ['3. Rows omitted from the workbook keep their current values on the project form.'],
            ['4. When category control is No, leave all category cells blank. When it is Yes, set every category to Shared, Reserved, or Not allowed.'],
            ['5. Reserved modes require reserved hours. Shared and Not allowed modes require blank reserved hours. All hour values must use 0.25-hour increments.'],
            ['6. The Usage Reference sheet is read-only guidance and is ignored during import. Submitted and approved usage may prevent reductions or removals.'],
            ['7. Upload this .xlsx file, review the all-or-nothing preview, apply it to the form, resolve assignment warnings, then save the project.'],
            ['8. The uploaded file is deleted from temporary storage after preview, whether validation succeeds or fails.'],
        ];
        $sheet->fromArray($instructions, null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A9')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getColumnDimension('A')->setWidth(120);
        foreach (range(2, 9) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(32);
        }
    }

    private function headerErrors(Worksheet $sheet): array
    {
        $errors = [];
        foreach (self::HEADERS as $index => $expected) {
            $column = chr(ord('A') + $index);
            $actual = trim((string) $sheet->getCell("{$column}1")->getValue());
            if ($actual !== $expected) {
                $errors[] = "Column {$column} must be named {$expected}.";
            }
        }

        return $errors;
    }

    private function hoursValue(Worksheet $sheet, string $cell, string $label, array &$errors): ?float
    {
        $value = $sheet->getCell($cell)->getValue();
        if ($value === null || trim((string) $value) === '') {
            $errors[] = "{$label} is required.";

            return null;
        }
        if ($sheet->getCell($cell)->getDataType() !== DataType::TYPE_NUMERIC || ! is_numeric($value)) {
            $errors[] = "{$label} must be an Excel number, not text.";

            return null;
        }

        $hours = (float) $value;
        if ($hours < 0.25 || $hours > self::MAX_HOURS) {
            $errors[] = "{$label} must be between 0.25 and ".number_format(self::MAX_HOURS, 2, '.', ',').'.';

            return null;
        }
        if (abs($hours * 4 - round($hours * 4)) > 0.0001) {
            $errors[] = "{$label} must use 0.25-hour increments.";

            return null;
        }

        return round($hours, 2);
    }

    private function yesNoValue(string $value): ?bool
    {
        return match (Str::lower(trim($value))) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function modeValue(string $value): ?string
    {
        return match (Str::lower(trim($value))) {
            'shared' => 'shared',
            'reserved' => 'reserved',
            'not allowed' => 'not_allowed',
            default => null,
        };
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            'reserved' => 'Reserved',
            'not_allowed' => 'Not allowed',
            default => 'Shared',
        };
    }

    private function blankCategories(): array
    {
        return collect(array_keys(self::CATEGORY_COLUMNS))->mapWithKeys(fn ($category) => [
            $category => ['mode' => 'not_allowed', 'hours' => null],
        ])->all();
    }

    private function emptyUsage(): array
    {
        return [
            'submitted' => 0.0,
            'approved' => 0.0,
            'shared' => 0.0,
            'legacy' => 0.0,
            'reserved' => collect(array_keys(self::CATEGORY_COLUMNS))->mapWithKeys(fn ($category) => [$category => 0.0])->all(),
        ];
    }

    private function stateDescription(?array $state): array
    {
        if ($state === null) {
            return [
                'label' => 'Not included',
                'total_hours' => null,
                'controlled' => false,
                'categories' => [],
            ];
        }

        $categorySummary = $state['controlled']
            ? collect($state['categories'])->map(function ($category, $code) {
                $label = config('manpower_categories.labels.'.$code);

                return $label.': '.$this->modeLabel($category['mode'])
                    .($category['mode'] === 'reserved' ? ' '.$this->hours($category['hours']) : '');
            })->implode('; ')
            : 'Department total only';

        return [
            'label' => $this->hours($state['total_hours']).' · '.($state['controlled'] ? 'Category controlled' : 'Uncontrolled'),
            'detail' => $categorySummary,
            'total_hours' => $state['total_hours'],
            'controlled' => $state['controlled'],
            'categories' => $state['categories'],
        ];
    }

    private function changeType(?array $current, ?array $imported, ?bool $included): string
    {
        if ($included === true && $current === null) {
            return 'added';
        }
        if ($included === false && $current !== null) {
            return 'removed';
        }
        if ($included === true && $current !== null && $this->comparableState($current) !== $this->comparableState($imported)) {
            return 'updated';
        }

        return 'unchanged';
    }

    private function comparableState(?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return [
            'total_hours' => number_format((float) $state['total_hours'], 2, '.', ''),
            'controlled' => (bool) $state['controlled'],
            'categories' => $state['controlled']
                ? collect($state['categories'])->map(fn ($category) => [
                    'mode' => $category['mode'],
                    'hours' => $category['mode'] === 'reserved'
                        ? number_format((float) $category['hours'], 2, '.', '')
                        : null,
                ])->all()
                : [],
        ];
    }

    private function hours(float|int|string|null $hours): string
    {
        return number_format((float) $hours, 2, '.', ',').' hrs';
    }

    private function yesNoValidation(Worksheet $sheet, string $cell, string $error): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid value');
        $validation->setError($error);
        $validation->setFormula1('"Yes,No"');
    }

    private function modeValidation(Worksheet $sheet, string $cell): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid mode');
        $validation->setError('Choose Shared, Reserved, or Not allowed.');
        $validation->setFormula1('"Shared,Reserved,Not allowed"');
    }

    private function hoursValidation(Worksheet $sheet, string $cell): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_DECIMAL);
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setErrorTitle('Invalid hours');
        $validation->setError('Enter a positive number in 0.25-hour increments.');
        $validation->setFormula1('0.25');
        $validation->setFormula2((string) self::MAX_HOURS);
    }

    private function spreadsheetText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=\-+@]/', $value) ? "'".$value : $value;
    }
}
