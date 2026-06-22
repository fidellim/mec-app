<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\AuditLog;
use App\Models\Timesheet;
use App\Models\TimesheetStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class TimesheetRecallHistoryWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_hod_can_recall_approved_employee_timesheet_with_reason_and_notify_employee(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod(['status' => 'closed']);
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $hod->id,
        ]);

        $this->actingAs($hod)
            ->withServerVariables(['REMOTE_ADDR' => '172.16.5.9'])
            ->post(route('hod.timesheets.recall-approved', $timesheet), [
                'recall_reason' => 'Incorrect overtime hours on Wednesday.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $timesheet->refresh();
        $this->assertSame('recalled', $timesheet->status);
        $this->assertNotNull($timesheet->approved_at);
        $this->assertSame($hod->id, $timesheet->approved_by);

        $log = AuditLog::where('action', 'timesheet_approved_recalled')->firstOrFail();
        $this->assertSame($hod->id, $log->user_id);
        $this->assertSame('172.16.5.9', $log->ip_address);
        $this->assertSame('approved', $log->old_values['status']);
        $this->assertSame('recalled', $log->new_values['status']);
        $this->assertSame('Incorrect overtime hours on Wednesday.', $log->new_values['recall_reason']);
        $history = TimesheetStatusHistory::where('action', 'timesheet_approved_recalled')->firstOrFail();
        $this->assertSame($timesheet->id, $history->timesheet_id);
        $this->assertSame($hod->id, $history->actor_id);
        $this->assertSame('approved', $history->old_status);
        $this->assertSame('recalled', $history->new_status);
        $this->assertSame('Incorrect overtime hours on Wednesday.', $history->comment);
        $this->assertSame('172.16.5.9', $history->ip_address);

        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Approved timesheet recalled'
            && $mail->comment === 'Incorrect overtime hours on Wednesday.');
    }

    public function test_employee_can_correct_and_resubmit_recalled_approved_timesheet_even_when_period_is_closed(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod(['status' => 'closed']);
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $hod->id,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.timesheets.recall-approved', $timesheet), [
                'recall_reason' => 'Correct the project allocation.',
            ])
            ->assertRedirect();

        $this->actingAs($employee)
            ->get(route('employee.timesheets.edit', $timesheet))
            ->assertOk();

        $this->actingAs($employee)
            ->put(route('employee.timesheets.update', $timesheet), [
                'timesheet_period_id' => $period->id,
                'submit' => '1',
                'entries' => $this->validEntries($project, [
                    '2026-05-11' => ['regular_hours' => 7, 'overtime_hours' => 1],
                ]),
            ])
            ->assertRedirect(route('employee.timesheets.show', $timesheet));

        $timesheet->refresh();
        $this->assertSame('submitted', $timesheet->status);
        $this->assertNull($timesheet->approved_at);
        $this->assertNull($timesheet->approved_by);
        $this->assertSame('7.00', $timesheet->total_regular_hours);
        $this->assertSame('1.00', $timesheet->total_overtime_hours);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet resubmitted for approval');
    }

    public function test_approved_recall_requires_reason_and_approved_status(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $approved = $this->submittedTimesheet($employee, $period, $project, ['status' => 'approved']);

        $this->actingAs($hod)
            ->from(route('hod.timesheets.show', $approved))
            ->post(route('hod.timesheets.recall-approved', $approved), ['recall_reason' => ''])
            ->assertRedirect(route('hod.timesheets.show', $approved))
            ->assertSessionHasErrors('recall_reason');

        $this->assertSame('approved', $approved->refresh()->status);

        $submitted = $this->submittedTimesheet($employee, $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]), $project);

        $this->actingAs($hod)
            ->post(route('hod.timesheets.recall-approved', $submitted), [
                'recall_reason' => 'Trying to recall a submitted record.',
            ])
            ->assertStatus(422);

        $this->assertSame('submitted', $submitted->refresh()->status);
    }

    public function test_admin_can_recall_hod_timesheet_but_not_employee_timesheet(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $admin = $this->userWithRole('admin');
        $period = $this->openPeriod();
        $project = $this->project();
        $hodTimesheet = $this->submittedTimesheet($hod, $period, $project, ['status' => 'approved']);
        $employeeTimesheet = $this->submittedTimesheet($employee, $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]), $project, ['status' => 'approved']);

        $this->actingAs($admin)
            ->post(route('admin.timesheets.recall-approved', $hodTimesheet), [
                'recall_reason' => 'HOD weekly totals need correction.',
            ])
            ->assertRedirect(route('admin.timesheets.show', $hodTimesheet));

        $this->assertSame('recalled', $hodTimesheet->refresh()->status);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Approved timesheet recalled');

        $this->actingAs($admin)
            ->post(route('admin.timesheets.recall-approved', $employeeTimesheet), [
                'recall_reason' => 'Employee correction.',
            ])
            ->assertForbidden();

        $this->assertSame('approved', $employeeTimesheet->refresh()->status);
    }

    public function test_employee_cannot_directly_recall_approved_timesheet(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), [
            'status' => 'approved',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.timesheets.recall', $timesheet))
            ->assertForbidden();

        $this->assertSame('approved', $timesheet->refresh()->status);
    }

    public function test_timesheet_history_shows_ip_only_to_super_admin(): void
    {
        Mail::fake();

        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), [
            'status' => 'approved',
        ]);

        $this->actingAs($superAdmin)
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
            ->post(route('admin.timesheets.recall-approved', $timesheet), [
                'recall_reason' => 'Approved entry should use another project.',
            ])
            ->assertRedirect(route('admin.timesheets.show', $timesheet));

        AuditLog::query()->delete();

        $this->actingAs($superAdmin)
            ->get(route('admin.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('Timesheet history')
            ->assertSee('data-timesheet-history', false)
            ->assertDontSee('Approved Timesheet Recalled')
            ->assertDontSee('IP 192.0.2.25');

        $this->actingAs($superAdmin)
            ->get(route('admin.timesheets.history', $timesheet))
            ->assertOk()
            ->assertSee('Approved Timesheet Recalled')
            ->assertSee('Approved entry should use another project.')
            ->assertSee('IP 192.0.2.25');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.history', $timesheet))
            ->assertOk()
            ->assertSee('Approved Timesheet Recalled')
            ->assertDontSee('IP 192.0.2.25');
    }

    public function test_history_backfill_skips_audit_logs_for_deleted_timesheets(): void
    {
        $actor = $this->userWithRole('super_admin');

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'timesheet_submitted',
            'auditable_type' => Timesheet::class,
            'auditable_id' => 999999,
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'submitted'],
            'ip_address' => '111.235.89.215',
        ]);

        Schema::dropIfExists('timesheet_status_histories');

        $migration = include database_path('migrations/2026_06_22_000002_create_timesheet_status_histories_table.php');
        $migration->up();

        $this->assertDatabaseCount('timesheet_status_histories', 0);
    }
}
