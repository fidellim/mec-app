<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Services\TimesheetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ManpowerCategoryAllocationWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_users_no_longer_have_or_require_a_global_job_level(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->assertFalse(Schema::hasColumn('users', 'job_level'));
        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'New Engineer',
            'email' => 'new.engineer@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-990',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new.engineer@example.com']);
        $this->actingAs($superAdmin)->get(route('manage.users.create'))
            ->assertOk()->assertDontSee('Job Level');
    }

    public function test_admin_assigns_optional_project_categories_and_controlled_projects_require_selected_users(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department();
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id, 'start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);
        $payload = $this->projectPayload($project, $manager->id, $department->id, 100, [
            'engineer' => ['mode' => 'reserved', 'hours' => 60],
            'designer' => ['mode' => 'shared', 'hours' => null],
        ], Project::ASSIGNMENT_ALL_USERS, [$employee->id]);

        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors('timesheet_assignment_mode');

        $response = $this->actingAs($admin)->get(route('manage.projects.edit', $project));
        $response->assertOk()
            ->assertSee('Lead Engineer / Checker')
            ->assertSee('Senior Engineer')
            ->assertSee('Engineer')
            ->assertSee('Designer')
            ->assertSee('Set category for selected users')
            ->assertSee('Control by Manpower Category');

        $payload['timesheet_assignment_mode'] = Project::ASSIGNMENT_SELECTED_USERS;
        $payload['allocation_change_reason'] = 'Configure category controls and assignments.';
        $payload['assigned_user_categories'] = [$employee->id => 'obsolete_category'];
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors("assigned_user_categories.$employee->id");

        $payload['assigned_user_categories'] = [$employee->id => 'engineer'];
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $employee->id,
            'manpower_category' => 'engineer',
        ]);
        $audit = AuditLog::where('action', 'project_updated')->latest('id')->firstOrFail();
        $this->assertSame('engineer', $audit->new_values['assigned_user_manpower_categories'][(string) $employee->id]);
    }

    public function test_employee_without_project_category_can_use_only_uncontrolled_departments(): void
    {
        Mail::fake();
        $controlled = $this->department(['name' => 'Mechanical']);
        $uncontrolled = $this->department(['name' => 'QA/QC']);
        $employee = $this->userWithRole('employee', ['department_id' => $uncontrolled->id]);
        $project = $this->controlledProject($controlled->id, 20, ['engineer' => null], users: [$employee], assignedCategory: null);
        $project->departmentAllocations()->create(['department_id' => $uncontrolled->id, 'allocated_hours' => 20]);
        $period = $this->openPeriod();

        $this->actingAs($employee)->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertDontSee('entries[0][manpower_category]', false);

        $uncontrolledEntry = $this->entry($project, $uncontrolled->id, '2026-05-11', 8);
        $uncontrolledEntry['manpower_category'] = 'engineer';
        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => [$uncontrolledEntry],
        ])->assertRedirect();

        $this->assertNull(TimesheetEntry::firstOrFail()->manpower_category_snapshot);

        $nextPeriod = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $nextPeriod->id,
            'submit' => '0',
            'entries' => [$this->entry($project, $controlled->id, '2026-05-18', 8)],
        ])->assertSessionHasErrors('entries.0.department_id');
    }

    public function test_one_project_category_applies_across_controlled_departments(): void
    {
        Mail::fake();
        $telecom = $this->department(['name' => 'Telecom']);
        $mechanical = $this->department(['name' => 'Mechanical']);
        $employee = $this->userWithRole('employee', ['department_id' => $telecom->id]);
        $project = $this->controlledProject($telecom->id, 40, ['engineer' => null], users: [$employee], assignedCategory: 'engineer');
        $this->addControlledDepartment($project, $mechanical->id, 40, ['engineer' => 20]);
        $period = $this->openPeriod();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => [
                $this->entry($project, $telecom->id, '2026-05-11', 8),
                $this->entry($project, $mechanical->id, '2026-05-12', 8),
            ],
        ])->assertRedirect();

        $entries = Timesheet::where('user_id', $employee->id)->firstOrFail()->entries()->orderBy('work_date')->get();
        $this->assertSame(['engineer', 'engineer'], $entries->pluck('manpower_category_snapshot')->all());
        $this->assertSame([
            TimesheetAllocationService::BUCKET_SHARED,
            TimesheetAllocationService::BUCKET_RESERVED,
        ], $entries->pluck('allocation_bucket_snapshot')->all());
    }

    public function test_injected_category_cannot_override_a_not_allowed_project_assignment(): void
    {
        Mail::fake();
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->controlledProject($department->id, 20, ['engineer' => null, 'designer' => 0], users: [$employee], assignedCategory: 'designer');
        $period = $this->openPeriod();
        $entry = $this->entry($project, $department->id, '2026-05-11', 1);
        $entry['manpower_category'] = 'engineer';

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => [$entry],
        ])->assertSessionHasErrors('entries.0.department_id');

        $this->assertDatabaseMissing('timesheets', [
            'user_id' => $employee->id,
            'timesheet_period_id' => $period->id,
        ]);
    }

    public function test_project_category_is_derived_for_drafts_and_hidden_from_employee_details(): void
    {
        Mail::fake();
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $admin = $this->userWithRole('admin');
        $project = $this->controlledProject($department->id, 20, ['engineer' => 10], users: [$employee], assignedCategory: 'engineer');
        $period = $this->openPeriod();
        $entries = [$this->entry($project, $department->id, '2026-05-11', 8)];

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '0',
            'entries' => $entries,
        ])->assertRedirect();

        $draft = Timesheet::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('engineer', $draft->entries()->first()->manpower_category_snapshot);
        $this->assertNull($draft->entries()->first()->allocation_bucket_snapshot);

        $this->actingAs($employee)->put(route('employee.timesheets.update', $draft), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $entry = $draft->fresh()->entries()->firstOrFail();
        $this->assertSame('engineer', $entry->manpower_category_snapshot);
        $this->assertSame(TimesheetAllocationService::BUCKET_RESERVED, $entry->allocation_bucket_snapshot);
        $this->actingAs($employee)->get(route('employee.timesheets.show', $draft))
            ->assertOk()->assertDontSee('Manpower Category');
        $this->actingAs($hod)->get(route('hod.timesheets.show', $draft))
            ->assertOk()->assertDontSee('Manpower Category');
        $this->actingAs($admin)->get(route('admin.timesheets.show', $draft))
            ->assertOk()->assertSee('Manpower Category')->assertSee('Engineer');
    }

    public function test_existing_editable_controlled_row_is_preserved_until_admin_confirms_category(): void
    {
        Mail::fake();
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->controlledProject($department->id, 30, [
            'engineer' => null,
            'senior_engineer' => null,
        ], users: [$employee], assignedCategory: null);
        $period = $this->openPeriod();
        $draft = $this->submittedTimesheet($employee, $period, $project, ['status' => Timesheet::STATUS_DRAFT]);
        $savedEntry = $draft->entries()->first();
        $savedEntry->update(['manpower_category_snapshot' => 'engineer']);
        $entry = $this->entry($project, $department->id, '2026-05-11', 8) + ['id' => $savedEntry->id];

        $this->actingAs($employee)->put(route('employee.timesheets.update', $draft), [
            'timesheet_period_id' => $period->id,
            'submit' => '0',
            'entries' => [$entry],
        ])->assertRedirect();
        $this->assertSame('engineer', $draft->fresh()->entries()->first()->manpower_category_snapshot);

        $this->actingAs($employee)->put(route('employee.timesheets.update', $draft), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => [$entry],
        ])->assertSessionHasErrors('entries.0.department_id');

        $project->assignedUsers()->updateExistingPivot($employee->id, ['manpower_category' => 'senior_engineer']);
        $this->actingAs($employee)->put(route('employee.timesheets.update', $draft), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => [$entry],
        ])->assertRedirect();

        $this->assertSame('senior_engineer', $draft->fresh()->entries()->first()->manpower_category_snapshot);
    }

    public function test_reserved_pool_enforces_atomic_submission_and_ignores_drafts(): void
    {
        Mail::fake();
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->controlledProject($department->id, 20, ['engineer' => 10], users: [$employee], assignedCategory: 'engineer');
        $existing = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $existing->entries()->first()->update([
            'regular_hours' => 8,
            'manpower_category_snapshot' => 'engineer',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);
        $draftPeriod = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $draft = $this->submittedTimesheet($employee, $draftPeriod, $project, ['status' => Timesheet::STATUS_DRAFT]);
        $draft->entries()->first()->update(['regular_hours' => 20, 'manpower_category_snapshot' => 'engineer']);

        $nextPeriod = $this->openPeriod(['week_number' => 22, 'start_date' => '2026-05-25', 'end_date' => '2026-05-31']);
        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $nextPeriod->id,
            'submit' => '1',
            'entries' => [$this->entry($project, $department->id, '2026-05-25', 3)],
        ])->assertSessionHasErrors('entries.0.department_id');

        $this->assertDatabaseMissing('timesheets', ['user_id' => $employee->id, 'timesheet_period_id' => $nextPeriod->id]);
    }

    public function test_legacy_configuration_blocks_submission_and_legacy_usage_is_reported(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach($employee, ['manpower_category' => 'engineer']);
        $allocation = $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 20]);
        $allocation->manpowerCategoryAllocations()->create(['manpower_category' => 'senior', 'allocated_hours' => 10]);
        $historical = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $historical->entries()->first()->update([
            'manpower_category_snapshot' => 'senior',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);

        $nextPeriod = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $response = $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $nextPeriod->id,
            'submit' => '1',
            'entries' => [$this->entry($project, $department->id, '2026-05-18', 1)],
        ]);
        $response->assertSessionHasErrors('entries.0.department_id');
        $this->assertSame(TimesheetAllocationService::LEGACY_SETUP_MESSAGE, session('errors')->first('entries.0.department_id'));

        $this->actingAs($manager)->get(route('projects.utilization', $project))
            ->assertOk()->assertSee('Legacy / Unclassified')->assertSee('deducted from the shared remainder');
    }

    public function test_project_controls_validate_totals_reason_and_reserved_usage(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->controlledProject($department->id, 100, ['engineer' => 40, 'designer' => null], [
            'project_manager_id' => $manager->id,
            'start_date' => '2026-01-01',
        ], [$employee], 'engineer');
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $timesheet->entries()->first()->update([
            'regular_hours' => 30,
            'manpower_category_snapshot' => 'engineer',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);

        $payload = $this->projectPayload($project, $manager->id, $department->id, 100, [
            'engineer' => ['mode' => 'reserved', 'hours' => 29],
            'designer' => ['mode' => 'shared', 'hours' => null],
        ], assignedUserIds: [$employee->id], assignedUserCategories: [$employee->id => 'engineer']);
        $payload['allocation_change_reason'] = 'Reforecast category reservations.';
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors("job_level_allocations.$department->id.engineer.hours");

        $payload['job_level_allocations'][$department->id]['engineer']['hours'] = 40;
        $payload['job_level_allocations'][$department->id]['designer']['mode'] = 'not_allowed';
        $payload['job_level_allocations'][$department->id]['senior_engineer']['mode'] = 'shared';
        unset($payload['allocation_change_reason']);
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors('allocation_change_reason');

        $payload['allocation_change_reason'] = 'Keep the approved delivery reservation.';
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)->assertRedirect();
        $this->assertSame(4, $project->departmentAllocations()->first()->manpowerCategoryAllocations()->count());
        $audit = AuditLog::where('action', 'project_allocations_updated')->latest()->firstOrFail();
        $this->assertSame('Keep the approved delivery reservation.', $audit->new_values['reason']);
    }

    private function controlledProject(
        int $departmentId,
        float $hours,
        array $states,
        array $attributes = [],
        array $users = [],
        ?string $assignedCategory = 'engineer',
    ): Project {
        $project = $this->project(array_merge([
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ], $attributes));
        $project->assignedUsers()->sync(collect($users)->mapWithKeys(fn ($user) => [
            $user->id => ['manpower_category' => $assignedCategory],
        ])->all());
        $this->addControlledDepartment($project, $departmentId, $hours, $states);

        return $project;
    }

    private function addControlledDepartment(Project $project, int $departmentId, float $hours, array $states): void
    {
        $allocation = $project->departmentAllocations()->create(['department_id' => $departmentId, 'allocated_hours' => $hours]);
        foreach (array_keys(config('manpower_categories.labels')) as $category) {
            $allocation->manpowerCategoryAllocations()->create([
                'manpower_category' => $category,
                'allocated_hours' => array_key_exists($category, $states) ? $states[$category] : 0,
            ]);
        }
    }

    private function entry(Project $project, int $departmentId, string $date, float $hours): array
    {
        return [
            'work_date' => $date,
            'attendance_code' => 'O100',
            'project_id' => $project->id,
            'department_id' => $departmentId,
            'regular_hours' => $hours,
            'overtime_hours' => 0,
            'remarks' => '',
        ];
    }

    private function projectPayload(
        Project $project,
        int $managerId,
        int $departmentId,
        float $hours,
        array $overrides,
        string $assignmentMode = Project::ASSIGNMENT_SELECTED_USERS,
        array $assignedUserIds = [],
        array $assignedUserCategories = [],
    ): array {
        $categories = collect(config('manpower_categories.labels'))
            ->mapWithKeys(fn ($label, $category) => [$category => ['mode' => 'not_allowed', 'hours' => null]])->all();
        foreach ($overrides as $category => $value) {
            $categories[$category] = $value;
        }

        return [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-01-01',
            'project_manager_id' => $managerId,
            'department_allocations' => [$departmentId => $hours],
            'job_level_controls' => [$departmentId => 1],
            'job_level_allocations' => [$departmentId => $categories],
            'is_active' => '1',
            'timesheet_assignment_mode' => $assignmentMode,
            'assigned_user_ids' => $assignedUserIds,
            'assigned_user_categories' => $assignedUserCategories,
        ];
    }
}
