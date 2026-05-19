<?php

namespace Tests\Feature;

use App\Mail\MissingTimesheetReminderMail;
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
        Carbon::setTestNow('2026-05-18 09:00:00');

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

        Mail::assertSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email)
            && $mail->period->is($pastPeriod));
        Mail::assertNotSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->period->is($currentPeriod));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminder_sent',
            'auditable_id' => $missingEmployee->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_automatic_command_does_not_send_when_no_past_open_period_exists(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 09:00:00');

        $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);

        $this->artisan('timesheets:send-missing-reminders')
            ->expectsOutput('No eligible open period found for missing timesheet reminders.')
            ->assertSuccessful();

        Mail::assertNothingSent();

        Carbon::setTestNow();
    }
}
