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

class ProjectAssignmentSpreadsheetWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_admin_can_download_a_project_specific_xlsx_assignment_template(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['name' => 'Mechanical']);
        $employee = $this->userWithRole('employee', [
            'name' => 'Template Engineer',
            'employee_code' => 'MEC-HR-2026-701',
            'department_id' => $department->id,
        ]);
        $hod = $this->userWithRole('hod', [
            'name' => 'Template HOD',
            'employee_code' => 'MEC-HR-2026-702',
            'department_id' => $department->id,
        ]);
        $project = $this->project([
            'project_manager_id' => $hod->id,
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach($employee, ['manpower_category' => 'engineer']);

        $response = $this->actingAs($admin)->get(route('manage.projects.assignment-template', [
            'project_id' => $project->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'assignment-template-');
        file_put_contents($path, $response->streamedContent());

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('Assignments');
            $this->assertNotNull($sheet);
            $this->assertSame([
                'Employee Number',
                'Employee Name',
                'Role',
                'Home Department',
                'Assigned',
                'Manpower Category',
            ], $sheet->rangeToArray('A1:F1')[0]);

            $rows = collect($sheet->rangeToArray('A2:F3'))->keyBy(fn ($row) => $row[0]);
            $this->assertSame('Yes', $rows->get($employee->employee_code)[4]);
            $this->assertSame('Engineer', $rows->get($employee->employee_code)[5]);
            $this->assertSame('No', $rows->get($hod->employee_code)[4]);
            $this->assertSame('list', $sheet->getCell('E2')->getDataValidation()->getType());
            $this->assertSame('list', $sheet->getCell('F2')->getDataValidation()->getType());
            $this->assertNotNull($spreadsheet->getSheetByName('Instructions'));
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_valid_preview_reports_assignment_changes_and_deletes_the_uploaded_file(): void
    {
        $admin = $this->userWithRole('admin');
        $controlled = $this->department(['name' => 'Mechanical']);
        $uncontrolled = $this->department(['name' => 'QA/QC']);
        $manager = $this->userWithRole('hod', ['department_id' => $controlled->id]);
        $existing = $this->userWithRole('employee', [
            'employee_code' => 'MEC-HR-2026-711',
            'department_id' => $controlled->id,
        ]);
        $removal = $this->userWithRole('employee', [
            'employee_code' => 'MEC-HR-2026-712',
            'department_id' => $uncontrolled->id,
        ]);
        $newUser = $this->userWithRole('employee', [
            'employee_code' => 'MEC-HR-2026-713',
            'department_id' => $uncontrolled->id,
        ]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach([
            $existing->id => ['manpower_category' => 'engineer'],
            $removal->id => ['manpower_category' => null],
        ]);

        [$upload, $path] = $this->assignmentUpload([
            [$existing->employee_code, $existing->name, 'Employee', $controlled->name, 'Yes', 'Senior Engineer'],
            [$removal->employee_code, $removal->name, 'Employee', $uncontrolled->name, 'No', ''],
            [$newUser->employee_code, $newUser->name, 'Employee', $uncontrolled->name, 'Yes', ''],
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.assignment-import.preview'), $this->previewPayload(
                $upload,
                $project,
                $controlled->id,
                $uncontrolled->id,
                [$existing->id, $removal->id],
                [$existing->id => 'engineer', $removal->id => null],
            ));

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('summary.assigned', 1)
            ->assertJsonPath('summary.category_changed', 1)
            ->assertJsonPath('summary.removed', 1)
            ->assertJsonPath('summary.uncontrolled_only', 1);
        $this->assertNotEmpty($response->json('token'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_preview_is_all_or_nothing_and_reports_row_errors(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['name' => 'Mechanical']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', [
            'employee_code' => 'MEC-HR-2026-721',
            'department_id' => $department->id,
        ]);
        $project = $this->project(['project_manager_id' => $manager->id]);

        [$upload, $path] = $this->assignmentUpload([
            [$employee->employee_code, $employee->name, 'Employee', $department->name, 'No', 'Engineer'],
            ['MEC-HR-2026-999', 'Unknown Employee', 'Employee', $department->name, 'Yes', 'Designer'],
        ]);

        $payload = $this->previewPayload($upload, $project, $department->id);
        $payload['job_level_allocations'][$department->id]['designer']['mode'] = 'not_allowed';
        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.assignment-import.preview'), $payload);

        $response->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('token', null)
            ->assertJsonPath('summary.errors', 2);
        $messages = collect($response->json('rows'))->flatMap(fn ($row) => $row['errors']);
        $this->assertTrue($messages->contains('Clear Manpower Category when Assigned is No.'));
        $this->assertTrue($messages->contains('Employee number does not match a user.'));
        $this->assertTrue($messages->contains('Designer is not Shared or Reserved in any controlled discipline.'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_preview_rejects_formulas_in_editable_columns(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department();
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);

        [$upload, $path] = $this->assignmentUpload([
            ['MEC-HR-2026-731', 'Formula User', 'Employee', $department->name, 'Yes', 'Engineer'],
        ], function (Spreadsheet $spreadsheet) {
            $spreadsheet->getActiveSheet()->setCellValueExplicit('E2', '=1+1', DataType::TYPE_FORMULA);
        });

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.assignment-import.preview'), $this->previewPayload(
                $upload,
                $project,
                $department->id,
            ));

        $response->assertOk()->assertJsonPath('valid', false);
        $this->assertStringContainsString(
            'Assigned cannot contain a formula.',
            implode(' ', $response->json('rows.0.errors')),
        );
        $this->assertFileDoesNotExist($path);
    }

    public function test_upload_is_deleted_when_file_validation_fails(): void
    {
        $admin = $this->userWithRole('admin');
        $path = tempnam(sys_get_temp_dir(), 'invalid-assignment-upload-');
        file_put_contents($path, "Employee Number,Assigned\nMEC-HR-2026-739,Yes\n");
        $upload = new UploadedFile($path, 'project_assignments.csv', 'text/csv', null, true);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.assignment-import.preview'), [
                'assignment_file' => $upload,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignment_file');

        $this->assertFileDoesNotExist($path);
    }

    public function test_preview_and_project_save_block_removal_with_editable_timesheet_rows(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['name' => 'QA/QC']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', [
            'employee_code' => 'MEC-HR-2026-741',
            'department_id' => $department->id,
        ]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'start_date' => '2026-01-01',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach($employee, ['manpower_category' => null]);
        $project->departmentAllocations()->create([
            'department_id' => $department->id,
            'allocated_hours' => 100,
        ]);
        $this->submittedTimesheet(
            $employee,
            $this->openPeriod(),
            $project,
            ['status' => Timesheet::STATUS_DRAFT, 'submitted_at' => null],
        );

        [$upload, $path] = $this->assignmentUpload([
            [$employee->employee_code, $employee->name, 'Employee', $department->name, 'No', ''],
        ]);
        $previewPayload = [
            'assignment_file' => $upload,
            'project_id' => $project->id,
            'department_allocations' => [$department->id => 100],
            'job_level_controls' => [$department->id => 0],
            'assigned_user_ids' => [$employee->id],
            'assigned_user_categories' => [$employee->id => null],
        ];

        $preview = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.assignment-import.preview'), $previewPayload);

        $preview->assertOk()->assertJsonPath('valid', false);
        $this->assertStringContainsString('Draft (1)', implode(' ', $preview->json('rows.0.errors')));
        $this->assertFileDoesNotExist($path);

        $this->actingAs($admin)->putJson(route('manage.projects.update', $project), [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-01-01',
            'project_manager_id' => $manager->id,
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
            'department_allocations' => [$department->id => 100],
            'job_level_controls' => [$department->id => 0],
            'assigned_user_ids' => [],
            'assigned_user_categories' => [],
        ])->assertJsonValidationErrors('assigned_user_ids');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_valid_import_token_records_audit_summary_when_project_is_saved(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department(['name' => 'Mechanical']);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', [
            'employee_code' => 'MEC-HR-2026-751',
            'department_id' => $department->id,
        ]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'start_date' => '2026-01-01',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);

        [$upload] = $this->assignmentUpload([
            [$employee->employee_code, $employee->name, 'Employee', $department->name, 'Yes', 'Engineer'],
        ]);
        $previewPayload = $this->previewPayload($upload, $project, $department->id);
        $preview = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('manage.projects.assignment-import.preview'), $previewPayload);
        $preview->assertOk()->assertJsonPath('valid', true);

        $this->actingAs($admin)->put(route('manage.projects.update', $project), [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-01-01',
            'project_manager_id' => $manager->id,
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
            'department_allocations' => [$department->id => 100],
            'job_level_controls' => [$department->id => 1],
            'job_level_allocations' => [$department->id => $this->categoryModes('engineer')],
            'assigned_user_ids' => [$employee->id],
            'assigned_user_categories' => [$employee->id => 'engineer'],
            'assignment_import_token' => $preview->json('token'),
            'allocation_change_reason' => 'Configure the initial controlled discipline.',
        ])->assertRedirect(route('manage.projects.index'));

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $employee->id,
            'manpower_category' => 'engineer',
        ]);
        $audit = AuditLog::where('action', 'project_assignment_excel_imported')->latest('id')->firstOrFail();
        $this->assertSame('excel_import', $audit->new_values['source']);
        $this->assertSame(1, $audit->new_values['assigned_count']);
        $this->assertSame(0, $audit->new_values['removed_count']);
        $this->assertSame(1, $audit->new_values['final_assigned_count']);
        $this->assertArrayNotHasKey('filename', $audit->new_values);
    }

    public function test_employee_cannot_access_assignment_spreadsheet_endpoints(): void
    {
        $employee = $this->userWithRole('employee');

        $this->actingAs($employee)
            ->get(route('manage.projects.assignment-template'))
            ->assertForbidden();
        $this->actingAs($employee)
            ->post(route('manage.projects.assignment-import.preview'))
            ->assertForbidden();
    }

    private function previewPayload(
        UploadedFile $upload,
        Project $project,
        int $controlledDepartmentId,
        ?int $uncontrolledDepartmentId = null,
        array $assignedUserIds = [],
        array $assignedUserCategories = [],
    ): array {
        $allocations = [$controlledDepartmentId => 100];
        $controls = [$controlledDepartmentId => 1];
        if ($uncontrolledDepartmentId) {
            $allocations[$uncontrolledDepartmentId] = 50;
            $controls[$uncontrolledDepartmentId] = 0;
        }

        return [
            'assignment_file' => $upload,
            'project_id' => $project->id,
            'department_allocations' => $allocations,
            'job_level_controls' => $controls,
            'job_level_allocations' => [
                $controlledDepartmentId => $this->categoryModes('engineer', 'senior_engineer'),
            ],
            'assigned_user_ids' => $assignedUserIds,
            'assigned_user_categories' => $assignedUserCategories,
        ];
    }

    private function categoryModes(string ...$allowedCategories): array
    {
        return collect(config('manpower_categories.labels'))->mapWithKeys(fn ($label, $category) => [
            $category => [
                'mode' => in_array($category, $allowedCategories, true) ? 'shared' : 'not_allowed',
                'hours' => null,
            ],
        ])->all();
    }

    private function assignmentUpload(array $rows, ?callable $mutate = null): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Assignments');
        $sheet->fromArray([
            'Employee Number',
            'Employee Name',
            'Role',
            'Home Department',
            'Assigned',
            'Manpower Category',
        ], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        if ($mutate) {
            $mutate($spreadsheet);
        }

        $path = tempnam(sys_get_temp_dir(), 'assignment-upload-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return [
            new UploadedFile(
                $path,
                'project_assignments.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            ),
            $path,
        ];
    }
}
