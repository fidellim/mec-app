<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class GuideWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_guide_to_login(): void
    {
        $this->get(route('guide'))->assertRedirect(route('login'));
    }

    public function test_guide_renders_employee_content_for_employee(): void
    {
        $employee = $this->userWithRole('employee');

        $this->actingAs($employee)
            ->get(route('guide'))
            ->assertOk()
            ->assertSee('My Guide')
            ->assertSee('Employee guide for using MEC Portal.')
            ->assertSee('Splitting One Day Across Projects')
            ->assertDontSee('Submission Tracker')
            ->assertDontSee('Automation did not run');
    }

    public function test_guide_renders_hod_content_for_hod(): void
    {
        $hod = $this->userWithRole('hod');

        $this->actingAs($hod)
            ->get(route('guide'))
            ->assertOk()
            ->assertSee('Head of Department guide for using MEC Portal.')
            ->assertSee('Submission Tracker')
            ->assertDontSee('Automation did not run');
    }

    public function test_guide_renders_admin_content_for_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('guide'))
            ->assertOk()
            ->assertSee('Admin guide for using MEC Portal.')
            ->assertSee('handle allowed approval actions')
            ->assertDontSee('Manage Users')
            ->assertDontSee('Automation did not run');
    }

    public function test_guide_renders_super_admin_content_for_super_admin(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('guide'))
            ->assertOk()
            ->assertSee('Super Admin guide for using MEC Portal.')
            ->assertSee('Automation did not run')
            ->assertSee('Manage Users');
    }
}
