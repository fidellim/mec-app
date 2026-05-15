<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class HodApprovalWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_hod_can_approve_submitted_timesheet_in_own_department(): void
    {
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

    public function test_hod_cannot_approve_own_timesheet(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());

        $this->actingAs($hod)->post(route('hod.timesheets.approve', $timesheet))->assertForbidden();
    }
}
