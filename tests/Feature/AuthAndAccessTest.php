<?php

namespace Tests\Feature;

use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    public function test_login_requests_are_throttled_by_normalized_email_and_ip(): void
    {
        $email = 'throttle-login@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
                ->post(route('login'), [
                    'email' => $attempt % 2 === 0 ? strtoupper($email) : ' '.$email.' ',
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->post(route('login'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_login_page_links_to_password_reset_and_can_toggle_password_visibility(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'))
            ->assertSee('data-password-toggle="password"', false);
    }

    public function test_active_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = $this->userWithRole('employee', ['email' => 'reset-me@example.com']);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('success');

        Notification::assertSentTo($user, QueuedResetPassword::class, fn ($notification) => $notification instanceof ShouldQueue);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_creating_a_new_password_reset_token_atomically_replaces_the_existing_token(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'replace-reset@example.com']);
        $broker = Password::broker();

        $firstToken = $broker->createToken($user);
        $secondToken = $broker->createToken($user);

        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertFalse($broker->tokenExists($user, $firstToken));
        $this->assertTrue($broker->tokenExists($user, $secondToken));
    }

    public function test_missing_or_inactive_password_reset_requests_use_generic_response_without_sending_links(): void
    {
        Notification::fake();

        $inactive = $this->userWithRole('employee', [
            'email' => 'inactive-reset@example.com',
            'is_active' => false,
        ]);

        $this->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $this->post(route('password.email'), [
            'email' => $inactive->email,
        ])->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.com']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $inactive->email]);
    }

    public function test_forgot_password_requests_are_throttled_by_normalized_email_and_ip(): void
    {
        Notification::fake();

        $email = 'throttle-forgot@example.com';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.20'])
                ->post(route('password.email'), [
                    'email' => $attempt % 2 === 0 ? strtoupper($email) : ' '.$email.' ',
                ])
                ->assertSessionHas('success')
                ->assertSessionHasNoErrors();
        }

        $this->from(route('password.request'))
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.20'])
            ->post(route('password.email'), [
                'email' => $email,
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_password_reset_form_requires_matching_confirmation(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'confirm-reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_reset_enforces_password_length_policy(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'policy-reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => str_repeat('a', 65),
            'password_confirmation' => str_repeat('a', 65),
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'valid pass 1!',
            'password_confirmation' => 'valid pass 1!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('valid pass 1!', $user->fresh()->password));
    }

    public function test_active_user_can_reset_password_with_valid_token(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'valid-reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('data-password-toggle="password_confirmation"', false)
            ->assertDontSee('Password must be between 10 and 64 characters.');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('fresh-password', $user->fresh()->password));
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'fresh-password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_expired_password_reset_token_is_rejected_after_one_hour(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'expired-reset@example.com']);
        $token = Password::broker()->createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])->assertSessionHasErrors(['email' => 'We could not reset your password. Please check the reset link and try again.']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_reset_failures_do_not_reveal_account_status(): void
    {
        $inactive = $this->userWithRole('employee', [
            'email' => 'inactive-update@example.com',
            'is_active' => false,
        ]);
        $active = $this->userWithRole('employee', ['email' => 'active-update@example.com']);
        $message = 'We could not reset your password. Please check the reset link and try again.';

        foreach (['missing-update@example.com', $inactive->email, $active->email] as $email) {
            $this->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $email,
                'password' => 'fresh-password',
                'password_confirmation' => 'fresh-password',
            ])->assertSessionHasErrors(['email' => $message]);
        }

        $this->assertTrue(Hash::check('password123', $inactive->fresh()->password));
        $this->assertTrue(Hash::check('password123', $active->fresh()->password));
    }

    public function test_reset_password_requests_are_throttled_by_ip(): void
    {
        $payload = [
            'token' => 'invalid-token',
            'email' => 'throttle-reset@example.com',
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.30'])
                ->post(route('password.update'), $payload)
                ->assertSessionHasErrors('email');
        }

        $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $payload['email']]))
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.30'])
            ->post(route('password.update'), $payload)
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $payload['email']]))
            ->assertSessionHasErrors('email');
    }

    public function test_role_middleware_limits_management_and_admin_pages(): void
    {
        $employee = $this->userWithRole('employee');
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($employee)->get(route('manage.users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.timesheets.index'))->assertOk();
        $this->actingAs($admin)->get(route('manage.users.index'))->assertOk();
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
