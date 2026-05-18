<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class EmployeeTimesheetWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_employee_can_save_draft_with_blank_weekend_attendance_codes(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();

        $response = $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ]);

        $response->assertRedirect();
        $timesheet = Timesheet::firstOrFail();
        $weekendEntry = $timesheet->entries()
            ->whereDate('work_date', '2026-05-16')
            ->firstOrFail();

        $this->assertSame('draft', $timesheet->status);
        $this->assertSame('8.00', $timesheet->total_regular_hours);
        $this->assertNull($weekendEntry->attendance_code);
    }

    public function test_employee_can_submit_valid_timesheet_and_it_becomes_locked(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();

        $this->assertSame('submitted', $timesheet->status);
        $this->assertNotNull($timesheet->submitted_at);
        $this->actingAs($employee)->get(route('employee.timesheets.edit', $timesheet))->assertForbidden();
        Mail::assertSent(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet submitted for approval');
    }

    public function test_employee_cannot_create_second_timesheet_for_same_week(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $existing = $this->submittedTimesheet($employee, $period, $project);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create'))
            ->assertRedirect(route('employee.timesheets.show', $existing))
            ->assertSessionHas('warning');

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertRedirect(route('employee.timesheets.show', $existing));
    }

    public function test_submit_requires_at_least_one_hour(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = array_map(fn ($entry) => array_merge($entry, [
            'project_id' => '',
            'regular_hours' => 0,
            'overtime_hours' => 0,
        ]), $this->validEntries($project));

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors('entries');
    }

    public function test_hours_require_project_and_attendance_code_but_remarks_are_optional(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => '',
                'project_id' => '',
                'regular_hours' => 0,
                'overtime_hours' => 2,
                'remarks' => '',
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors([
            'entries.0.project_id',
            'entries.0.attendance_code',
        ]);

        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'O100',
                'project_id' => $project->id,
                'regular_hours' => 0,
                'overtime_hours' => 2,
                'remarks' => '',
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $this->assertSame('2.00', Timesheet::firstOrFail()->total_overtime_hours);
    }

    public function test_work_dates_must_be_inside_the_selected_week(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => ['work_date' => '2026-05-18'],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors('entries.0.work_date');
    }

    public function test_closed_periods_do_not_accept_new_timesheets(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod(['status' => 'closed']);
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertStatus(422);
    }

    public function test_rejected_timesheet_can_be_updated_and_resubmitted(): void
    {
        Mail::fake();

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, [
            'status' => 'rejected',
            'rejection_comment' => 'Please correct Monday.',
            'rejected_at' => now(),
        ]);

        $this->actingAs($employee)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project, [
                '2026-05-11' => ['regular_hours' => 7, 'overtime_hours' => 1],
            ]),
        ])->assertRedirect();

        $timesheet->refresh();
        $this->assertSame('submitted', $timesheet->status);
        $this->assertNull($timesheet->rejection_comment);
        $this->assertSame('7.00', $timesheet->total_regular_hours);
        $this->assertSame('1.00', $timesheet->total_overtime_hours);
        Mail::assertNothingSent();
    }

    public function test_employee_can_recall_own_submitted_timesheet(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project);

        $this->actingAs($employee)
            ->post(route('employee.timesheets.recall', $timesheet))
            ->assertRedirect(route('employee.timesheets.edit', $timesheet));

        $this->assertSame('draft', $timesheet->refresh()->status);
        Mail::assertSent(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet recalled by employee');
    }

    public function test_employee_cannot_view_or_edit_another_employees_timesheet(): void
    {
        $department = $this->department();
        $owner = $this->userWithRole('employee', ['department_id' => $department->id]);
        $other = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($owner, $period, $project);

        $this->actingAs($other)->get(route('employee.timesheets.show', $timesheet))->assertForbidden();
        $this->actingAs($other)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertForbidden();
    }
}
