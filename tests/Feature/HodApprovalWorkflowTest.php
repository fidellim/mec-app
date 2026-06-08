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
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
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
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
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

    public function test_hod_can_review_and_approve_timesheets_for_managed_departments(): void
    {
        Mail::fake();

        $homeDepartment = $this->department(['name' => 'Home Department']);
        $managedDepartment = $this->department(['name' => 'Managed Department']);
        $unmanagedDepartment = $this->department(['name' => 'Unmanaged Department']);
        $hod = $this->userWithRole('hod', ['department_id' => $homeDepartment->id]);
        $managedDepartment->hods()->attach($hod->id);
        $homeEmployee = $this->userWithRole('employee', ['name' => 'Home Employee', 'department_id' => $homeDepartment->id]);
        $managedEmployee = $this->userWithRole('employee', ['name' => 'Managed Employee', 'department_id' => $managedDepartment->id]);
        $unmanagedEmployee = $this->userWithRole('employee', ['name' => 'Unmanaged Employee', 'department_id' => $unmanagedDepartment->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $homeTimesheet = $this->submittedTimesheet($homeEmployee, $period, $project);
        $managedTimesheet = $this->submittedTimesheet($managedEmployee, $period, $project);
        $unmanagedTimesheet = $this->submittedTimesheet($unmanagedEmployee, $period, $project);

        $this->actingAs($hod)
            ->get(route('hod.timesheets.index'))
            ->assertOk()
            ->assertSee('Department')
            ->assertSee('Home Employee')
            ->assertSee('Managed Employee')
            ->assertSee('Home Department')
            ->assertSee('Managed Department')
            ->assertDontSee('Unmanaged Employee');

        $this->actingAs($hod)
            ->get(route('hod.timesheets.index', ['department_id' => $managedDepartment->id]))
            ->assertOk()
            ->assertSee('Managed Employee')
            ->assertDontSee('Home Employee')
            ->assertDontSee('Unmanaged Employee');

        $this->actingAs($hod)
            ->post(route('hod.timesheets.approve', $managedTimesheet))
            ->assertRedirect();

        $this->assertSame('approved', $managedTimesheet->refresh()->status);
        $this->assertSame('submitted', $homeTimesheet->refresh()->status);
        $this->actingAs($hod)->get(route('hod.timesheets.show', $unmanagedTimesheet))->assertForbidden();
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
            ->get(route('hod.timesheets.index'))
            ->assertOk()
            ->assertDontSee('Reset');

        $this->actingAs($hod)
            ->get(route('hod.timesheets.index', [
                'employee_id' => $employee->id,
                'week_number' => 20,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Filtered Employee')
            ->assertSee('20 / 2026')
            ->assertSee('Reset')
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
            ->get(route('hod.tracker'))
            ->assertOk()
            ->assertDontSee('Reset');

        $this->actingAs($hod)
            ->get(route('hod.tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Reset');

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email)
            && $mail->period->is($period));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($submittedEmployee->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($otherDepartmentEmployee->email));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timesheet_missing_reminder_sent',
            'auditable_type' => \App\Models\User::class,
            'auditable_id' => $missingEmployee->id,
        ]);
    }

    public function test_hod_missing_timesheet_reminders_do_not_target_hods(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $missingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $missingHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $period = $this->openPeriod();

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sent 1 missing timesheet reminder(s).');

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingHod->email));
    }

    public function test_hod_tracker_and_reminders_include_managed_departments(): void
    {
        Mail::fake();

        $homeDepartment = $this->department(['name' => 'Home Department']);
        $managedDepartment = $this->department(['name' => 'Managed Department']);
        $unmanagedDepartment = $this->department(['name' => 'Unmanaged Department']);
        $hod = $this->userWithRole('hod', ['department_id' => $homeDepartment->id]);
        $managedDepartment->hods()->attach($hod->id);
        $homeMissing = $this->userWithRole('employee', ['name' => 'Home Missing', 'department_id' => $homeDepartment->id]);
        $managedMissing = $this->userWithRole('employee', ['name' => 'Managed Missing', 'department_id' => $managedDepartment->id]);
        $unmanagedMissing = $this->userWithRole('employee', ['name' => 'Unmanaged Missing', 'department_id' => $unmanagedDepartment->id]);
        $period = $this->openPeriod();

        $this->actingAs($hod)
            ->get(route('hod.tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Home Missing')
            ->assertSee('Managed Missing')
            ->assertSee('Home Department')
            ->assertSee('Managed Department')
            ->assertDontSee('Unmanaged Missing');

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sent 2 missing timesheet reminder(s).');

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($homeMissing->email));
        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($managedMissing->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($unmanagedMissing->email));
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

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingEmployee->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($otherMissingEmployee->email));
    }

    public function test_hod_cannot_send_same_missing_reminder_during_cooldown(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $missingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();

        $payload = [
            'period_id' => $period->id,
            'employee_id' => $missingEmployee->id,
        ];

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($hod)
            ->get(route('hod.tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Available again in')
            ->assertSee('disabled', false);

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), $payload)
            ->assertRedirect()
            ->assertSessionHas('warning', 'No reminder was sent. The selected employee(s) were already reminded recently.');

        Mail::assertQueuedCount(1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_bulk_missing_reminders_skip_employees_on_cooldown(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $coolingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $availableEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), [
                'period_id' => $period->id,
                'employee_id' => $coolingEmployee->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sent 1 missing timesheet reminder(s).');

        Mail::assertQueuedCount(2);
        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($coolingEmployee->email));
        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($availableEmployee->email));
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_bulk_missing_reminders_warn_when_all_missing_employees_are_on_cooldown(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $this->userWithRole('employee', ['department_id' => $department->id]);
        $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sent 2 missing timesheet reminder(s).');

        $this->actingAs($hod)
            ->get(route('hod.tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('All missing employees are on reminder cooldown.');

        $this->actingAs($hod)
            ->post(route('hod.tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('warning', 'No reminder was sent. The selected employee(s) were already reminded recently.');

        Mail::assertQueuedCount(2);
        $this->assertDatabaseCount('audit_logs', 2);
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
