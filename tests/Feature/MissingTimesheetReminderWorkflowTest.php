<?php

namespace Tests\Feature;

use App\Mail\MissingTimesheetReminderMail;
use App\Models\AutomationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class MissingTimesheetReminderWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_automatic_command_sends_reminders_for_latest_past_open_period(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 07:00:00');

        $department = $this->department();
        $missingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $submittedEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $pastPeriod = $this->openPeriod();
        $currentPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $this->submittedTimesheet($submittedEmployee, $pastPeriod, $this->project());

        $this->artisan('timesheets:send-missing-reminders')
            ->expectsOutput('Sent 1 missing timesheet reminder(s) for Week 20, 2026.')
            ->assertSuccessful();

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email)
            && $mail->period->is($pastPeriod));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->period->is($currentPeriod));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminder_sent',
            'auditable_id' => $missingEmployee->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminders_succeeded',
            'auditable_type' => AutomationSetting::class,
        ]);
        $this->assertNotNull(AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->firstOrFail()->last_run_at);

        Carbon::setTestNow();
    }

    public function test_automatic_command_does_not_send_when_automation_is_disabled(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 07:00:00');

        AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->update(['is_enabled' => false]);
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $this->openPeriod();

        $this->artisan('timesheets:send-missing-reminders')
            ->expectsOutput('Missing timesheet reminders automation is disabled.')
            ->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminders_failed',
            'auditable_type' => AutomationSetting::class,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'timesheet_missing_reminder_sent',
            'auditable_id' => $employee->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_automatic_command_can_be_forced_when_automation_is_disabled(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 07:00:00');

        AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->update(['is_enabled' => false]);
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();

        $this->artisan('timesheets:send-missing-reminders', ['--force' => true])
            ->expectsOutput('Sent 1 missing timesheet reminder(s) for Week 20, 2026.')
            ->assertSuccessful();

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->period->is($period));

        Carbon::setTestNow();
    }

    public function test_automatic_command_does_not_send_when_no_past_open_period_exists(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 07:00:00');

        $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);

        $this->artisan('timesheets:send-missing-reminders')
            ->expectsOutput('No eligible open period found for missing timesheet reminders.')
            ->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminders_failed',
            'auditable_type' => AutomationSetting::class,
        ]);

        Carbon::setTestNow();
    }
}
