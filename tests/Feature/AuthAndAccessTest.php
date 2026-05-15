<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AuthAndAccessTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_active_user_can_login_and_inactive_user_cannot(): void
    {
        $active = $this->userWithRole('employee', ['email' => 'active@example.com']);
        $inactive = $this->userWithRole('employee', ['email' => 'inactive@example.com', 'is_active' => false]);

        $this->post(route('login'), [
            'email' => $active->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        auth()->logout();

        $this->post(route('login'), [
            'email' => $inactive->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_role_middleware_limits_management_and_admin_pages(): void
    {
        $employee = $this->userWithRole('employee');
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($employee)->get(route('manage.users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.timesheets.index'))->assertOk();
        $this->actingAs($admin)->get(route('manage.users.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('manage.users.index'))->assertOk();
    }
}
