<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
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

    public function test_expired_authenticated_browser_request_redirects_to_login_with_message(): void
    {
        $user = $this->userWithRole('employee');

        Route::middleware(['web', 'auth'])->post('/__test/expired-session', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $this->actingAs($user)
            ->post('/__test/expired-session')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session expired. Please sign in again.');

        $this->assertGuest();
    }

    public function test_expired_guest_browser_request_redirects_to_login_with_message(): void
    {
        Route::middleware('web')->post('/__test/expired-guest-session', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $this->post('/__test/expired-guest-session')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session expired. Please sign in again.');

        $this->assertGuest();
    }

    public function test_expired_json_request_returns_419_response_without_redirecting(): void
    {
        $user = $this->userWithRole('employee');

        Route::middleware(['web', 'auth'])->post('/__test/expired-json-session', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post('/__test/expired-json-session')
            ->assertStatus(419)
            ->assertHeaderMissing('Location')
            ->assertJson([
                'message' => 'Your session expired. Please sign in again.',
            ]);

        $this->assertAuthenticatedAs($user);
    }
}
