<?php

namespace Tests\Feature;

use App\Mail\LeavePlanWorkflowMail;
use App\Mail\TimesheetWorkflowMail;
use App\Models\LeavePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class HodExclusionWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_email_excluded_hod_does_not_receive_submission_emails_but_can_still_approve(): void
    {
        Mail::fake();

        [$department, $mutedHod, $activeHod, $employee] = $this->departmentWithTwoHods();
        $mutedHod->hodNotificationExcludedSubmitters()->attach($employee->id);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($mutedHod->email));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($activeHod->email)
            && $mail->headline === 'Timesheet submitted for approval');

        $timesheet = $employee->timesheets()->firstOrFail();
        $this->actingAs($mutedHod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertRedirect();

        $this->assertSame('approved', $timesheet->refresh()->status);
        $this->assertSame($mutedHod->id, $timesheet->approved_by);
    }

    public function test_approval_excluded_hod_does_not_receive_email_and_cannot_approve_or_reject(): void
    {
        Mail::fake();

        [, $excludedHod, $activeHod, $employee] = $this->departmentWithTwoHods();
        $excludedHod->hodApprovalExcludedSubmitters()->attach($employee->id);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $timesheet = $employee->timesheets()->firstOrFail();

        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($excludedHod->email));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($activeHod->email));

        $this->actingAs($excludedHod)
            ->get(route('hod.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('another HOD approver is assigned');

        $this->actingAs($excludedHod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertForbidden();

        $this->actingAs($excludedHod)
            ->post(route('hod.timesheets.reject', $timesheet), ['rejection_comment' => 'Needs correction.'])
            ->assertForbidden();

        $this->actingAs($activeHod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertRedirect();

        $this->assertSame($activeHod->id, $timesheet->refresh()->approved_by);
    }

    public function test_leave_plan_hod_notifications_and_approval_actions_respect_exclusions(): void
    {
        Mail::fake();

        [, $excludedHod, $activeHod, $employee] = $this->departmentWithTwoHods();
        $excludedHod->hodApprovalExcludedSubmitters()->attach($employee->id);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertRedirect();

        $leavePlan = LeavePlan::firstOrFail();

        Mail::assertNotQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($excludedHod->email));
        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($activeHod->email)
            && $mail->headline === 'Leave plan submitted for approval');

        $this->actingAs($excludedHod)
            ->post(route('hod.leave-plans.approve', $leavePlan))
            ->assertForbidden();

        $this->actingAs($activeHod)
            ->post(route('hod.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $leavePlan->status);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_DIRECTOR, $leavePlan->approval_stage);
        $this->assertSame($activeHod->id, $leavePlan->hod_approved_by);
        $this->assertNull($leavePlan->approved_by);
    }

    public function test_visibility_excluded_hod_cannot_see_or_action_employee_records(): void
    {
        Mail::fake();

        [, $hiddenHod, $visibleHod, $employee] = $this->departmentWithTwoHods();
        $hiddenHod->hodVisibilityExcludedSubmitters()->attach($employee->id);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertRedirect();

        $timesheet = $employee->timesheets()->firstOrFail();
        $leavePlan = LeavePlan::firstOrFail();

        $this->actingAs($hiddenHod)
            ->get(route('hod.timesheets.index'))
            ->assertOk()
            ->assertDontSee($employee->name);

        $this->actingAs($hiddenHod)
            ->get(route('hod.leave-plans.index'))
            ->assertOk()
            ->assertDontSee($employee->name);

        $this->actingAs($hiddenHod)
            ->get(route('hod.leave-plans.calendar'))
            ->assertOk()
            ->assertDontSee($employee->name);

        $this->actingAs($hiddenHod)
            ->get(route('hod.leave-entitlements.index'))
            ->assertOk()
            ->assertDontSee($employee->name);

        $this->actingAs($hiddenHod)
            ->get(route('hod.tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertDontSee($employee->name);

        $this->actingAs($hiddenHod)
            ->get(route('hod.timesheets.show', $timesheet))
            ->assertForbidden();

        $this->actingAs($hiddenHod)
            ->get(route('hod.leave-plans.show', $leavePlan))
            ->assertForbidden();

        $this->actingAs($hiddenHod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertForbidden();

        $this->actingAs($hiddenHod)
            ->post(route('hod.leave-plans.approve', $leavePlan))
            ->assertForbidden();

        $this->actingAs($hiddenHod)
            ->post(route('hod.tracker.reminders'), [
                'period_id' => $period->id,
                'employee_id' => $employee->id,
            ])
            ->assertNotFound();

        $this->actingAs($visibleHod)
            ->get(route('hod.timesheets.index'))
            ->assertOk()
            ->assertSee($employee->name);

        $this->actingAs($visibleHod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertRedirect();

        $this->assertSame($visibleHod->id, $timesheet->refresh()->approved_by);
    }

    public function test_hod_can_view_visible_employee_leave_entitlements_with_filters(): void
    {
        [$department, $hod, , $employee] = $this->departmentWithTwoHods();
        $employee->update(['annual_leave_allowance_days' => 12]);
        $secondDepartment = $this->department(['name' => 'Managed Projects']);
        $secondDepartment->hods()->attach($hod);
        $secondEmployee = $this->userWithRole('employee', [
            'department_id' => $secondDepartment->id,
            'name' => 'Second Managed Employee',
        ]);
        $unmanagedDepartment = $this->department();

        $this->actingAs($hod)
            ->get(route('hod.leave-entitlements.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Department Leave Entitlements')
            ->assertSee($employee->name)
            ->assertSee('Annual leave')
            ->assertSee('12 days')
            ->assertSee('Current-year override')
            ->assertSee($secondEmployee->name);

        $this->actingAs($hod)
            ->get(route('hod.leave-entitlements.index', [
                'department_id' => $department->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertDontSee($secondEmployee->name);

        $this->actingAs($hod)
            ->get(route('hod.leave-entitlements.index', ['department_id' => $unmanagedDepartment->id]))
            ->assertForbidden();
    }

    public function test_hod_leave_plan_review_shows_employee_leave_balances(): void
    {
        [$department, $hod, , $employee] = $this->departmentWithTwoHods();
        $employee->update(['annual_leave_allowance_days' => 12]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.show', $leavePlan))
            ->assertOk()
            ->assertSee('Employee leave balances')
            ->assertSee('Eligible balances for 2026.')
            ->assertSee('Annual Leave')
            ->assertSee('12 days')
            ->assertSee('Current-year override');
    }

    public function test_super_admin_can_manage_hod_exclusions_and_cannot_leave_zero_eligible_approvers(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        [, $hod, $otherHod, $employee] = $this->departmentWithTwoHods();

        $this->actingAs($superAdmin)
            ->get(route('manage.users.edit', $hod))
            ->assertOk()
            ->assertSee('HOD notification and approval exceptions')
            ->assertSee('Do not show this employee to this HOD')
            ->assertSee($employee->name);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $hod), $this->userPayload($hod, [
                'hod_notification_exclusion_ids' => [$employee->id],
                'hod_approval_exclusion_ids' => [$employee->id],
                'hod_visibility_exclusion_ids' => [$employee->id],
            ]))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('hod_notification_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('hod_approval_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('hod_visibility_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $employee->id,
        ]);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $otherHod), $this->userPayload($otherHod, [
                'hod_approval_exclusion_ids' => [$employee->id],
            ]))
            ->assertSessionHasErrors('hod_approval_exclusion_ids');

        $this->assertDatabaseMissing('hod_approval_exclusions', [
            'hod_user_id' => $otherHod->id,
            'employee_user_id' => $employee->id,
        ]);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $otherHod), $this->userPayload($otherHod, [
                'hod_visibility_exclusion_ids' => [$employee->id],
            ]))
            ->assertSessionHasErrors('hod_visibility_exclusion_ids');

        $this->assertDatabaseMissing('hod_visibility_exclusions', [
            'hod_user_id' => $otherHod->id,
            'employee_user_id' => $employee->id,
        ]);
    }

    public function test_visibility_selector_disables_employees_without_another_hod_available(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);

        $department->update(['hod_id' => $hod->id]);
        $department->hods()->sync([$hod->id]);

        $this->actingAs($superAdmin)
            ->get(route('manage.users.edit', $hod))
            ->assertOk()
            ->assertSee('Assign another HOD to the department first.')
            ->assertSee($employee->name.' - Employee (assign another HOD first)')
            ->assertSee('disabled', false);
    }

    public function test_exclusion_candidates_require_explicit_hod_approver_assignment(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $profileDepartment = $this->department(['name' => 'Profile Only Department']);
        $primaryDepartment = $this->department(['name' => 'Primary Department']);
        $additionalDepartment = $this->department(['name' => 'Additional Department']);
        $hod = $this->userWithRole('hod', ['department_id' => $profileDepartment->id]);
        $profileEmployee = $this->userWithRole('employee', [
            'name' => 'Profile Only Employee',
            'department_id' => $profileDepartment->id,
        ]);
        $primaryEmployee = $this->userWithRole('employee', [
            'name' => 'Primary Employee',
            'department_id' => $primaryDepartment->id,
        ]);
        $additionalEmployee = $this->userWithRole('employee', [
            'name' => 'Additional Employee',
            'department_id' => $additionalDepartment->id,
        ]);

        $primaryDepartment->update(['hod_id' => $hod->id]);
        $additionalDepartment->hods()->attach($hod->id);

        $this->actingAs($superAdmin)
            ->get(route('manage.users.edit', $hod))
            ->assertOk()
            ->assertDontSee($profileEmployee->name)
            ->assertSee($primaryEmployee->name)
            ->assertSee($additionalEmployee->name);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $hod), $this->userPayload($hod, [
                'hod_notification_exclusion_ids' => [$profileEmployee->id, $primaryEmployee->id, $additionalEmployee->id],
                'hod_approval_exclusion_ids' => [$profileEmployee->id],
            ]))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseMissing('hod_notification_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $profileEmployee->id,
        ]);
        $this->assertDatabaseHas('hod_notification_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $primaryEmployee->id,
        ]);
        $this->assertDatabaseHas('hod_notification_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $additionalEmployee->id,
        ]);
        $this->assertDatabaseMissing('hod_approval_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $profileEmployee->id,
        ]);
    }

    public function test_role_and_department_changes_remove_invalid_exclusions(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        [$department, $hod, $otherHod, $employee] = $this->departmentWithTwoHods();
        $otherDepartment = $this->department(['name' => 'Other Department']);

        $hod->hodNotificationExcludedSubmitters()->attach($employee->id);
        $hod->hodApprovalExcludedSubmitters()->attach($employee->id);
        $hod->hodVisibilityExcludedSubmitters()->attach($employee->id);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $employee), $this->userPayload($employee, [
                'department_id' => $otherDepartment->id,
            ]))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseMissing('hod_notification_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('hod_approval_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('hod_visibility_exclusions', [
            'hod_user_id' => $hod->id,
            'employee_user_id' => $employee->id,
        ]);

        $employee->update(['department_id' => $department->id]);
        $hod->hodNotificationExcludedSubmitters()->attach($employee->id);
        $hod->hodApprovalExcludedSubmitters()->attach($employee->id);
        $hod->hodVisibilityExcludedSubmitters()->attach($employee->id);

        $this->actingAs($superAdmin)
            ->put(route('manage.users.update', $hod), $this->userPayload($hod, [
                'role' => 'admin',
                'employee_code' => null,
                'department_id' => null,
            ]))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseMissing('hod_notification_exclusions', ['hod_user_id' => $hod->id]);
        $this->assertDatabaseMissing('hod_approval_exclusions', ['hod_user_id' => $hod->id]);
        $this->assertDatabaseMissing('hod_visibility_exclusions', ['hod_user_id' => $hod->id]);

        $otherHod->hodNotificationExcludedSubmitters()->attach($employee->id);
        $otherHod->hodApprovalExcludedSubmitters()->attach($employee->id);
        $otherHod->hodVisibilityExcludedSubmitters()->attach($employee->id);

        $this->actingAs($superAdmin)
            ->put(route('manage.departments.update', $department), [
                'name' => $department->name,
                'code' => $department->code,
                'hod_id' => '',
                'hod_ids' => [],
                'is_active' => '1',
            ])
            ->assertRedirect(route('manage.departments.index'));

        $this->assertDatabaseMissing('hod_notification_exclusions', ['hod_user_id' => $otherHod->id]);
        $this->assertDatabaseMissing('hod_approval_exclusions', ['hod_user_id' => $otherHod->id]);
        $this->assertDatabaseMissing('hod_visibility_exclusions', ['hod_user_id' => $otherHod->id]);
    }

    private function departmentWithTwoHods(): array
    {
        $department = $this->department();
        $primaryHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $secondaryHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);

        $department->update(['hod_id' => $primaryHod->id]);
        $department->hods()->sync([$primaryHod->id, $secondaryHod->id]);

        return [$department, $primaryHod, $secondaryHod, $employee];
    }

    private function userPayload(User $user, array $overrides = []): array
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
        ], $overrides);
    }

    private function validLeavePlanPayload(array $overrides = []): array
    {
        return array_merge([
            'attendance_code' => 'L100',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'duration_type' => 'full_day',
            'half_day_period' => null,
            'reason' => 'Family travel.',
            'submit' => '0',
        ], $overrides);
    }
}
