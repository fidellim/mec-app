<?php

namespace App\Services;

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

class ProjectAssignmentSpreadsheetService
{
    public const MAX_ROWS = 1000;

    public const EDITABLE_TIMESHEET_STATUSES = [
        Timesheet::STATUS_DRAFT,
        Timesheet::STATUS_REJECTED,
        Timesheet::STATUS_WITHDRAWN,
        Timesheet::STATUS_RECALLED,
    ];

    private const HEADERS = [
        'Employee Number',
        'Employee Name',
        'Role',
        'Home Department',
        'Assigned',
        'Manpower Category',
    ];

    public function template(?Project $project = null): Spreadsheet
    {
        $users = User::query()
            ->with('department:id,name')
            ->where('is_active', true)
            ->whereIn('role', ['employee', 'hod'])
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'role', 'department_id'])
            ->sortBy(fn (User $user) => strtolower(($user->department?->name ?? 'ZZZZ No department').'|'.$user->name))
            ->values();

        $assigned = $project
            ? $project->assignedUsers()->get(['users.id'])->mapWithKeys(fn (User $user) => [
                $user->id => $user->pivot->manpower_category,
            ])
            : collect();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Assignments');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        foreach ($users as $index => $user) {
            $row = $index + 2;
            $isAssigned = $assigned->has($user->id);
            $category = $assigned->get($user->id);

            $sheet->setCellValueExplicit("A{$row}", (string) $user->employee_code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", $this->spreadsheetText($user->name), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$row}", $user->role === 'hod' ? 'Head of Department' : 'Employee', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row}", $this->spreadsheetText($user->department?->name ?? 'No department assigned'), DataType::TYPE_STRING);
            $sheet->setCellValue("E{$row}", $isAssigned ? 'Yes' : 'No');
            $sheet->setCellValue("F{$row}", $isAssigned && filled($category)
                ? config('manpower_categories.labels.'.$category, '')
                : '');

            $this->assignedValidation($sheet, $row);
            $this->categoryValidation($sheet, $row);
        }

        $lastRow = max(2, $users->count() + 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:F{$lastRow}");
        $sheet->getStyle('A1:F1')->applyFromArray([
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
        $sheet->getStyle("A2:D{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF1F3F5');
        $sheet->getStyle("E2:F{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE7F0FF');
        $sheet->getStyle("A1:F{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FFD5DAE2');
        $sheet->getStyle("A1:F{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B2:D{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(26);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(28);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Project assignment template'],
            ['1. Configure the project discipline allocations before importing this file.'],
            ['2. Edit only Assigned and Manpower Category. Reference columns are ignored during import.'],
            ['3. Assigned = Yes with a blank category gives access to uncontrolled disciplines only.'],
            ['4. Assigned = No removes an existing assignment. Clear the category on those rows.'],
            ['5. Rows omitted from the file remain unchanged. Every row must pass validation before it can be applied.'],
            ['6. Upload this .xlsx file from the project form, review the preview, apply it, then save the project.'],
        ], null, 'A1');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructions->getStyle('A1:A7')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $instructions->getColumnDimension('A')->setWidth(110);
        foreach (range(2, 7) as $row) {
            $instructions->getRowDimension($row)->setRowHeight(30);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle(($project?->project_code ? $project->project_code.' ' : '').'Project assignments');

        return $spreadsheet;
    }

    public function preview(string $path, array $input, ?Project $project = null): array
    {
        $parsed = $this->parse($path);
        if ($parsed['errors']) {
            return $this->previewResult($parsed['rows'], $parsed['errors']);
        }

        $access = $this->allocationAccess($input);
        $globalErrors = [];
        if (! $access['has_allocation']) {
            $globalErrors[] = 'Configure at least one discipline allocation before importing assignments.';
        }

        $currentAssignedIds = collect($input['assigned_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique();
        $currentCategories = collect($input['assigned_user_categories'] ?? [])
            ->mapWithKeys(fn ($category, $userId) => [(int) $userId => filled($category) ? (string) $category : null]);

        $employeeCodes = collect($parsed['rows'])->pluck('employee_code')->filter()->unique()->values();
        $usersByCode = User::query()
            ->with('department:id,name')
            ->whereIn('employee_code', $employeeCodes)
            ->get(['id', 'employee_code', 'name', 'role', 'is_active', 'department_id'])
            ->keyBy(fn (User $user) => Str::upper($user->employee_code));

        $userIds = $usersByCode->pluck('id')->all();
        $editableUsage = $project
            ? $this->editableTimesheetUsage($project, $userIds)
            : collect();

        $rows = collect($parsed['rows'])->map(function (array $row) use (
            $usersByCode,
            $currentAssignedIds,
            $currentCategories,
            $access,
            $editableUsage
        ) {
            $user = filled($row['employee_code'])
                ? $usersByCode->get(Str::upper($row['employee_code']))
                : null;

            if (! $user && filled($row['employee_code']) && ! $row['has_duplicate']) {
                $row['errors'][] = 'Employee number does not match a user.';
            } elseif ($user && (! $user->is_active || ! in_array($user->role, ['employee', 'hod'], true))) {
                $row['errors'][] = 'Employee must be an active Employee or Head of Department.';
            }

            $row['user_id'] = $user?->id;
            $row['employee_name'] = $user?->name ?: ($row['reference_name'] ?: 'Unknown employee');
            $row['role'] = $user
                ? ($user->role === 'hod' ? 'Head of Department' : 'Employee')
                : ($row['reference_role'] ?: '—');
            $row['department'] = $user?->department?->name ?: ($row['reference_department'] ?: '—');

            if ($row['assigned'] === true && $row['category'] !== null && ! in_array($row['category'], $access['allowed_categories'], true)) {
                $row['errors'][] = config('manpower_categories.labels.'.$row['category'], 'This category')
                    .' is not Shared or Reserved in any controlled discipline.';
            }
            if ($row['assigned'] === true && $row['category'] === null && $access['has_allocation'] && ! $access['has_uncontrolled']) {
                $row['errors'][] = 'A blank category requires at least one uncontrolled discipline allocation.';
            }

            $currentAssigned = $user ? $currentAssignedIds->contains($user->id) : false;
            $currentCategory = $user && $currentAssigned
                ? ($currentCategories->get($user->id) ?: null)
                : null;
            $row['current_assigned'] = $currentAssigned;
            $row['current_category'] = $currentCategory;
            $row['current_category_label'] = $currentAssigned
                ? ($currentCategory
                    ? config('manpower_categories.labels.'.$currentCategory, 'Legacy / Unclassified')
                    : 'Uncontrolled disciplines only')
                : 'Not assigned';

            $usage = $user ? $editableUsage->get($user->id, collect()) : collect();
            if ($row['assigned'] === false && $currentAssigned && $usage->isNotEmpty()) {
                $row['errors'][] = 'Assignment cannot be removed while the employee has '.$this->usageDescription($usage).'.';
            }
            if ($row['assigned'] === true && $currentAssigned && $currentCategory !== $row['category'] && $usage->isNotEmpty()) {
                $row['warnings'][] = ucfirst($this->usageDescription($usage))
                    .' will use the new category when next saved.';
            }

            $row['change'] = $this->changeType($row['assigned'], $row['category'], $currentAssigned, $currentCategory);
            $row['change_label'] = match ($row['change']) {
                'assigned' => 'New assignment',
                'category_changed' => 'Category change',
                'removed' => 'Removal',
                default => 'Unchanged',
            };
            $row['category_label'] = $row['assigned'] === true
                ? ($row['category']
                    ? config('manpower_categories.labels.'.$row['category'], $row['raw_category'])
                    : 'Uncontrolled disciplines only')
                : 'Not assigned';
            $row['valid'] = $row['errors'] === [];

            unset(
                $row['reference_name'],
                $row['reference_role'],
                $row['reference_department'],
                $row['raw_category'],
                $row['has_duplicate'],
            );

            return $row;
        })->values()->all();

        return $this->previewResult($rows, $globalErrors);
    }

    private function parse(string $path): array
    {
        $reader = new XlsxReader;
        $reader->setReadDataOnly(false);

        $worksheetNames = $reader->listWorksheetNames($path);
        if (! in_array('Assignments', $worksheetNames, true)) {
            return ['rows' => [], 'errors' => ['The workbook must contain a worksheet named Assignments.']];
        }

        $reader->setLoadSheetsOnly(['Assignments']);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheetByName('Assignments');
            if (! $sheet) {
                return ['rows' => [], 'errors' => ['The workbook must contain a worksheet named Assignments.']];
            }

            $headerErrors = $this->headerErrors($sheet);
            if ($headerErrors) {
                return ['rows' => [], 'errors' => $headerErrors];
            }

            $highestRow = $sheet->getHighestDataRow();
            if ($highestRow - 1 > self::MAX_ROWS) {
                return ['rows' => [], 'errors' => ['The workbook may contain no more than '.self::MAX_ROWS.' assignment rows.']];
            }

            $categories = collect(config('manpower_categories.labels'))
                ->mapWithKeys(fn ($label, $code) => [Str::lower(trim($label)) => $code]);
            $rows = [];

            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $values = [];
                foreach (range('A', 'F') as $column) {
                    $values[$column] = trim((string) ($sheet->getCell("{$column}{$rowNumber}")->getValue() ?? ''));
                }
                if (collect($values)->every(fn ($value) => $value === '')) {
                    continue;
                }

                $errors = [];
                foreach (['A', 'E', 'F'] as $column) {
                    if ($sheet->getCell("{$column}{$rowNumber}")->getDataType() === DataType::TYPE_FORMULA) {
                        $errors[] = self::HEADERS[array_search($column, ['A', 'B', 'C', 'D', 'E', 'F'], true)]
                            .' cannot contain a formula.';
                        $values[$column] = '';
                    }
                }

                $employeeCode = Str::upper($values['A']);
                if ($employeeCode === '') {
                    $errors[] = 'Employee Number is required.';
                } elseif (! preg_match('/^(MEC|MCE|MEC-PHIL)-HR-\d{4}-\d{3,}$/', $employeeCode)) {
                    $errors[] = 'Employee Number must use MEC-HR-YYYY-NNN, MCE-HR-YYYY-NNN, or MEC-PHIL-HR-YYYY-NNN.';
                }

                $assigned = match (Str::lower($values['E'])) {
                    'yes' => true,
                    'no' => false,
                    default => null,
                };
                if ($assigned === null) {
                    $errors[] = 'Assigned must be Yes or No.';
                }

                $category = null;
                if ($values['F'] !== '') {
                    $category = $categories->get(Str::lower($values['F']));
                    if ($category === null) {
                        $errors[] = 'Manpower Category must be one of the four standard categories or blank.';
                    }
                }
                if ($assigned === false && $values['F'] !== '') {
                    $errors[] = 'Clear Manpower Category when Assigned is No.';
                }

                $rows[] = [
                    'row_number' => $rowNumber,
                    'employee_code' => $employeeCode,
                    'reference_name' => $values['B'],
                    'reference_role' => $values['C'],
                    'reference_department' => $values['D'],
                    'assigned' => $assigned,
                    'category' => $category,
                    'raw_category' => $values['F'],
                    'errors' => array_values(array_unique($errors)),
                    'warnings' => [],
                    'has_duplicate' => false,
                ];
            }

            if ($rows === []) {
                return ['rows' => [], 'errors' => ['The Assignments worksheet does not contain any assignment rows.']];
            }

            $counts = collect($rows)->pluck('employee_code')->filter()->countBy();
            foreach ($rows as &$row) {
                if (($counts[$row['employee_code']] ?? 0) > 1) {
                    $row['errors'][] = 'Employee Number appears more than once in the workbook.';
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

    private function allocationAccess(array $input): array
    {
        $allocations = collect($input['department_allocations'] ?? [])
            ->filter(fn ($hours) => filled($hours) && is_numeric($hours) && (float) $hours > 0);
        $controls = collect($input['job_level_controls'] ?? []);
        $settings = $input['job_level_allocations'] ?? [];
        $allowedCategories = collect();
        $hasUncontrolled = false;

        foreach ($allocations as $departmentId => $hours) {
            $controlled = filter_var($controls->get($departmentId, false), FILTER_VALIDATE_BOOL);
            if (! $controlled) {
                $hasUncontrolled = true;

                continue;
            }

            foreach (array_keys(config('manpower_categories.labels')) as $category) {
                $mode = $settings[$departmentId][$category]['mode'] ?? 'shared';
                if (in_array($mode, ['shared', 'reserved'], true)) {
                    $allowedCategories->push($category);
                }
            }
        }

        return [
            'has_allocation' => $allocations->isNotEmpty(),
            'has_uncontrolled' => $hasUncontrolled,
            'allowed_categories' => $allowedCategories->unique()->values()->all(),
        ];
    }

    private function editableTimesheetUsage(Project $project, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->whereIn('timesheets.user_id', $userIds)
            ->whereIn('timesheets.status', self::EDITABLE_TIMESHEET_STATUSES)
            ->groupBy('timesheets.user_id', 'timesheets.status')
            ->selectRaw('timesheets.user_id, timesheets.status, COUNT(entries.id) as entry_count')
            ->get()
            ->groupBy('user_id');
    }

    private function usageDescription(Collection $usage): string
    {
        $total = (int) $usage->sum('entry_count');
        $statuses = $usage
            ->sortBy('status')
            ->map(fn ($item) => Str::headline($item->status).' ('.$item->entry_count.')')
            ->implode(', ');

        return $total.' editable timesheet '.Str::plural('row', $total).' ['.$statuses.']';
    }

    private function previewResult(array $rows, array $globalErrors): array
    {
        $collection = collect($rows);
        $errorRows = $collection->filter(fn ($row) => ($row['errors'] ?? []) !== []);
        $warningRows = $collection->filter(fn ($row) => ($row['warnings'] ?? []) !== []);

        return [
            'valid' => $globalErrors === [] && $errorRows->isEmpty(),
            'errors' => array_values($globalErrors),
            'rows' => $collection->sortBy(fn ($row) => match ($row['change'] ?? 'unchanged') {
                'removed' => 0,
                'assigned' => 1,
                'category_changed' => 2,
                default => 3,
            })->values()->all(),
            'summary' => [
                'assigned' => $collection->where('change', 'assigned')->count(),
                'category_changed' => $collection->where('change', 'category_changed')->count(),
                'removed' => $collection->where('change', 'removed')->count(),
                'uncontrolled_only' => $collection->where('assigned', true)->whereNull('category')->count(),
                'unchanged' => $collection->where('change', 'unchanged')->count(),
                'errors' => $errorRows->count() + count($globalErrors),
                'warnings' => $warningRows->count(),
            ],
        ];
    }

    private function changeType(?bool $assigned, ?string $category, bool $currentAssigned, ?string $currentCategory): string
    {
        if ($assigned === true && ! $currentAssigned) {
            return 'assigned';
        }
        if ($assigned === false && $currentAssigned) {
            return 'removed';
        }
        if ($assigned === true && $currentAssigned && $category !== $currentCategory) {
            return 'category_changed';
        }

        return 'unchanged';
    }

    private function assignedValidation(Worksheet $sheet, int $row): void
    {
        $validation = $sheet->getCell("E{$row}")->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid assignment');
        $validation->setError('Choose Yes or No.');
        $validation->setFormula1('"Yes,No"');
    }

    private function categoryValidation(Worksheet $sheet, int $row): void
    {
        $validation = $sheet->getCell("F{$row}")->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid category');
        $validation->setError('Choose a standard Manpower Category or leave the cell blank.');
        $validation->setFormula1('"'.implode(',', array_values(config('manpower_categories.labels'))).'"');
    }

    private function spreadsheetText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=\-+@]/', $value) ? "'".$value : $value;
    }
}
