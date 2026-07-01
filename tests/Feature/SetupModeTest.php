<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HolidayEvent;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class SetupModeTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_super_admin_can_view_and_toggle_setup_mode(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('manage.system-settings.index'))
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Setup Mode')
            ->assertSee('Disabled');

        $this->actingAs($superAdmin)
            ->patch(route('manage.system-settings.setup-mode'), [
                'setup_mode_enabled' => '1',
            ])
            ->assertRedirect(route('manage.system-settings.index'))
            ->assertSessionHas('success');

        $setting = SystemSetting::where('key', SystemSetting::SETUP_MODE_ENABLED)->firstOrFail();

        $this->assertTrue($setting->boolean_value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'setup_mode_enabled',
            'auditable_type' => SystemSetting::class,
            'auditable_id' => $setting->id,
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('manage.system-settings.setup-mode'), [
                'setup_mode_enabled' => '0',
            ])
            ->assertRedirect(route('manage.system-settings.index'));

        $this->assertFalse($setting->refresh()->boolean_value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'setup_mode_disabled',
            'auditable_type' => SystemSetting::class,
            'auditable_id' => $setting->id,
        ]);
    }

    public function test_admin_cannot_control_setup_mode(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('manage.system-settings.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('manage.system-settings.setup-mode'), [
                'setup_mode_enabled' => '1',
            ])
            ->assertForbidden();

        $this->assertFalse(SystemSetting::setupModeEnabled());
        $this->assertSame(0, AuditLog::whereIn('action', ['setup_mode_enabled', 'setup_mode_disabled'])->count());
    }

    public function test_setup_mode_redirects_employees_and_hods_to_notice_page(): void
    {
        SystemSetting::setupMode()->update(['boolean_value' => true]);

        foreach (['employee', 'hod'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertRedirect(route('setup.in-progress'));

            $this->actingAs($user)
                ->get(route('setup.in-progress'))
                ->assertOk()
                ->assertSee('System setup is currently in progress');
        }
    }

    public function test_setup_mode_still_allows_employee_logout(): void
    {
        SystemSetting::setupMode()->update(['boolean_value' => true]);
        $employee = $this->userWithRole('employee');

        $this->actingAs($employee)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_write_actions_continue_while_setup_mode_is_enabled(): void
    {
        SystemSetting::setupMode()->update(['boolean_value' => true]);
        $admin = $this->userWithRole('admin');
        $holiday = HolidayEvent::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('manage.holidays.status', $holiday))
            ->assertRedirect(route('manage.holidays.index'))
            ->assertSessionHas('success');

        $this->assertFalse($holiday->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'holiday_deactivated',
            'auditable_type' => HolidayEvent::class,
            'auditable_id' => $holiday->id,
        ]);
    }

    public function test_admin_and_super_admin_can_update_leave_settings_while_setup_mode_is_enabled(): void
    {
        SystemSetting::setupMode()->update(['boolean_value' => true]);

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->patch(route('manage.leave-settings.update'), [
                    'annual_leave_default_days_uae' => '24.5',
                    'annual_leave_default_days_ph' => '0',
                    'sick_leave_default_days_uae' => '16',
                    'sick_leave_default_days_ph' => '0',
                    'maternity_leave_default_days_uae' => '60',
                    'maternity_leave_default_days_ph' => '0',
                    'parental_leave_default_days_uae' => '5',
                    'parental_leave_default_days_ph' => '0',
                    'bereavement_spouse_leave_days_uae' => '5',
                    'bereavement_immediate_family_leave_days_uae' => '3',
                    'bereavement_compassionate_leave_default_days_ph' => '0',
                    'service_incentive_leave_default_days_ph' => '5',
                ])
                ->assertRedirect(route('manage.leave-settings.index'));
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_setting_updated']);
    }

    public function test_normal_access_resumes_when_setup_mode_is_disabled(): void
    {
        SystemSetting::setupMode()->update(['boolean_value' => false]);
        $employee = $this->userWithRole('employee');

        $this->actingAs($employee)
            ->get(route('guide'))
            ->assertOk()
            ->assertDontSee('System setup is currently in progress');
    }
}
