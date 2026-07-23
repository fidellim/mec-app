<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ProjectAllocationSpreadsheetWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_admin_can_download_a_prefilled_allocation_template_with_usage_reference(): void
    {
        $admin = $this->userWithRole('admin');
        $active = $this->department(['code' => 'MEC', 'name' => 'Mechanical']);
        $inactive = $this->department(['code' => 'OLD', 'name' => 'Legacy Discipline', 'is_active' => false]);
        $manager = $this->userWithRole('hod', ['department_id' => $active->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $allocation = $project->departmentAllocations()->create([
            'department_id' => $inactive->id,
            'allocated_hours' => 75,
        ]);
        foreach (config('manpower_categories.labels') as $category => $label) {
            $allocation->manpowerCategoryAllocations()->create([
                'manpower_category' => $category,
                'allocated_hours' => $category === 'engineer' ? 25 : ($category === 'designer' ? null : 0),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('manage.projects.allocation-template', [
            'project_id' => $project->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'allocation-template-');
        file_put_contents($path, $response->streamedContent());

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('Department Allocations');
            $this->assertNotNull($sheet);
            $this->assertSame($this->headers(), $sheet->rangeToArray('A1:M1')[0]);
            $rows = collect($sheet->rangeToArray('A2:M3'))->keyBy(fn ($row) => $row[0]);
            $this->assertSame('No', $rows->get('MEC')[2]);
            $this->assertSame('Yes', $rows->get('OLD')[2]);
            $this->assertSame(75.0, (float) $rows->get('OLD')[3]);
            $oldRowNumber = $sheet->getCell('A2')->getValue() === 'OLD' ? 2 : 3;
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell("D{$oldRowNumber}")->getDataType());
            $this->assertSame('Yes', $rows->get('OLD')[4]);
            $this->assertSame('Reserved', $rows->get('OLD')[9]);
            $this->assertSame(25.0, (float) $rows->get('OLD')[10]);
            $this->assertSame('Shared', $rows->get('OLD')[11]);
            $this->assertSame('list', $sheet->getCell('C2')->getDataValidation()->getType());
            $this->assertSame('list', $sheet->getCell('F2')->getDataValidation()->getType());
            $this->assertNotNull($spreadsheet->getSheetByName('Usage Reference'));
            $this->assertNotNull($spreadsheet->getSheetByName('Instructions'));
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_valid_partial_preview_reports_changes_and_non_blocking_assignment_warnings(): void
    {
        $admin = $this->userWithRole('admin');
        $mechanical = $this->department(['code' => 'MEC', 'name' => 'Mechanical']);
        $quality = $this->department(['code' => 'QA', 'name' => 'QA/QC']);
        $this->department(['code' => 'TEL', 'name' => 'Telecom']);
        $manager = $this->userWithRole('hod', ['department_id' => $mechanical->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $quality->id]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach($employee, ['manpower_category' => 'designer']);

        [$upload, $path] = $this->allocationUpload([
            ['MEC', 'Ignored name', 'Yes', 120, 'Yes', 'Not allowed', '', 'Reserved', 20, 'Shared', '', 'Not allowed', ''],
            ['QA', 'Ignored name', 'No', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), [
                'allocation_file' => $upload,
                'project_id' => $project->id,
                'department_allocations' => [
                    $mechanical->id => 100,
                    $quality->id => 50,
                ],
                'job_level_controls' => [
                    $mechanical->id => 0,
                    $quality->id => 0,
                ],
                'assigned_user_ids' => [$employee->id],
                'assigned_user_categories' => [$employee->id => 'designer'],
            ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('summary.updated', 1)
            ->assertJsonPath('summary.removed', 1)
            ->assertJsonPath('summary.current_total', 150)
            ->assertJsonPath('summary.imported_total', 120)
            ->assertJsonPath('summary.controlled_total', 120)
            ->assertJsonPath('summary.uncontrolled_total', 0)
            ->assertJsonPath('summary.assignment_warnings', 1);
        $this->assertStringContainsString('Designer', implode(' ', $response->json('assignment_warnings')));
        $this->assertNotEmpty($response->json('token'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_preview_is_all_or_nothing_and_rejects_invalid_cells_and_formulas(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['code' => 'MEC']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);

        [$upload, $path] = $this->allocationUpload([
            ['MEC', 'Mechanical', 'No', 100, '', '', '', '', '', '', '', '', ''],
            ['UNKNOWN', 'Unknown', 'Yes', 10.10, 'No', '', '', '', '', '', '', '', ''],
        ], function (Spreadsheet $spreadsheet) {
            $spreadsheet->getActiveSheet()->setCellValueExplicit('C2', '=1+1', DataType::TYPE_FORMULA);
        });

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), [
                'allocation_file' => $upload,
                'project_id' => $project->id,
                'department_allocations' => [$department->id => 100],
                'job_level_controls' => [$department->id => 0],
            ]);

        $response->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('token', null);
        $messages = collect($response->json('rows'))->flatMap(fn ($row) => $row['errors'])->implode(' ');
        $this->assertStringContainsString('Included cannot contain a formula.', $messages);
        $this->assertStringContainsString('Department Code does not match', $messages);
        $this->assertStringContainsString('0.25-hour increments', $messages);
        $this->assertFileDoesNotExist($path);
    }

    public function test_preview_blocks_removal_and_reductions_below_submitted_usage(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['code' => 'MEC']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->departmentAllocations()->create([
            'department_id' => $department->id,
            'allocated_hours' => 100,
        ]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $project, [
            'status' => Timesheet::STATUS_SUBMITTED,
        ]);
        $timesheet->entries()->first()->update(['regular_hours' => 30]);

        [$upload, $path] = $this->allocationUpload([
            ['MEC', 'Mechanical', 'No', '', '', '', '', '', '', '', '', '', ''],
        ]);
        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), [
                'allocation_file' => $upload,
                'project_id' => $project->id,
                'department_allocations' => [$department->id => 100],
                'job_level_controls' => [$department->id => 0],
            ]);

        $response->assertOk()->assertJsonPath('valid', false);
        $error = implode(' ', $response->json('rows.0.errors'));
        $this->assertStringContainsString('Cannot remove this department', $error);
        $this->assertStringContainsString('submitted usage is 30.00 hrs', $error);
        $this->assertStringContainsString('minimum allocation is 30.00 hrs', $error);
        $this->assertFileDoesNotExist($path);
    }

    public function test_preview_protects_reserved_and_legacy_shared_usage_separately(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['code' => 'MEC']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $allocation = $project->departmentAllocations()->create([
            'department_id' => $department->id,
            'allocated_hours' => 100,
        ]);
        foreach (config('manpower_categories.labels') as $category => $label) {
            $allocation->manpowerCategoryAllocations()->create([
                'manpower_category' => $category,
                'allocated_hours' => $category === 'engineer' ? 40 : ($category === 'designer' ? null : 0),
            ]);
        }

        $submitted = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $submitted->entries()->first()->update([
            'regular_hours' => 30,
            'manpower_category_snapshot' => 'engineer',
            'allocation_bucket_snapshot' => 'reserved',
        ]);
        $approved = $this->submittedTimesheet(
            $employee,
            $this->openPeriod([
                'week_number' => 21,
                'start_date' => '2026-05-18',
                'end_date' => '2026-05-24',
            ]),
            $project,
            ['status' => Timesheet::STATUS_APPROVED],
        );
        $approved->entries()->first()->update([
            'regular_hours' => 20,
            'manpower_category_snapshot' => 'legacy_engineer',
            'allocation_bucket_snapshot' => null,
        ]);

        [$reservedUpload, $reservedPath] = $this->allocationUpload([
            ['MEC', 'Mechanical', 'Yes', 60, 'Yes', 'Not allowed', '', 'Not allowed', '', 'Reserved', 25, 'Shared', ''],
        ]);
        $reservedPreview = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), $this->controlledPreviewPayload(
                $reservedUpload,
                $project,
                $department->id,
            ));

        $reservedPreview->assertOk()->assertJsonPath('valid', false);
        $this->assertStringContainsString(
            'Engineer imported reservation 25.00 hrs is below',
            implode(' ', $reservedPreview->json('rows.0.errors')),
        );
        $this->assertFileDoesNotExist($reservedPath);

        [$sharedUpload, $sharedPath] = $this->allocationUpload([
            ['MEC', 'Mechanical', 'Yes', 50, 'Yes', 'Not allowed', '', 'Not allowed', '', 'Reserved', 35, 'Shared', ''],
        ]);
        $sharedPreview = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), $this->controlledPreviewPayload(
                $sharedUpload,
                $project,
                $department->id,
            ));

        $sharedPreview->assertOk()->assertJsonPath('valid', false);
        $sharedError = implode(' ', $sharedPreview->json('rows.0.errors'));
        $this->assertStringContainsString('Imported shared remainder 15.00 hrs is below the minimum 20.00 hrs', $sharedError);
        $this->assertStringContainsString('Legacy / Unclassified 20.00 hrs', $sharedError);
        $this->assertFileDoesNotExist($sharedPath);
    }

    public function test_applied_preview_token_records_allocation_import_audit_on_normal_project_save(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['code' => 'MEC']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'start_date' => '2026-01-01',
        ]);
        $project->departmentAllocations()->create([
            'department_id' => $department->id,
            'allocated_hours' => 100,
        ]);

        [$upload] = $this->allocationUpload([
            ['MEC', 'Mechanical', 'Yes', 150, 'No', '', '', '', '', '', '', '', ''],
        ]);
        $preview = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), [
                'allocation_file' => $upload,
                'project_id' => $project->id,
                'department_allocations' => [$department->id => 100],
                'job_level_controls' => [$department->id => 0],
            ]);
        $preview->assertOk()->assertJsonPath('valid', true);

        $this->actingAs($admin)->put(route('manage.projects.update', $project), [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-01-01',
            'project_manager_id' => $manager->id,
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_ALL_USERS,
            'department_allocations' => [$department->id => 150],
            'job_level_controls' => [$department->id => 0],
            'allocation_change_reason' => 'Import the revised discipline budget.',
            'allocation_import_token' => $preview->json('token'),
        ])->assertRedirect(route('manage.projects.index'));

        $this->assertDatabaseHas('project_department_allocations', [
            'project_id' => $project->id,
            'department_id' => $department->id,
            'allocated_hours' => 150,
        ]);
        $audit = AuditLog::where('action', 'project_allocation_excel_imported')->latest('id')->firstOrFail();
        $this->assertSame('excel_import', $audit->new_values['source']);
        $this->assertSame('Import the revised discipline budget.', $audit->new_values['reason']);
        $this->assertSame(1, $audit->new_values['summary']['updated']);
        $this->assertSame('100.00', $audit->old_values[(string) $department->id]['allocated_hours']);
        $this->assertSame('150.00', $audit->new_values['allocations'][(string) $department->id]['allocated_hours']);
        $this->assertArrayNotHasKey('filename', $audit->new_values);
    }

    public function test_upload_is_deleted_when_allocation_file_validation_fails(): void
    {
        $admin = $this->userWithRole('admin');
        $path = tempnam(sys_get_temp_dir(), 'invalid-allocation-upload-');
        file_put_contents($path, "Department Code,Included\nMEC,Yes\n");
        $upload = new UploadedFile($path, 'department_allocations.csv', 'text/csv', null, true);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.allocation-import.preview'), [
                'allocation_file' => $upload,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('allocation_file');

        $this->assertFileDoesNotExist($path);
    }

    public function test_employee_cannot_access_allocation_spreadsheet_endpoints(): void
    {
        $employee = $this->userWithRole('employee');

        $this->actingAs($employee)
            ->get(route('manage.projects.allocation-template'))
            ->assertForbidden();
        $this->actingAs($employee)
            ->post(route('manage.projects.allocation-import.preview'))
            ->assertForbidden();
    }

    private function allocationUpload(array $rows, ?callable $mutate = null): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Department Allocations');
        $sheet->fromArray($this->headers(), null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        if ($mutate) {
            $mutate($spreadsheet);
        }

        $path = tempnam(sys_get_temp_dir(), 'allocation-upload-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return [
            new UploadedFile(
                $path,
                'department_allocations.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            ),
            $path,
        ];
    }

    private function controlledPreviewPayload(
        UploadedFile $upload,
        Project $project,
        int $departmentId,
    ): array {
        return [
            'allocation_file' => $upload,
            'project_id' => $project->id,
            'department_allocations' => [$departmentId => 100],
            'job_level_controls' => [$departmentId => 1],
            'job_level_allocations' => [
                $departmentId => [
                    'lead_engineer_checker' => ['mode' => 'not_allowed', 'hours' => null],
                    'senior_engineer' => ['mode' => 'not_allowed', 'hours' => null],
                    'engineer' => ['mode' => 'reserved', 'hours' => 40],
                    'designer' => ['mode' => 'shared', 'hours' => null],
                ],
            ],
        ];
    }

    private function headers(): array
    {
        return [
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
    }
}
