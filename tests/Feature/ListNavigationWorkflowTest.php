<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ListNavigationWorkflowTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_user_creation_opens_profile_and_retains_list_context(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $context = ['role' => 'employee', 'region' => 'uae', 'page' => '2'];
        $response = $this->post(route('manage.users.store'), [
            'name' => 'New Navigation Employee', 'email' => 'navigation@example.com',
            'password' => 'password123', 'employee_code' => 'MEC-HR-2026-9876',
            'role' => 'employee', 'is_active' => '1', 'list' => $context,
        ]);
        $user = User::where('email', 'navigation@example.com')->firstOrFail();
        $response->assertRedirect(route('manage.users.show', ['user' => $user, 'list' => $context]))
            ->assertSessionHas('success', 'User created.');
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('New Navigation Employee')
            ->assertSee(e(route('manage.users.index', $context)), false);
    }

    public function test_user_context_survives_validation_and_update_for_both_admin_roles(): void
    {
        $employee = $this->userWithRole('employee');
        $department = $this->department();
        $context = ['department_id' => (string) $department->id, 'role' => 'employee', 'region' => 'uae', 'search' => [$employee->name], 'page' => '3'];
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $edit = route('manage.users.edit', ['user' => $employee, 'list' => $context]);
            $show = route('manage.users.show', ['user' => $employee, 'list' => $context]);
            $this->get($show)->assertOk()->assertSee(e($edit), false)
                ->assertSee(e(route('manage.users.index', $context)), false);
            $this->get($edit)->assertOk()->assertSee('name="list[search][]"', false);
            $this->from($edit)->put(route('manage.users.update', $employee), ['name' => '', 'list' => $context])
                ->assertRedirect($edit)->assertSessionHasErrors('name');
            $this->get($edit)->assertOk()->assertSee('name="list[page]" value="3"', false);
            $payload = [
                'name' => 'Changed Name', 'email' => $employee->email,
                'employee_code' => $employee->employee_code, 'role' => 'employee',
                'is_active' => '1', 'list' => $context,
            ];
            $this->put(route('manage.users.update', $employee), $payload)->assertRedirect($show);
            $this->get($show)->assertOk()->assertSee('Changed Name')
                ->assertSee(e(route('manage.users.index', $context)), false);
        }
    }

    public function test_project_context_survives_tabs_filters_and_save(): void
    {
        $manager = $this->userWithRole('employee');
        $department = $this->department();
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);
        $context = ['search' => 'Original search', 'status' => 'active', 'page' => '2'];
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $overview = route('projects.utilization', ['project' => $project, 'tab' => 'overview', 'list' => $context]);
            $edit = route('manage.projects.edit', ['project' => $project, 'list' => $context]);
            $this->get($overview)->assertOk()->assertSee(e($edit), false)
                ->assertSee(e(route('manage.projects.index', $context)), false);
            $this->get(route('projects.utilization', ['project' => $project, 'list' => $context, 'date_from' => '2026-01-01']))
                ->assertOk()->assertSee(e($overview), false)->assertSee('name="list[search]"', false);
            $this->get($edit)->assertOk()->assertSee('name="list[status]" value="active"', false);
            $this->put(route('manage.projects.update', $project), [
                'project_code' => $project->project_code, 'project_name' => 'Updated Navigation Project',
                'start_date' => '2026-01-01', 'project_manager_id' => $manager->id,
                'is_active' => '1', 'timesheet_assignment_mode' => Project::ASSIGNMENT_ALL_USERS,
                'department_allocations' => [$department->id => 100], 'list' => $context,
            ])->assertRedirect($overview);
        }
    }

    public function test_list_links_capture_filters_and_separate_tabs_do_not_overwrite_context(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $employee = $this->userWithRole('employee');
        $project = $this->project();
        $userContext = ['role' => 'employee', 'search' => [$employee->name]];
        $projectContext = ['search' => $project->project_code, 'status' => 'active'];
        $this->get(route('manage.users.index', $userContext))->assertOk()
            ->assertSee(e(route('manage.users.show', ['user' => $employee, 'list' => $userContext])), false)
            ->assertSee(e(route('manage.users.create', ['list' => $userContext])), false);
        // A second browser tab has its own URL rather than session-wide list state.
        $this->get(route('manage.projects.index', $projectContext))->assertOk()
            ->assertSee(e(route('manage.projects.edit', ['project' => $project, 'list' => $projectContext])), false);
        $this->get(route('manage.users.show', ['user' => $employee, 'list' => $userContext]))->assertOk()
            ->assertSee(e(route('manage.users.index', $userContext)), false);
        $this->get(route('manage.users.show', $employee))->assertOk()
            ->assertSee('href="'.route('manage.users.index').'"', false);
    }

    public function test_invalid_context_is_ignored_and_permissions_are_unchanged(): void
    {
        $employee = $this->userWithRole('employee');
        $this->actingAs($this->userWithRole('admin'));
        $invalid = ['page' => '-2', 'search' => [['nested']], 'department_id' => '999999', 'role' => 'super_admin', 'return_url' => 'https://untrusted-navigation.invalid', 'region' => 'bad'];
        $this->get(route('manage.users.show', ['user' => $employee, 'list' => $invalid]))->assertOk()
            ->assertSee('href="'.route('manage.users.index').'"', false)->assertDontSee('https://untrusted-navigation.invalid');
        $this->get(route('manage.users.show', ['user' => $employee, 'list' => 'https://untrusted-navigation.invalid']))->assertOk()
            ->assertSee('href="'.route('manage.users.index').'"', false);
        $superAdmin = $this->userWithRole('super_admin');
        $this->get(route('manage.users.show', ['user' => $superAdmin, 'list' => $invalid]))->assertForbidden();
    }

    public function test_out_of_range_list_pages_fall_back_without_losing_filters(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $this->get(route('manage.users.index', ['role' => 'employee', 'page' => 99]))
            ->assertRedirect(route('manage.users.index', ['role' => 'employee', 'page' => 1]));
        $this->get(route('manage.projects.index', ['status' => 'active', 'search' => 'Missing', 'page' => 99]))
            ->assertRedirect(route('manage.projects.index', ['status' => 'active', 'search' => 'Missing', 'page' => 1]));
    }
}
