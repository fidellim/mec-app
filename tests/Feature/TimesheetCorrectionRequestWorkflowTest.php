<?php

namespace Tests\Feature;

use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Mail\TimesheetWorkflowMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class TimesheetCorrectionRequestWorkflowTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_project_manager_can_flag_managed_entries_and_hod_must_resolve_them(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $manager = $this->userWithRole('employee');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $entry = $timesheet->entries()->firstOrFail();

        $this->actingAs($manager)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$entry->id], 'comment' => 'The hours do not match the project record.',
        ])->assertRedirect();

        $request = TimesheetCorrectionRequest::firstOrFail();
        $this->assertSame(TimesheetCorrectionRequest::STATUS_OPEN, $request->status);
        $this->actingAs($hod)->post(route('hod.timesheets.approve', $timesheet))->assertStatus(422);

        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $timesheet), [
            'decisions' => [$request->id => 'accepted'],
        ])->assertRedirect();

        $this->assertSame(Timesheet::STATUS_REJECTED, $timesheet->fresh()->status);
        $this->assertSame(TimesheetCorrectionRequest::STATUS_ACCEPTED, $request->fresh()->status);
        $this->assertStringContainsString('hours do not match', $timesheet->fresh()->rejection_comment);
    }

    public function test_dismissal_requires_reason_and_withdrawal_preserves_request(): void
    {
        Mail::fake();
        $department = $this->department(); $hod = $this->userWithRole('hod'); $department->hods()->attach($hod);
        $manager = $this->userWithRole('employee'); $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $entry = $timesheet->entries()->firstOrFail();
        $this->actingAs($manager)->post(route('timesheet-corrections.store'), ['entry_ids' => [$entry->id], 'comment' => 'Please verify these recorded hours.']);
        $correction = TimesheetCorrectionRequest::firstOrFail();

        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $timesheet), ['decisions' => [$correction->id => 'dismissed']])
            ->assertSessionHasErrors("dismissal_comments.{$correction->id}");
        $this->actingAs($manager)->post(route('timesheet-corrections.withdraw', $correction))->assertRedirect();
        $this->assertSame(TimesheetCorrectionRequest::STATUS_WITHDRAWN, $correction->fresh()->status);
    }

    public function test_hod_timesheet_correction_is_routed_to_admins_and_only_admins_can_resolve_it(): void
    {
        Mail::fake();
        $department = $this->department();
        $departmentHod = $this->userWithRole('hod');
        $department->hods()->attach($departmentHod);
        $timesheetOwner = $this->userWithRole('hod', ['department_id' => $department->id]);
        $manager = $this->userWithRole('employee');
        $admin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $timesheet = $this->submittedTimesheet($timesheetOwner, $this->openPeriod(), $project);
        $entry = $timesheet->entries()->firstOrFail();

        $this->actingAs($manager)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$entry->id], 'comment' => 'Please verify the recorded project hours.',
        ])->assertRedirect();

        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->headline === 'Timesheet correction request needs review');
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($departmentHod->email) && $mail->headline === 'Timesheet correction request needs review');

        $this->actingAs($departmentHod)->get(route('hod.timesheets.show', $timesheet))
            ->assertOk()->assertDontSee('Please verify the recorded project hours.');

        $correction = TimesheetCorrectionRequest::firstOrFail();
        $this->actingAs($departmentHod)->post(route('timesheet-corrections.resolve', $timesheet), ['decisions' => [$correction->id => 'dismissed'], 'dismissal_comments' => [$correction->id => 'No correction needed.']])->assertForbidden();
        $this->actingAs($admin)->post(route('timesheet-corrections.resolve', $timesheet), ['decisions' => [$correction->id => 'dismissed'], 'dismissal_comments' => [$correction->id => 'The recorded hours are supported.']])->assertRedirect();
        $this->assertSame(TimesheetCorrectionRequest::STATUS_DISMISSED, $correction->fresh()->status);
    }
}
