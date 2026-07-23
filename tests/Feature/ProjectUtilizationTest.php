<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ProjectUtilizationTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_project_manager_sees_approved_and_pending_hours_by_entry_discipline(): void
    {
        $home = $this->department(['name' => 'Home']);
        $discipline = $this->department(['name' => 'Engineering']);
        $manager = $this->userWithRole('employee', ['department_id' => $home->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $home->id]);
        $project = $this->project(['project_manager_id' => $manager->id, 'start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $discipline->id, 'allocated_hours' => 100]);

        foreach ([[Timesheet::STATUS_APPROVED, 12, 20], [Timesheet::STATUS_SUBMITTED, 5, 21]] as [$status, $hours, $week]) {
            $period = $this->openPeriod(['week_number' => $week, 'start_date' => $week === 20 ? '2026-05-11' : '2026-05-18', 'end_date' => $week === 20 ? '2026-05-17' : '2026-05-24']);
            $timesheet = $this->submittedTimesheet($worker, $period, $project, ['status' => $status]);
            $timesheet->entries()->first()->update(['department_id' => $discipline->id, 'regular_hours' => $hours, 'overtime_hours' => 0]);
        }

        $this->actingAs($manager)->get(route('projects.utilization', $project))
            ->assertOk()
            ->assertSee('Engineering')
            ->assertSee('12.00')
            ->assertSee('5.00')
            ->assertSee('83.00')
            ->assertSee('data-confirm="Send this correction request for the selected entries?"', false);
    }

    public function test_unrelated_employee_cannot_view_project_utilization(): void
    {
        $manager = $this->userWithRole('hod');
        $project = $this->project(['project_manager_id' => $manager->id]);
        $this->actingAs($this->userWithRole('employee'))->get(route('projects.utilization', $project))->assertForbidden();
    }

    public function test_manager_and_admin_see_people_grouped_by_entry_discipline(): void
    {
        $home = $this->department(['name' => 'Home Department']);
        $discipline = $this->department(['name' => 'Project Engineering']);
        $manager = $this->userWithRole('employee', ['department_id' => $home->id]);
        $worker = $this->userWithRole('employee', ['name' => 'Jamie Engineer', 'department_id' => $home->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->departmentAllocations()->create(['department_id' => $discipline->id, 'allocated_hours' => 100]);
        $period = $this->openPeriod();
        $timesheet = $this->submittedTimesheet($worker, $period, $project, ['status' => Timesheet::STATUS_APPROVED]);
        $timesheet->entries()->first()->update(['department_id' => $discipline->id, 'regular_hours' => 7, 'overtime_hours' => 1]);

        foreach ([$manager, $this->userWithRole('admin')] as $viewer) {
            $this->actingAs($viewer)->get(route('projects.utilization', $project))
                ->assertOk()
                ->assertSee('People charging')
                ->assertSee('Jamie Engineer')
                ->assertSee('8.00');
        }
    }

    public function test_utilization_lists_every_selected_user_with_their_project_category(): void
    {
        $department = $this->department(['name' => 'Engineering']);
        $manager = $this->userWithRole('employee', ['department_id' => $department->id]);
        $engineer = $this->userWithRole('employee', ['name' => 'Assigned Engineer', 'department_id' => $department->id]);
        $uncontrolledOnly = $this->userWithRole('hod', ['name' => 'Uncontrolled User', 'department_id' => $department->id]);
        $project = $this->project([
            'project_manager_id' => $manager->id,
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS,
        ]);
        $project->assignedUsers()->sync([
            $engineer->id => ['manpower_category' => 'engineer'],
            $uncontrolledOnly->id => ['manpower_category' => null],
        ]);
        $allocation = $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);
        foreach (array_keys(config('manpower_categories.labels')) as $category) {
            $allocation->manpowerCategoryAllocations()->create([
                'manpower_category' => $category,
                'allocated_hours' => $category === 'engineer' ? null : 0,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('projects.utilization', $project));

        $response->assertOk()
            ->assertSee('Project team categories')
            ->assertSee('Assigned Engineer')
            ->assertSee('Engineer')
            ->assertSee('Uncontrolled User')
            ->assertSee('Uncontrolled departments only');
    }

    public function test_utilization_date_range_filters_department_and_people_hours(): void
    {
        $department = $this->department(['name' => 'Design']);
        $manager = $this->userWithRole('employee', ['department_id' => $department->id]);
        $worker = $this->userWithRole('employee', ['name' => 'Date Filter Worker', 'department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);

        foreach ([['2026-05-11', 4, 20], ['2026-06-01', 9, 23]] as [$date, $hours, $week]) {
            $period = $this->openPeriod(['week_number' => $week, 'start_date' => $date, 'end_date' => Carbon::parse($date)->addDays(6)->toDateString()]);
            $timesheet = $this->submittedTimesheet($worker, $period, $project, ['status' => Timesheet::STATUS_APPROVED]);
            $timesheet->entries()->first()->update(['department_id' => $department->id, 'work_date' => $date, 'regular_hours' => $hours, 'overtime_hours' => 0]);
        }

        $this->actingAs($manager)->get(route('projects.utilization', $project).'?date_from=2026-05-01&date_to=2026-05-31')
            ->assertOk()
            ->assertSee('Date Filter Worker')
            ->assertSee('4.00')
            ->assertDontSee('9.00');
    }

    public function test_utilization_rejects_an_inverted_date_range(): void
    {
        $manager = $this->userWithRole('employee');
        $project = $this->project(['project_manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->get(route('projects.utilization', $project).'?date_from=2026-06-01&date_to=2026-05-01')
            ->assertSessionHasErrors('date_to');
    }

    public function test_project_manager_can_open_managed_project_register(): void
    {
        $manager = $this->userWithRole('employee');
        $owned = $this->project(['project_code' => 'MINE-100', 'project_name' => 'Managed by Me', 'project_manager_id' => $manager->id]);
        $other = $this->project(['project_code' => 'OTHER-200', 'project_name' => 'Managed Elsewhere', 'project_manager_id' => $this->userWithRole('hod')->id]);
        $owned->departmentAllocations()->create(['department_id' => $this->department()->id, 'allocated_hours' => 100]);

        $this->actingAs($manager)->get(route('managed-projects.index'))
            ->assertOk()
            ->assertSee('My Managed Projects')
            ->assertSee('MINE-100')
            ->assertSee('Managed by Me')
            ->assertSee('View utilization')
            ->assertDontSee('OTHER-200')
            ->assertDontSee('Managed Elsewhere');
    }

    public function test_admin_uses_management_projects_instead_of_managed_project_register(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('managed-projects.index'))
            ->assertForbidden();
    }

    public function test_employee_without_a_managed_project_cannot_open_or_see_managed_projects(): void
    {
        $employee = $this->userWithRole('employee');

        $this->actingAs($employee)
            ->get(route('managed-projects.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('employee.timesheets.index'))
            ->assertOk()
            ->assertDontSee('My Managed Projects');
    }

    public function test_allocation_can_decrease_but_not_below_submitted_and_approved_hours(): void
    {
        $department = $this->department(['name' => 'Design']);
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id, 'start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);

        foreach ([[Timesheet::STATUS_APPROVED, 60, 20], [Timesheet::STATUS_SUBMITTED, 20, 21]] as [$status, $hours, $week]) {
            $period = $this->openPeriod(['week_number' => $week, 'start_date' => $week === 20 ? '2026-05-11' : '2026-05-18', 'end_date' => $week === 20 ? '2026-05-17' : '2026-05-24']);
            $timesheet = $this->submittedTimesheet($worker, $period, $project, ['status' => $status]);
            $timesheet->entries()->first()->update(['department_id' => $department->id, 'regular_hours' => $hours]);
        }

        $payload = [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-01-01',
            'project_manager_id' => $manager->id,
            'department_allocations' => [$department->id => 80],
            'allocation_change_reason' => 'Align the budget with the current forecast.',
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_ALL_USERS,
        ];

        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertRedirect(route('manage.projects.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('project_department_allocations', ['project_id' => $project->id, 'department_id' => $department->id, 'allocated_hours' => 80]);

        $payload['department_allocations'][$department->id] = 79;
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors("department_allocations.$department->id");
        $this->assertDatabaseHas('project_department_allocations', ['project_id' => $project->id, 'department_id' => $department->id, 'allocated_hours' => 80]);
    }
}
