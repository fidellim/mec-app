<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ProjectTimesheetAssignmentWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_new_project_defaults_to_selected_users_and_admin_can_save_assignments(): void
    {
        $admin = $this->userWithRole('admin');
        $operations = $this->department(['name' => 'Operations']);
        $engineering = $this->department(['name' => 'Engineering']);
        $employee = $this->userWithRole('employee', ['department_id' => $operations->id]);
        $hod = $this->userWithRole('hod', ['department_id' => $operations->id]);
        $engineeringEmployee = $this->userWithRole('employee', ['department_id' => $engineering->id]);

        $this->actingAs($admin)
            ->get(route('manage.projects.create'))
            ->assertOk()
            ->assertSee('Selected users')
            ->assertSee('Operations')
            ->assertSee('Engineering')
            ->assertSee($employee->email)
            ->assertSee($hod->email)
            ->assertSee($engineeringEmployee->email)
            ->assertSee('data-department-toggle', false)
            ->assertSee('data-department-summary', false)
            ->assertDontSee($admin->email);

        $this->actingAs($admin)->post(route('manage.projects.store'), [
            'project_code' => 'CLIENT-101',
            'project_name' => 'Restricted Client Project',
            'client_name' => 'Client One',
            'start_date' => '2026-07-17',
            'project_manager_id' => $hod->id,
            'department_allocations' => [$operations->id => 100],
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
            'assigned_user_ids' => [$employee->id, $hod->id],
        ])->assertRedirect(route('manage.projects.index'));

        $project = Project::where('project_code', 'CLIENT-101')->firstOrFail();
        $this->assertSame(Project::ASSIGNMENT_SELECTED_USERS, $project->timesheet_assignment_mode);
        $this->assertEqualsCanonicalizing([$employee->id, $hod->id], $project->assignedUsers()->pluck('users.id')->all());

        $log = AuditLog::where('action', 'project_created')->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing([$employee->id, $hod->id], $log->new_values['assigned_user_ids']);
    }

    public function test_admin_and_super_admin_accounts_cannot_be_saved_as_selected_project_users(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $department = $this->department();
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);

        $this->actingAs($superAdmin)->post(route('manage.projects.store'), [
            'project_code' => 'INVALID-ASSIGNEE',
            'project_name' => 'Invalid Assignee Project',
            'start_date' => '2026-07-17',
            'project_manager_id' => $manager->id,
            'department_allocations' => [$department->id => 100],
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
            'assigned_user_ids' => [$admin->id],
        ])->assertSessionHasErrors('assigned_user_ids.0');

        $this->assertDatabaseMissing('projects', ['project_code' => 'INVALID-ASSIGNEE']);
    }

    public function test_all_users_mode_is_dynamic_and_selected_mode_filters_timesheet_form(): void
    {
        $period = $this->openPeriod();
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $otherEmployee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $allUsersProject = $this->project([
            'project_code' => 'OVERHEAD',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_ALL_USERS,
        ]);
        $selectedProject = $this->project([
            'project_code' => 'PRIVATE',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $selectedProject->assignedUsers()->attach($employee);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('OVERHEAD')
            ->assertSee('PRIVATE');

        $this->actingAs($otherEmployee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('OVERHEAD')
            ->assertDontSee('PRIVATE');
    }

    public function test_server_rejects_unassigned_project_for_employee_and_admin_timesheets(): void
    {
        $period = $this->openPeriod();
        $project = $this->project([
            'project_code' => 'PRIVATE',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);

        foreach (['employee', 'admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role, ['department_id' => $this->department()->id]);

            $this->actingAs($user)->post(route('employee.timesheets.store'), [
                'timesheet_period_id' => $period->id,
                'entries' => $this->validEntries($project),
            ])->assertSessionHasErrors('entries.0.project_id');

            $this->assertDatabaseMissing('timesheets', [
                'user_id' => $user->id,
                'timesheet_period_id' => $period->id,
            ]);
        }
    }

    public function test_assigned_user_can_save_but_loses_access_immediately_after_unassignment(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project([
            'project_code' => 'PRIVATE',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach($employee);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $timesheet = Timesheet::where('user_id', $employee->id)->firstOrFail();
        $project->assignedUsers()->detach($employee);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.edit', $timesheet))
            ->assertOk()
            ->assertSee('PRIVATE', false)
            ->assertSee('unavailable');

        $this->actingAs($employee)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertSessionHasErrors('entries.0.project_id');

        $this->assertSame('draft', $timesheet->fresh()->status);
        $this->assertDatabaseHas('timesheet_entries', [
            'timesheet_id' => $timesheet->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_project_assignment_update_is_audited_and_keeps_saved_selections_in_all_users_mode(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->userWithRole('employee');
        $manager = $this->userWithRole('hod');
        $department = $this->department();
        $project = $this->project([
            'project_code' => 'SWITCH-1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->attach($employee);

        $this->actingAs($admin)->put(route('manage.projects.update', $project), [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-07-17',
            'project_manager_id' => $manager->id,
            'department_allocations' => [$department->id => 100],
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_ALL_USERS,
            'assigned_user_ids' => [$employee->id],
        ])->assertRedirect(route('manage.projects.index'));

        $this->assertSame(Project::ASSIGNMENT_ALL_USERS, $project->fresh()->timesheet_assignment_mode);
        $this->assertTrue($project->assignedUsers()->whereKey($employee->id)->exists());
        $log = AuditLog::where('action', 'project_updated')->latest('id')->firstOrFail();
        $this->assertSame(Project::ASSIGNMENT_SELECTED_USERS, $log->old_values['timesheet_assignment_mode']);
        $this->assertSame(Project::ASSIGNMENT_ALL_USERS, $log->new_values['timesheet_assignment_mode']);
        $this->assertSame([$employee->id], $log->new_values['assigned_user_ids']);
    }

    public function test_timesheet_discipline_must_participate_when_project_has_allocations(): void
    {
        $operations = $this->department(['name' => 'Operations']);
        $engineering = $this->department(['name' => 'Engineering']);
        $employee = $this->userWithRole('employee', ['department_id' => $operations->id]);
        $period = $this->openPeriod();
        $project = $this->project(['project_code' => 'DISCIPLINE-CONTROL']);
        $project->departmentAllocations()->create(['department_id' => $engineering->id, 'allocated_hours' => 100]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project, [
                '2026-05-11' => ['department_id' => $operations->id],
            ]),
        ])->assertSessionHasErrors('entries.0.department_id');

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project, [
                '2026-05-11' => ['department_id' => $engineering->id],
            ]),
        ])->assertRedirect();

        $timesheet = Timesheet::where('user_id', $employee->id)->firstOrFail();
        $this->assertDatabaseHas('timesheet_entries', [
            'timesheet_id' => $timesheet->id,
            'project_id' => $project->id,
            'department_id' => $engineering->id,
        ]);
    }
}
