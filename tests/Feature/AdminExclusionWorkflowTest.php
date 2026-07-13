<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\AuditLog;
use App\Models\Timesheet;
use App\Services\AdminExclusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AdminExclusionWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_super_admin_can_manage_admin_exceptions_and_changes_are_audited(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $hod = $this->userWithRole('hod');
        $inactiveHod = $this->userWithRole('hod', ['is_active' => false]);

        $this->actingAs($superAdmin)
            ->get(route('manage.users.edit', $admin))
            ->assertOk()
            ->assertSee('Admin notification and approval exceptions')
            ->assertSee($hod->name)
            ->assertDontSee($inactiveHod->name);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $admin), $this->userPayload($admin, [
                'admin_notification_exclusion_ids' => [$hod->id],
                'admin_approval_exclusion_ids' => [$hod->id],
            ]))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('admin_notification_exclusions', [
            'admin_user_id' => $admin->id,
            'hod_user_id' => $hod->id,
        ]);
        $this->assertDatabaseHas('admin_approval_exclusions', [
            'admin_user_id' => $admin->id,
            'hod_user_id' => $hod->id,
        ]);

        $audit = AuditLog::where('action', 'user_admin_exclusions_updated')->latest('id')->firstOrFail();
        $this->assertSame([$hod->id], $audit->new_values['notification_excluded_hod_ids']);
        $this->assertSame([$hod->id], $audit->new_values['approval_excluded_hod_ids']);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $admin), $this->userPayload($admin))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseMissing('admin_notification_exclusions', ['admin_user_id' => $admin->id]);
        $this->assertDatabaseMissing('admin_approval_exclusions', ['admin_user_id' => $admin->id]);
    }

    public function test_approval_exception_requires_another_active_reviewer(): void
    {
        $admin = $this->userWithRole('admin');
        $hod = $this->userWithRole('hod');

        try {
            app(AdminExclusionService::class)->syncForAdmin($admin, [], [$hod->id]);
            $this->fail('Expected the last-reviewer validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('admin_approval_exclusion_ids', $exception->errors());
        }

        $this->assertDatabaseMissing('admin_approval_exclusions', [
            'admin_user_id' => $admin->id,
            'hod_user_id' => $hod->id,
        ]);
    }

    public function test_notification_and_approval_exceptions_filter_hod_submission_emails(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $notificationExcludedAdmin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $approvalExcludedAdmin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $receivingAdmin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $superAdmin = $this->userWithRole('super_admin', ['receives_hod_timesheet_submission_emails' => true]);
        $notificationExcludedAdmin->adminNotificationExcludedHods()->attach($hod->id);
        $approvalExcludedAdmin->adminApprovalExcludedHods()->attach($hod->id);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($hod)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($notificationExcludedAdmin->email));
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($approvalExcludedAdmin->email));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($receivingAdmin->email)
            && $mail->headline === 'HOD timesheet submitted for approval');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email)
            && $mail->headline === 'HOD timesheet submitted for approval');
    }

    public function test_approval_excluded_admin_can_view_but_cannot_approve_reject_or_recall_hod_timesheet(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $excludedAdmin = $this->userWithRole('admin');
        $otherAdmin = $this->userWithRole('admin');
        $excludedAdmin->adminApprovalExcludedHods()->attach($hod->id);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());

        $this->actingAs($excludedAdmin)
            ->get(route('admin.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('another Admin or Super Admin reviewer is assigned')
            ->assertDontSee('data-confirm="Approve this timesheet?"', false);

        $this->actingAs($excludedAdmin)
            ->post(route('admin.timesheets.approve', $timesheet))
            ->assertForbidden();
        $this->actingAs($excludedAdmin)
            ->post(route('admin.timesheets.reject', $timesheet), ['rejection_comment' => 'Needs correction.'])
            ->assertForbidden();

        $this->actingAs($otherAdmin)
            ->post(route('admin.timesheets.approve', $timesheet))
            ->assertRedirect();
        $this->assertSame(Timesheet::STATUS_APPROVED, $timesheet->refresh()->status);

        $this->actingAs($excludedAdmin)
            ->get(route('admin.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('assigned to recall this approved HOD timesheet');
        $this->actingAs($excludedAdmin)
            ->post(route('admin.timesheets.recall-approved', $timesheet), ['recall_reason' => 'Please correct this entry.'])
            ->assertForbidden();
    }

    public function test_notification_only_admin_can_approve_and_super_admin_bypasses_exceptions_without_affecting_employee_timesheets(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super_admin');
        $admin->adminNotificationExcludedHods()->attach($hod->id);
        $superAdmin->adminApprovalExcludedHods()->attach($hod->id);
        $period = $this->openPeriod();
        $hodTimesheet = $this->submittedTimesheet($hod, $period, $this->project());

        $this->actingAs($admin)
            ->post(route('admin.timesheets.approve', $hodTimesheet))
            ->assertRedirect();

        $employeeTimesheet = $this->submittedTimesheet(
            $employee,
            $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']),
            $this->project()
        );
        $admin->adminApprovalExcludedHods()->attach($hod->id);

        $this->actingAs($admin)
            ->post(route('admin.timesheets.approve', $employeeTimesheet))
            ->assertRedirect();

        $superAdminTimesheet = $this->submittedTimesheet(
            $hod,
            $this->openPeriod(['week_number' => 22, 'start_date' => '2026-05-25', 'end_date' => '2026-05-31']),
            $this->project()
        );
        $this->actingAs($superAdmin)
            ->post(route('admin.timesheets.approve', $superAdminTimesheet))
            ->assertRedirect();
    }

    public function test_invalid_admin_and_hod_role_or_status_pairs_are_pruned(): void
    {
        $admin = $this->userWithRole('admin');
        $hod = $this->userWithRole('hod');
        $admin->adminNotificationExcludedHods()->attach($hod->id);
        $admin->adminApprovalExcludedHods()->attach($hod->id);

        $hod->update(['is_active' => false]);
        app(AdminExclusionService::class)->pruneInvalidForUser($hod->fresh());

        $this->assertDatabaseMissing('admin_notification_exclusions', ['hod_user_id' => $hod->id]);
        $this->assertDatabaseMissing('admin_approval_exclusions', ['hod_user_id' => $hod->id]);

        $activeHod = $this->userWithRole('hod');
        $admin->update(['is_active' => false]);
        app(AdminExclusionService::class)->syncForAdmin($admin->fresh(), [$activeHod->id], [$activeHod->id]);

        $this->assertDatabaseMissing('admin_notification_exclusions', ['admin_user_id' => $admin->id]);
        $this->assertDatabaseMissing('admin_approval_exclusions', ['admin_user_id' => $admin->id]);
    }

    private function userPayload($user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'initials' => $user->initials,
            'job_title' => $user->job_title,
            'department_id' => $user->department_id,
            'role' => $user->role,
            'is_active' => $user->is_active ? '1' : '0',
            'receives_hod_timesheet_submission_emails' => $user->receives_hod_timesheet_submission_emails ? '1' : '0',
            'hod_notification_exclusion_ids' => [],
            'hod_approval_exclusion_ids' => [],
            'hod_visibility_exclusion_ids' => [],
            'admin_notification_exclusion_ids' => [],
            'admin_approval_exclusion_ids' => [],
        ], $overrides);
    }
}
