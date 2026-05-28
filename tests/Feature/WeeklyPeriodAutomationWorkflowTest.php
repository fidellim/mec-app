<?php

namespace Tests\Feature;

use App\Models\AutomationSetting;
use App\Models\TimesheetPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyPeriodAutomationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_current_weekly_period_when_missing(): void
    {
        Carbon::setTestNow('2026-05-25 06:30:00');

        $this->artisan('timesheets:create-weekly-period')
            ->expectsOutput('Created Week 22, 2026: 2026-05-25 to 2026-05-31.')
            ->assertSuccessful();

        $this->assertDatabaseHas('timesheet_periods', [
            'week_number' => 22,
            'year' => 2026,
            'start_date' => '2026-05-25 00:00:00',
            'end_date' => '2026-05-31 00:00:00',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_period_auto_creation_succeeded',
            'auditable_type' => AutomationSetting::class,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_period_auto_created']);
        $this->assertNotNull(AutomationSetting::where('key', AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION)->firstOrFail()->last_run_at);

        Carbon::setTestNow();
    }

    public function test_command_skips_existing_weekly_period_without_duplicate(): void
    {
        Carbon::setTestNow('2026-05-25 06:30:00');

        TimesheetPeriod::create([
            'week_number' => 22,
            'year' => 2026,
            'start_date' => '2026-05-25',
            'end_date' => '2026-05-31',
            'status' => 'closed',
        ]);

        $this->artisan('timesheets:create-weekly-period')
            ->expectsOutput('Week 22, 2026 already exists.')
            ->assertSuccessful();

        $this->assertSame(1, TimesheetPeriod::where('week_number', 22)->where('year', 2026)->count());
        $this->assertSame('closed', TimesheetPeriod::where('week_number', 22)->where('year', 2026)->firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_period_auto_creation_succeeded',
            'auditable_type' => AutomationSetting::class,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_period_auto_create_skipped']);

        Carbon::setTestNow();
    }

    public function test_command_does_not_create_when_automation_is_disabled(): void
    {
        Carbon::setTestNow('2026-05-25 06:30:00');
        AutomationSetting::where('key', AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION)->update(['is_enabled' => false]);

        $this->artisan('timesheets:create-weekly-period')
            ->expectsOutput('Weekly period auto creation automation is disabled.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('timesheet_periods', [
            'week_number' => 22,
            'year' => 2026,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_period_auto_creation_failed',
            'auditable_type' => AutomationSetting::class,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'timesheet_period_auto_created']);

        Carbon::setTestNow();
    }

    public function test_command_can_be_forced_when_automation_is_disabled(): void
    {
        Carbon::setTestNow('2026-05-25 06:30:00');
        AutomationSetting::where('key', AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION)->update(['is_enabled' => false]);

        $this->artisan('timesheets:create-weekly-period', ['--force' => true])
            ->expectsOutput('Created Week 22, 2026: 2026-05-25 to 2026-05-31.')
            ->assertSuccessful();

        $this->assertDatabaseHas('timesheet_periods', [
            'week_number' => 22,
            'year' => 2026,
        ]);

        Carbon::setTestNow();
    }

    public function test_command_uses_iso_week_year_around_new_year(): void
    {
        $this->artisan('timesheets:create-weekly-period', ['--date' => '2024-12-30'])
            ->expectsOutput('Created Week 1, 2025: 2024-12-30 to 2025-01-05.')
            ->assertSuccessful();

        $this->assertDatabaseHas('timesheet_periods', [
            'week_number' => 1,
            'year' => 2025,
            'start_date' => '2024-12-30 00:00:00',
            'end_date' => '2025-01-05 00:00:00',
        ]);
    }
}
