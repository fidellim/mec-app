<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Services\TimesheetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class SuperAdminProjectReviewTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_super_admin_reviews_any_project_with_project_scoped_entries_and_filters(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();
        $period = $this->openPeriod();
        $otherProject = $this->project();

        foreach ([true, false] as $active) {
            $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
            $otherWorker = $this->userWithRole('employee', ['department_id' => $department->id]);
            $project = $this->project(['is_active' => $active, 'project_manager_id' => $this->userWithRole('employee')->id]);
            $sheet = $this->submittedTimesheet($worker, $period, $project);
            $sheet->entries()->first()->update(['description' => 'Visible selected project work']);
            $sheet->entries()->create([
                'project_id' => $otherProject->id, 'department_id' => $department->id,
                'work_date' => $period->start_date, 'day_name' => 'Monday', 'attendance_code' => 'O100',
                'regular_hours' => 2, 'overtime_hours' => 0, 'description' => 'Private other project entry',
            ]);
            $otherSheet = $this->submittedTimesheet($otherWorker, $period, $project, ['status' => Timesheet::STATUS_APPROVED]);
            $otherSheet->entries()->first()->update(['description' => 'Another employee approved work']);

            $this->actingAs($superAdmin)->get(route('projects.utilization', $project))
                ->assertOk()->assertSee('Entry review docket')->assertSee('Visible selected project work')
                ->assertSee('Another employee approved work')->assertDontSee('Private other project entry')
                ->assertSee('Send correction request')->assertSee('entire weekly timesheet');

            $this->get(route('projects.utilization', [
                'project' => $project, 'review_employee_id' => $worker->id,
                'review_status' => 'submitted', 'review_week' => 20, 'review_year' => 2026,
                'date_from' => '2026-05-11', 'date_to' => '2026-05-17',
            ]))->assertOk()->assertSee('Visible selected project work')->assertDontSee('Another employee approved work');
            $this->get(route('projects.utilization', ['project' => $project, 'date_from' => '2026-06-01']))
                ->assertOk()->assertDontSee('Visible selected project work');
            $this->get(route('projects.utilization', ['project' => $project, 'review_status' => 'approved']))
                ->assertOk()->assertSee('Another employee approved work')->assertDontSee('Visible selected project work');
            $this->get(route('projects.utilization', ['project' => $project, 'date_from' => '2026-06-01', 'date_to' => '2026-05-01']))
                ->assertSessionHasErrors('date_to');
            $this->actingAs($this->userWithRole('admin'))->get(route('projects.utilization', $project))
                ->assertOk()->assertDontSee('Entry review docket')->assertDontSee('Send correction request');
            $this->actingAs($this->userWithRole('employee'))->get(route('projects.utilization', $project))->assertForbidden();
        }
    }

    public function test_super_admin_request_notifies_hods_and_acceptance_notifies_employee_and_requester(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $secondHod = $this->userWithRole('hod');
        $inactiveHod = $this->userWithRole('hod', ['is_active' => false]);
        $hiddenHod = $this->userWithRole('hod');
        $unrelatedHod = $this->userWithRole('hod');
        $department->update(['hod_id' => $hod->id]);
        $department->hods()->attach([$hod->id, $secondHod->id, $inactiveHod->id, $hiddenHod->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $hiddenHod->hodVisibilityExcludedSubmitters()->attach($worker);
        $superAdmin = $this->userWithRole('super_admin');
        $project = $this->project(['project_manager_id' => $this->userWithRole('employee')->id]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project);

        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these project hours.',
        ])->assertRedirect()->assertSessionHas('success');
        $request = TimesheetCorrectionRequest::sole();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        $this->assertSame($superAdmin->id, $request->requested_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_correction_requested', 'user_id' => $superAdmin->id, 'auditable_id' => $request->id]);
        foreach ([$hod, $secondHod] as $recipient) {
            Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($recipient->email)
                && $mail->headline === 'Timesheet correction request needs review'
                && $mail->actionUrl === route('hod.timesheets.show', $sheet)
                && $mail->comment === 'Please verify these project hours.');
        }
        Mail::assertQueuedCount(2);
        foreach ([$inactiveHod, $hiddenHod, $unrelatedHod, $worker] as $recipient) {
            Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($recipient->email));
        }
        $this->actingAs($hod)->get(route('hod.timesheets.show', $sheet))->assertOk()->assertSee($request->comment);
        $this->post(route('hod.timesheets.approve', $sheet))->assertStatus(422);
        $this->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$request->id => 'accepted']])->assertRedirect();
        $this->assertSame(Timesheet::STATUS_REJECTED, $sheet->fresh()->status);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($worker->email) && $mail->headline === 'Timesheet rejected');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email)
            && $mail->headline === 'Correction request accepted' && $mail->actionUrl === route('projects.utilization', $project));
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_correction_accepted', 'user_id' => $hod->id, 'auditable_id' => $request->id]);
        $this->actingAs($superAdmin)->get(route('projects.utilization', $project))->assertOk()->assertSee('Accepted');
    }

    public function test_approved_hours_stay_reserved_until_hod_accepts_then_employee_can_resubmit(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $otherWorker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $superAdmin = $this->userWithRole('super_admin');
        $project = $this->project(['start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 8]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project, ['status' => Timesheet::STATUS_APPROVED]);
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these approved hours.',
        ])->assertRedirect();
        $request = TimesheetCorrectionRequest::sole();
        $this->assertSame(Timesheet::STATUS_APPROVED, $sheet->fresh()->status);
        try {
            DB::transaction(fn () => app(TimesheetAllocationService::class)->validateSubmission($otherWorker, $this->validEntries($project)));
            $this->fail('Open correction requests must not release reserved hours.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('allocated hours', implode(' ', $exception->validator->errors()->all()));
        }
        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$request->id => 'accepted']])->assertRedirect();
        $this->assertSame(Timesheet::STATUS_RECALLED, $sheet->fresh()->status);
        $snapshots = DB::transaction(fn () => app(TimesheetAllocationService::class)->validateSubmission($otherWorker, $this->validEntries($project)));
        $this->assertNotEmpty($snapshots);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($worker->email) && $mail->headline === 'Approved timesheet recalled');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email) && $mail->headline === 'Correction request accepted');
        $this->actingAs($worker)->put(route('employee.timesheets.update', $sheet), [
            'timesheet_period_id' => $sheet->timesheet_period_id, 'submit' => '1',
            'entries' => $this->validEntries($project, ['2026-05-11' => ['regular_hours' => 4]]),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        $this->assertEquals(4, $sheet->fresh()->total_hours);
    }

    public function test_super_admin_hod_timesheet_request_follows_admin_notification_and_resolution_rules(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->hods()->attach($hod);
        $admin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $optedOut = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => false]);
        $excluded = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $excluded->adminNotificationExcludedHods()->attach($hod);
        $superAdmin = $this->userWithRole('super_admin', ['receives_hod_timesheet_submission_emails' => true]);
        $sheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please check the HOD project hours.',
        ])->assertRedirect();
        $request = TimesheetCorrectionRequest::sole();
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->actionUrl === route('admin.timesheets.show', $sheet));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email) && $mail->headline === 'Timesheet correction request needs review');
        foreach ([$hod, $optedOut, $excluded] as $recipient) {
            Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($recipient->email));
        }
        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$request->id => 'accepted']])->assertForbidden();
        $this->actingAs($admin)->post(route('timesheet-corrections.resolve', $sheet), [
            'decisions' => [$request->id => 'dismissed'], 'dismissal_comments' => [$request->id => 'These hours are supported by the project records.'],
        ])->assertRedirect();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email) && $mail->headline === 'Correction request dismissed');
    }

    public function test_invalid_or_duplicate_requests_do_not_create_requests_or_send_extra_mail(): void
    {
        Mail::fake();
        $department = $this->department();
        $department->hods()->attach($this->userWithRole('hod'));
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $sheet = $this->submittedTimesheet($worker, $period, $project);
        $otherSheet = $this->submittedTimesheet($this->userWithRole('employee', ['department_id' => $department->id]), $period, $project);
        $entryId = $sheet->entries()->first()->id;
        $otherId = $otherSheet->entries()->first()->id;
        $this->actingAs($this->userWithRole('super_admin'));
        foreach ([
            ['entry_ids' => [], 'comment' => 'Please check.'],
            ['entry_ids' => [$entryId], 'comment' => ''],
            ['entry_ids' => [$entryId, $entryId], 'comment' => 'Please check.'],
            ['entry_ids' => [999999], 'comment' => 'Please check.'],
            ['entry_ids' => [$entryId, $otherId], 'comment' => 'Please check.'],
        ] as $payload) {
            $this->post(route('timesheet-corrections.store'), $payload)->assertSessionHasErrors();
        }
        foreach ([Timesheet::STATUS_DRAFT, Timesheet::STATUS_REJECTED, Timesheet::STATUS_RECALLED, Timesheet::STATUS_VOIDED] as $status) {
            $sheet->update(['status' => $status]);
            $this->post(route('timesheet-corrections.store'), ['entry_ids' => [$entryId], 'comment' => 'Please check.'])->assertSessionHasErrors('entry_ids');
        }
        $this->assertDatabaseCount('timesheet_correction_requests', 0);
        Mail::assertNothingQueued();
        $sheet->update(['status' => Timesheet::STATUS_SUBMITTED]);
        $payload = ['entry_ids' => [$entryId], 'comment' => 'Please verify these hours.'];
        $this->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $this->post(route('timesheet-corrections.store'), $payload)->assertSessionHasErrors('entry_ids');
        $this->assertDatabaseCount('timesheet_correction_requests', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_only_requester_can_withdraw_and_resolved_or_changed_timesheets_reject_stale_actions(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $this->project());
        $superAdmin = $this->userWithRole('super_admin');
        $payload = ['entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these hours.'];
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $request = TimesheetCorrectionRequest::sole();
        $this->actingAs($this->userWithRole('super_admin'))->post(route('timesheet-corrections.withdraw', $request))->assertForbidden();
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.withdraw', $request))->assertRedirect();
        $this->assertSame(TimesheetCorrectionRequest::STATUS_WITHDRAWN, $request->fresh()->status);
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_correction_withdrawn', 'user_id' => $superAdmin->id, 'auditable_id' => $request->id]);
        $this->post(route('timesheet-corrections.withdraw', $request))->assertStatus(422);
        $this->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $newRequest = TimesheetCorrectionRequest::latest('id')->first();
        $sheet->update(['status' => Timesheet::STATUS_RECALLED]);
        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$newRequest->id => 'accepted']])->assertStatus(422);
        $this->assertSame(TimesheetCorrectionRequest::STATUS_SUPERSEDED, $newRequest->fresh()->status);
    }

    public function test_admins_and_unrelated_managers_cannot_raise_or_withdraw_project_requests(): void
    {
        Mail::fake();
        $worker = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $manager = $this->userWithRole('employee');
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $this->project(['project_manager_id' => $manager->id]));
        $payload = ['entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these hours.'];
        foreach (['admin', 'employee', 'hod'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('timesheet-corrections.store'), $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('timesheet_correction_requests', 0);
        Mail::assertNothingQueued();
        $this->actingAs($manager)->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $this->actingAs($this->userWithRole('admin'))->post(route('timesheet-corrections.withdraw', TimesheetCorrectionRequest::sole()))->assertForbidden();
    }
}
