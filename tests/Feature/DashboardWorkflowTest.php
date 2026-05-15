<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class DashboardWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_dashboard_renders_for_each_role(): void
    {
        $department = $this->department();
        $roles = ['employee', 'hod', 'admin', 'super_admin'];

        foreach ($roles as $role) {
            $user = $this->userWithRole($role, [
                'department_id' => in_array($role, ['employee', 'hod'], true) ? $department->id : null,
            ]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Dashboard');
        }
    }
}
