<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Mail\MissingTimesheetReminderMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class HodApprovalWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_hod_can_approve_submitted_timesheet_in_own_department(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());

        $this->actingAs($hod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertRedirect();

        $timesheet->refresh();
        $this->assertSame('approved', $timesheet->status);
        $this->assertSame($hod->id, $timesheet->approved_by);
        $this->assertNotNull($timesheet->approved_at);
        Mail::assertSent(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Timesheet approved');
    }

    public function test_hod_rejection_requires_comment(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());

        $this->actingAs($hod)
            ->from(route('hod.timesheets.show', $timesheet))
            ->post(route('hod.timesheets.reject', $timesheet), ['rejection_comment' => ''])
            ->assertRedirect(route('hod.timesheets.show', $timesheet))
            ->assertSessionHasErrors('rejection_comment');
    }

    public function test_hod_can_reject_with_comment(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());

        $this->actingAs($hod)
            ->post(route('hod.timesheets.reject', $timesheet), ['rejection_comment' => 'Wrong project code.'])
            ->assertRedirect();

        $timesheet->refresh();
        $this->assertSame('rejected', $timesheet->status);
        $this->assertSame('Wrong project code.', $timesheet->rejection_comment);
        $this->assertSame($hod->id, $timesheet->rejected_by);
        Mail::assertSent(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Timesheet rejected'
            && $mail->comment === 'Wrong project code.');
    }

    public function test_hod_cannot_access_or_approve_other_department_timesheets(): void
    {
        $hod = $this->userWithRole('hod', ['department_id' => $this->department()->id]);
        $otherDepartment = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $otherDepartment->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());

        $this->actingAs($hod)->get(route('hod.timesheets.show', $timesheet))->assertForbidden();
        $this->actingAs($hod)->post(route('hod.timesheets.approve', $timesheet))->assertForbidden();
    }

    public function test_hod_can_filter_department_timesheets_by_employee_week_and_year(): void
    {
        $department = $this->department();
        $otherDepartment = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', [
            'name' => 'Filtered Employee',
            'department_id' => $department->id,
        ]);
        $otherEmployee = $this->userWithRole('employee', [
            'name' => 'Other Department Employee',
            'department_id' => $otherDepartment->id,
        ]);
        $week20 = $this->openPeriod();
        $week21 = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $project = $this->project();

        $this->submittedTimesheet($employee, $week20, $project);
        $this->submittedTimesheet($employee, $week21, $project);
        $this->submittedTimesheet($otherEmployee, $week20, $project);

        $this->actingAs($hod)
            ->get(route('hod.timesheets.index', [
                'employee_id' => $employee->id,
                'week_number' => 20,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Filtered Employee')
            ->assertSee('20 / 2026')
            ->assertDontSee('21 / 2026')
            ->assertDontSee('Other Department Employee');
    }

    public function test_hod_can_send_missing_timesheet_reminders_for_selected_period(): void
    {
        Mail::fake();

        $department = $this->department();
        $otherDepartment = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $missingEmployee = $this->userWithRole('employee', [
            'name' => 'Missing Employee',
            'department_id' => $department->id,
        ]);
        $submittedEmployee = $this->userWithRole('employee', [
            'name' => 'Submitted Employee',
            'department_id' => $department->id,
        ]);
        $otherDepartmentEmployee = $this->userWithRole('employee', [
            'name' => 'Other Department Missing',
            'department_id' => $otherDepartment->id,
        ]);
        $period = $this->openPeriod();
        $this->submittedTimesheet($submittedEmployee, $period, $this->project());

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email)
            && $mail->period->is($period));
        Mail::assertNotSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($submittedEmployee->email));
        Mail::assertNotSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($otherDepartmentEmployee->email));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminder_sent',
            'auditable_type' => \App\Models\User::class,
            'auditable_id' => $missingEmployee->id,
        ]);
    }

    public function test_hod_can_send_missing_timesheet_reminder_to_one_employee(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $missingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $otherMissingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), [
                'period_id' => $period->id,
                'employee_id' => $missingEmployee->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email));
        Mail::assertNotSent(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($otherMissingEmployee->email));
    }

    public function test_hod_cannot_approve_own_timesheet(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());

        $this->actingAs($hod)
            ->get(route('hod.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('You cannot approve or reject your own timesheet')
            ->assertDontSee('Approve this timesheet?')
            ->assertDontSee('Rejection comment');

        $this->actingAs($hod)
            ->from(route('hod.timesheets.show', $timesheet))
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertRedirect(route('hod.timesheets.show', $timesheet))
            ->assertSessionHas('warning');
    }

    public function test_hod_cannot_reject_own_timesheet(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());

        $this->actingAs($hod)
            ->from(route('hod.timesheets.show', $timesheet))
            ->post(route('hod.timesheets.reject', $timesheet), ['rejection_comment' => 'Needs correction.'])
            ->assertRedirect(route('hod.timesheets.show', $timesheet))
            ->assertSessionHas('warning');
    }
}
