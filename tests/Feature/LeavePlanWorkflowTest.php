<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HolidayEvent;
use App\Models\LeaveEntitlement;
use App\Models\LeavePlan;
use App\Models\LeavePlanApproverSetting;
use App\Models\LeavePlanStatusHistory;
use App\Models\LeaveSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class LeavePlanWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_employee_can_save_and_submit_leave_plan(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-601',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '0']))
            ->assertRedirect();

        $draft = LeavePlan::firstOrFail();
        $this->assertSame(LeavePlan::STATUS_DRAFT, $draft->status);
        $this->assertSame($department->id, $draft->department_id);

        $this->actingAs($employee)
            ->put(route('employee.leave-plans.update', $draft), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertRedirect(route('employee.leave-plans.show', $draft));

        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $draft->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'leave_plan_submitted')->count());
        $this->assertDatabaseHas('leave_plan_status_histories', [
            'leave_plan_id' => $draft->id,
            'actor_id' => $employee->id,
            'action' => 'leave_plan_submitted',
            'old_status' => LeavePlan::STATUS_DRAFT,
            'new_status' => LeavePlan::STATUS_SUBMITTED,
            'new_approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
        ]);
    }

    public function test_employee_can_delete_only_draft_leave_plan(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $draft = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'status' => LeavePlan::STATUS_DRAFT,
        ]);
        $submitted = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);

        $this->actingAs($employee)
            ->delete(route('employee.leave-plans.destroy', $draft))
            ->assertRedirect(route('employee.leave-plans.index'));

        $this->assertModelMissing($draft);

        $this->actingAs($employee)
            ->delete(route('employee.leave-plans.destroy', $submitted))
            ->assertForbidden();

        $this->assertModelExists($submitted);
    }

    public function test_hod_approval_moves_leave_plan_to_director_review_and_hod_can_reject(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);

        $approvedPlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'submitted_at' => now(),
        ]);
        $rejectedPlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'submitted_at' => now(),
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve', $approvedPlan))
            ->assertRedirect();

        $approvedPlan->refresh();
        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $approvedPlan->status);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_DIRECTOR, $approvedPlan->approval_stage);
        $this->assertSame($hod->id, $approvedPlan->hod_approved_by);
        $this->assertNull($approvedPlan->approved_by);
        $this->assertDatabaseHas('leave_plan_status_histories', [
            'leave_plan_id' => $approvedPlan->id,
            'actor_id' => $hod->id,
            'action' => 'leave_plan_stage_approved',
            'old_approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'new_approval_stage' => LeavePlan::APPROVAL_STAGE_DIRECTOR,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.reject', $rejectedPlan), ['rejection_comment' => 'Project coverage needed.'])
            ->assertRedirect();

        $this->assertSame(LeavePlan::STATUS_REJECTED, $rejectedPlan->fresh()->status);
        $this->assertSame('Project coverage needed.', $rejectedPlan->fresh()->rejection_comment);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_HOD, $rejectedPlan->fresh()->rejected_approval_stage);
        $this->assertDatabaseHas('leave_plan_status_histories', [
            'leave_plan_id' => $rejectedPlan->id,
            'actor_id' => $hod->id,
            'action' => 'leave_plan_rejected',
            'old_status' => LeavePlan::STATUS_SUBMITTED,
            'new_status' => LeavePlan::STATUS_REJECTED,
            'comment' => 'Project coverage needed.',
        ]);
    }

    public function test_leave_plan_history_timeline_shows_approval_flow(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['name' => 'Harper HOD']);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $department->hods()->attach($hod);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_DIRECTOR,
            'submitted_at' => now(),
        ]);

        LeavePlanStatusHistory::create([
            'leave_plan_id' => $leavePlan->id,
            'actor_id' => $hod->id,
            'action' => 'leave_plan_stage_approved',
            'old_status' => LeavePlan::STATUS_SUBMITTED,
            'new_status' => LeavePlan::STATUS_SUBMITTED,
            'old_approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'new_approval_stage' => LeavePlan::APPROVAL_STAGE_DIRECTOR,
            'occurred_at' => now(),
        ]);

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.show', $leavePlan))
            ->assertOk()
            ->assertSee('Leave plan history')
            ->assertSee('Show history');

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.history', $leavePlan))
            ->assertOk()
            ->assertSee('Approval Stage Completed')
            ->assertSee('Harper HOD')
            ->assertSee('moved approval from Head of Department to Director');
    }

    public function test_director_and_regional_hr_complete_sequential_leave_plan_approval(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $director = $this->userWithRole('employee');
        $uaeHr = $this->userWithRole('employee');
        $department->hods()->attach($hod);
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-501',
        ]);
        $this->setLeavePlanApprovers($director, $uaeHr, $this->userWithRole('employee'));

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'submitted_at' => now(),
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $this->assertSame(LeavePlan::APPROVAL_STAGE_DIRECTOR, $leavePlan->fresh()->approval_stage);

        $this->actingAs($director)
            ->post(route('assigned.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $leavePlan->status);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_HR, $leavePlan->approval_stage);
        $this->assertSame($director->id, $leavePlan->director_approved_by);

        $this->actingAs($uaeHr)
            ->post(route('assigned.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_APPROVED, $leavePlan->status);
        $this->assertNull($leavePlan->approval_stage);
        $this->assertSame($uaeHr->id, $leavePlan->hr_approved_by);
        $this->assertSame($uaeHr->id, $leavePlan->approved_by);
    }

    public function test_philippines_employee_routes_to_ph_hr_for_final_leave_plan_approval(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $director = $this->userWithRole('employee');
        $uaeHr = $this->userWithRole('employee');
        $phHr = $this->userWithRole('employee');
        $department->hods()->attach($hod);
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-502',
        ]);
        $this->setLeavePlanApprovers($director, $uaeHr, $phHr);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HR,
            'submitted_at' => now(),
            'hod_approved_at' => now(),
            'hod_approved_by' => $hod->id,
            'director_approved_at' => now(),
            'director_approved_by' => $director->id,
        ]);

        $this->actingAs($uaeHr)
            ->post(route('assigned.leave-plans.approve', $leavePlan))
            ->assertForbidden();

        $this->actingAs($phHr)
            ->post(route('assigned.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $this->assertSame($phHr->id, $leavePlan->fresh()->approved_by);
    }

    public function test_missing_director_or_hr_configuration_leaves_plan_pending_at_stage(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $leavePlan->status);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_DIRECTOR, $leavePlan->approval_stage);

        $this->actingAs($this->userWithRole('super_admin'))
            ->post(route('admin.leave-plans.approve', $leavePlan))
            ->assertStatus(422);

        $director = $this->userWithRole('employee');
        $this->setLeavePlanApprovers($director, null, null);

        $this->actingAs($director)
            ->post(route('assigned.leave-plans.approve', $leavePlan))
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::APPROVAL_STAGE_HR, $leavePlan->approval_stage);

        $this->actingAs($this->userWithRole('super_admin'))
            ->post(route('admin.leave-plans.approve', $leavePlan))
            ->assertStatus(422);
    }

    public function test_director_rejection_and_resubmission_restarts_full_approval_chain(): void
    {
        $department = $this->department();
        $director = $this->userWithRole('employee');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $this->setLeavePlanApprovers($director, $this->userWithRole('employee'), $this->userWithRole('employee'));
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_DIRECTOR,
            'submitted_at' => now(),
            'hod_approved_at' => now(),
            'hod_approved_by' => $this->userWithRole('hod')->id,
        ]);

        $this->actingAs($director)
            ->post(route('assigned.leave-plans.reject', $leavePlan), ['rejection_comment' => 'Director review requires different dates.'])
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_REJECTED, $leavePlan->status);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_DIRECTOR, $leavePlan->rejected_approval_stage);

        $this->actingAs($employee)
            ->put(route('employee.leave-plans.update', $leavePlan), $this->validLeavePlanPayload([
                'end_date' => '2026-05-12',
                'submit' => '1',
            ]))
            ->assertRedirect(route('employee.leave-plans.show', $leavePlan));

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $leavePlan->status);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_HOD, $leavePlan->approval_stage);
        $this->assertNull($leavePlan->hod_approved_at);
        $this->assertNull($leavePlan->director_approved_at);
        $this->assertNull($leavePlan->rejected_approval_stage);
    }

    public function test_hod_cannot_approve_own_leave_plan(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->hods()->attach($hod);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $hod->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve', $leavePlan))
            ->assertForbidden();
    }

    public function test_approved_leave_plan_cancellation_requires_hod_review(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $hod->id,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.cancel-request', $leavePlan), [
                'cancellation_reason' => 'Plans changed.',
            ])
            ->assertRedirect();

        $this->assertSame(LeavePlan::STATUS_CANCELLATION_REQUESTED, $leavePlan->fresh()->status);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve-cancellation', $leavePlan))
            ->assertRedirect();

        $this->assertSame(LeavePlan::STATUS_CANCELLED, $leavePlan->fresh()->status);
        $this->assertSame($hod->id, $leavePlan->fresh()->cancelled_by);
        $this->assertDatabaseHas('leave_plan_status_histories', [
            'leave_plan_id' => $leavePlan->id,
            'action' => 'leave_plan_cancellation_requested',
            'comment' => 'Plans changed.',
        ]);
        $this->assertDatabaseHas('leave_plan_status_histories', [
            'leave_plan_id' => $leavePlan->id,
            'action' => 'leave_plan_cancellation_approved',
            'new_status' => LeavePlan::STATUS_CANCELLED,
        ]);
    }

    public function test_approved_leave_plan_can_be_recalled_for_employee_correction(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $hod->id,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.recall-approved', $leavePlan), [
                'recall_reason' => 'Correct the end date.',
            ])
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_RECALLED, $leavePlan->status);
        $this->assertSame($hod->id, $leavePlan->recalled_by);
        $this->assertSame('Correct the end date.', $leavePlan->recall_reason);
        $this->assertTrue($leavePlan->editableBy($employee));

        $this->actingAs($employee)
            ->put(route('employee.leave-plans.update', $leavePlan), $this->validLeavePlanPayload([
                'end_date' => '2026-05-12',
                'submit' => '1',
            ]))
            ->assertRedirect(route('employee.leave-plans.show', $leavePlan));

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_SUBMITTED, $leavePlan->status);
        $this->assertNull($leavePlan->recalled_at);
        $this->assertNull($leavePlan->recalled_by);
        $this->assertNull($leavePlan->recall_reason);
    }

    public function test_super_admin_can_void_approved_leave_plan(): void
    {
        $department = $this->department();
        $superAdmin = $this->userWithRole('super_admin');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.leave-plans.void', $leavePlan), [
                'void_reason' => 'Approved against the wrong employee.',
            ])
            ->assertRedirect();

        $leavePlan->refresh();
        $this->assertSame(LeavePlan::STATUS_VOIDED, $leavePlan->status);
        $this->assertSame($superAdmin->id, $leavePlan->voided_by);
        $this->assertSame('Approved against the wrong employee.', $leavePlan->void_reason);
        $this->assertSame(1, AuditLog::where('action', 'leave_plan_voided')->count());
        $this->assertDatabaseHas('leave_plan_status_histories', [
            'leave_plan_id' => $leavePlan->id,
            'actor_id' => $superAdmin->id,
            'action' => 'leave_plan_voided',
            'comment' => 'Approved against the wrong employee.',
        ]);
    }

    public function test_hod_review_page_embeds_calendar_with_managed_department_active_leave(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $otherDepartment = $this->department(['name' => 'Engineering']);
        $hod = $this->userWithRole('hod', ['name' => 'Olivia HOD']);
        $department->hods()->attach($hod);
        $requester = $this->userWithRole('employee', ['name' => 'Aisha Requester', 'department_id' => $department->id]);
        $visibleEmployee = $this->userWithRole('employee', ['name' => 'Ben Visible', 'department_id' => $department->id]);
        $hiddenEmployee = $this->userWithRole('employee', ['name' => 'Carla Hidden', 'department_id' => $otherDepartment->id]);
        $draftEmployee = $this->userWithRole('employee', ['name' => 'Daniel Draft', 'department_id' => $department->id]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $visibleEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-20',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $visibleEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'Founders Day',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'region' => 'global',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $hiddenEmployee->id,
            'department_id' => $otherDepartment->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $draftEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_DRAFT,
            'start_date' => '2026-05-13',
            'end_date' => '2026-05-13',
        ]);

        $response = $this->actingAs($hod)
            ->get(route('hod.leave-plans.show', $leavePlan));

        $response
            ->assertOk()
            ->assertSee('Leave calendar view')
            ->assertSee('May 2026')
            ->assertSee('This request - Aisha Requester')
            ->assertSee('Ben Visible')
            ->assertSee('Holiday - Founders Day')
            ->assertSee('Clash')
            ->assertDontSee('Carla Hidden')
            ->assertDontSee('Daniel Draft');

        $this->assertSame(1, substr_count($response->getContent(), 'This request - Aisha Requester'));
    }

    public function test_admin_review_calendar_shows_company_wide_active_leave_and_ignores_inactive_statuses(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $otherDepartment = $this->department(['name' => 'Engineering']);
        $admin = $this->userWithRole('admin', ['name' => 'Admin Reviewer']);
        $requester = $this->userWithRole('employee', ['name' => 'Fatima Requester', 'department_id' => $department->id]);
        $companyEmployee = $this->userWithRole('employee', ['name' => 'Grace Company', 'department_id' => $otherDepartment->id]);
        $rejectedEmployee = $this->userWithRole('employee', ['name' => 'Hana Rejected', 'department_id' => $department->id]);
        $cancelledEmployee = $this->userWithRole('employee', ['name' => 'Iris Cancelled', 'department_id' => $otherDepartment->id]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'start_date' => '2026-05-31',
            'end_date' => '2026-06-02',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $companyEmployee->id,
            'department_id' => $otherDepartment->id,
            'status' => LeavePlan::STATUS_CANCELLATION_REQUESTED,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $rejectedEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_REJECTED,
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-03',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $cancelledEmployee->id,
            'department_id' => $otherDepartment->id,
            'status' => LeavePlan::STATUS_CANCELLED,
            'start_date' => '2026-06-04',
            'end_date' => '2026-06-04',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leave-plans.show', $leavePlan))
            ->assertOk()
            ->assertSee('Leave calendar view')
            ->assertSee('May 2026')
            ->assertSee('June 2026')
            ->assertSee('This request - Fatima Requester')
            ->assertSee('Grace Company')
            ->assertSee('cancellation requested')
            ->assertDontSee('Hana Rejected')
            ->assertDontSee('Iris Cancelled');
    }

    public function test_review_calendar_uses_entitlement_counting_for_uae_sick_leave(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $hod = $this->userWithRole('hod');
        $employee = $this->userWithRole('employee', [
            'name' => 'Aisha Sick',
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-802',
        ]);
        $department->hods()->attach($hod);

        HolidayEvent::factory()->create([
            'name' => 'Sick Holiday',
            'start_date' => '2026-06-08',
            'end_date' => '2026-06-08',
            'region' => 'uae',
        ]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HOD,
            'attendance_code' => 'L110',
            'start_date' => '2026-06-06',
            'end_date' => '2026-06-08',
        ]);

        $response = $this->actingAs($hod)
            ->get(route('hod.leave-plans.show', $leavePlan));

        $response
            ->assertOk()
            ->assertSee('2 calendar days')
            ->assertSee('Holiday - Sick Holiday');

        $this->assertSame(2, substr_count($response->getContent(), 'This request - Aisha Sick'));
    }

    public function test_employee_leave_plan_form_shows_same_department_active_availability(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $otherDepartment = $this->department(['name' => 'Engineering']);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $submittedEmployee = $this->userWithRole('employee', ['name' => 'Ben Submitted', 'department_id' => $department->id]);
        $approvedEmployee = $this->userWithRole('employee', ['name' => 'Grace Approved', 'department_id' => $department->id]);
        $cancellingEmployee = $this->userWithRole('employee', ['name' => 'Nora Cancelling', 'department_id' => $department->id]);
        $otherEmployee = $this->userWithRole('employee', ['name' => 'Carla Hidden', 'department_id' => $otherDepartment->id]);
        $draftEmployee = $this->userWithRole('employee', ['name' => 'Daniel Draft', 'department_id' => $department->id]);
        $rejectedEmployee = $this->userWithRole('employee', ['name' => 'Hana Rejected', 'department_id' => $department->id]);
        $cancelledEmployee = $this->userWithRole('employee', ['name' => 'Iris Cancelled', 'department_id' => $department->id]);
        $voidedEmployee = $this->userWithRole('employee', ['name' => 'Vera Voided', 'department_id' => $department->id]);

        foreach ([
            [$submittedEmployee, LeavePlan::STATUS_SUBMITTED],
            [$approvedEmployee, LeavePlan::STATUS_APPROVED],
            [$cancellingEmployee, LeavePlan::STATUS_CANCELLATION_REQUESTED],
            [$otherEmployee, LeavePlan::STATUS_APPROVED],
            [$draftEmployee, LeavePlan::STATUS_DRAFT],
            [$rejectedEmployee, LeavePlan::STATUS_REJECTED],
            [$cancelledEmployee, LeavePlan::STATUS_CANCELLED],
            [$voidedEmployee, LeavePlan::STATUS_VOIDED],
        ] as [$user, $status]) {
            LeavePlan::factory()->create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'status' => $status,
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-11',
            ]);
        }
        HolidayEvent::factory()->create([
            'name' => 'UAE Founders Day',
            'region' => HolidayEvent::REGION_UAE,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'PH Independence Day',
            'region' => HolidayEvent::REGION_PH,
            'start_date' => '2026-05-13',
            'end_date' => '2026-05-13',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'Inactive Holiday',
            'region' => HolidayEvent::REGION_UAE,
            'start_date' => '2026-05-14',
            'end_date' => '2026-05-14',
            'is_active' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.create', ['month' => '2026-05']))
            ->assertOk()
            ->assertSee('Department leave availability')
            ->assertSee('Holiday - United Arab Emirates - UAE Founders Day')
            ->assertDontSee('PH Independence Day')
            ->assertDontSee('Inactive Holiday')
            ->assertSee('Ben Submitted')
            ->assertSee('Grace Approved')
            ->assertSee('Nora Cancelling')
            ->assertSee('cancellation requested')
            ->assertDontSee('Carla Hidden')
            ->assertDontSee('Daniel Draft')
            ->assertDontSee('Hana Rejected')
            ->assertDontSee('Iris Cancelled')
            ->assertDontSee('Vera Voided');
    }

    public function test_employee_calendar_shows_same_department_applied_leave_only(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $otherDepartment = $this->department(['name' => 'Engineering']);
        $employee = $this->userWithRole('employee', [
            'name' => 'Aisha Viewer',
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-602',
        ]);
        $submittedEmployee = $this->userWithRole('employee', ['name' => 'Ben Submitted', 'department_id' => $department->id]);
        $approvedEmployee = $this->userWithRole('employee', ['name' => 'Grace Approved', 'department_id' => $department->id]);
        $cancellingEmployee = $this->userWithRole('employee', ['name' => 'Nora Cancelling', 'department_id' => $department->id]);
        $otherEmployee = $this->userWithRole('employee', ['name' => 'Carla Hidden', 'department_id' => $otherDepartment->id]);
        $draftEmployee = $this->userWithRole('employee', ['name' => 'Daniel Draft', 'department_id' => $department->id]);
        $rejectedEmployee = $this->userWithRole('employee', ['name' => 'Hana Rejected', 'department_id' => $department->id]);
        $recalledEmployee = $this->userWithRole('employee', ['name' => 'Rami Recalled', 'department_id' => $department->id]);
        $cancelledEmployee = $this->userWithRole('employee', ['name' => 'Iris Cancelled', 'department_id' => $department->id]);
        $voidedEmployee = $this->userWithRole('employee', ['name' => 'Vera Voided', 'department_id' => $department->id]);

        foreach ([
            [$employee, LeavePlan::STATUS_APPROVED],
            [$submittedEmployee, LeavePlan::STATUS_SUBMITTED],
            [$approvedEmployee, LeavePlan::STATUS_APPROVED],
            [$cancellingEmployee, LeavePlan::STATUS_CANCELLATION_REQUESTED],
            [$otherEmployee, LeavePlan::STATUS_APPROVED],
            [$draftEmployee, LeavePlan::STATUS_DRAFT],
            [$rejectedEmployee, LeavePlan::STATUS_REJECTED],
            [$recalledEmployee, LeavePlan::STATUS_RECALLED],
            [$cancelledEmployee, LeavePlan::STATUS_CANCELLED],
            [$voidedEmployee, LeavePlan::STATUS_VOIDED],
        ] as [$user, $status]) {
            LeavePlan::factory()->create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'status' => $status,
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-11',
            ]);
        }
        HolidayEvent::factory()->create([
            'name' => 'Global Company Day',
            'region' => HolidayEvent::REGION_GLOBAL,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'Philippines Holiday',
            'region' => HolidayEvent::REGION_PH,
            'start_date' => '2026-05-13',
            'end_date' => '2026-05-13',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'UAE Hidden Holiday',
            'region' => HolidayEvent::REGION_UAE,
            'start_date' => '2026-05-14',
            'end_date' => '2026-05-14',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.calendar', ['month' => '2026-05']))
            ->assertOk()
            ->assertSee('Department Leave Calendar')
            ->assertSee('Holiday - Global - Global Company Day')
            ->assertSee('Holiday - Philippines - Philippines Holiday')
            ->assertDontSee('UAE Hidden Holiday')
            ->assertSee('Aisha Viewer')
            ->assertSee('Ben Submitted')
            ->assertSee('Grace Approved')
            ->assertSee('Nora Cancelling')
            ->assertSee('cancellation requested')
            ->assertDontSee('Carla Hidden')
            ->assertDontSee('Daniel Draft')
            ->assertDontSee('Hana Rejected')
            ->assertDontSee('Rami Recalled')
            ->assertDontSee('Iris Cancelled')
            ->assertDontSee('Vera Voided');
    }

    public function test_employee_calendar_ignores_inactive_status_filter_and_does_not_link_coworker_entries(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $visibleEmployee = $this->userWithRole('employee', ['name' => 'Same Team Visible', 'department_id' => $department->id]);
        $rejectedEmployee = $this->userWithRole('employee', ['name' => 'Same Team Rejected', 'department_id' => $department->id]);

        $visiblePlan = LeavePlan::factory()->create([
            'user_id' => $visibleEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $rejectedEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_REJECTED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.calendar', ['month' => '2026-05', 'status' => LeavePlan::STATUS_REJECTED]))
            ->assertOk()
            ->assertSee('Same Team Visible')
            ->assertDontSee('Same Team Rejected')
            ->assertDontSee(route('employee.leave-plans.show', $visiblePlan), false);
    }

    public function test_admin_leave_calendar_shows_all_active_company_holidays(): void
    {
        $admin = $this->userWithRole('admin');

        HolidayEvent::factory()->create([
            'name' => 'Global Review Holiday',
            'region' => HolidayEvent::REGION_GLOBAL,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'UAE Review Holiday',
            'region' => HolidayEvent::REGION_UAE,
            'start_date' => '2026-05-13',
            'end_date' => '2026-05-13',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'PH Review Holiday',
            'region' => HolidayEvent::REGION_PH,
            'start_date' => '2026-05-14',
            'end_date' => '2026-05-14',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'Inactive Review Holiday',
            'region' => HolidayEvent::REGION_GLOBAL,
            'start_date' => '2026-05-15',
            'end_date' => '2026-05-15',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leave-plans.calendar', ['month' => '2026-05']))
            ->assertOk()
            ->assertSee('All Leave Calendar')
            ->assertSee('Holiday - Global - Global Review Holiday')
            ->assertSee('Holiday - United Arab Emirates - UAE Review Holiday')
            ->assertSee('Holiday - Philippines - PH Review Holiday')
            ->assertDontSee('Inactive Review Holiday');
    }

    public function test_employee_leave_plan_edit_availability_excludes_current_plan(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['name' => 'Current Editor', 'department_id' => $department->id]);
        $visibleEmployee = $this->userWithRole('employee', ['name' => 'Same Team Visible', 'department_id' => $department->id]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_DRAFT,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $visibleEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);

        $response = $this->actingAs($employee)
            ->get(route('employee.leave-plans.edit', ['leavePlan' => $leavePlan, 'month' => '2026-05']));

        $response
            ->assertOk()
            ->assertSee('Department leave availability')
            ->assertSee('Same Team Visible')
            ->assertDontSee('Current Editor - L100');
    }

    public function test_employee_leave_plan_form_calendar_fragment_renders_without_full_form(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $visibleEmployee = $this->userWithRole('employee', ['name' => 'Fragment Visible', 'department_id' => $department->id]);

        LeavePlan::factory()->create([
            'user_id' => $visibleEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.create', [
                'month' => '2026-05',
                'calendar_fragment' => 'availability',
            ]))
            ->assertOk()
            ->assertSee('Department leave availability')
            ->assertSee('Fragment Visible')
            ->assertSee('data-leave-plan-availability-calendar', false)
            ->assertDontSee('Leave details')
            ->assertDontSee('name="calendar_fragment"', false)
            ->assertDontSee('calendar_fragment=availability', false);
    }

    public function test_employee_leave_plan_edit_calendar_fragment_excludes_current_plan(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['name' => 'Current Fragment Editor', 'department_id' => $department->id]);
        $visibleEmployee = $this->userWithRole('employee', ['name' => 'Other Fragment Visible', 'department_id' => $department->id]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_DRAFT,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $visibleEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.edit', [
                'leavePlan' => $leavePlan,
                'month' => '2026-05',
                'calendar_fragment' => 'availability',
            ]))
            ->assertOk()
            ->assertSee('Department leave availability')
            ->assertSee('Other Fragment Visible')
            ->assertDontSee('Current Fragment Editor - L100')
            ->assertDontSee('Leave details');
    }

    public function test_annual_leave_limit_blocks_submit_but_allows_draft(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 2]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-13',
                'end_date' => '2026-05-13',
                'submit' => '0',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('leave_plans', [
            'user_id' => $employee->id,
            'status' => LeavePlan::STATUS_DRAFT,
            'start_date' => '2026-05-13 00:00:00',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-14',
                'end_date' => '2026-05-14',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_annual_leave_allowance_refreshes_by_year_without_carryover(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 5]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-12-21',
            'end_date' => '2026-12-22',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2027-01-04',
                'end_date' => '2027-01-08',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2027-01-11',
                'end_date' => '2027-01-11',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_cross_year_annual_leave_consumes_each_year_separately(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 2]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-12-31',
                'end_date' => '2027-01-01',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-12-30',
                'end_date' => '2026-12-30',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-12-29',
                'end_date' => '2026-12-29',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_user_annual_leave_override_replaces_company_default(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 1]);

        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'annual_leave_allowance_days' => 3,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-13',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-14',
                'end_date' => '2026-05-14',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_current_year_annual_override_does_not_carry_to_new_year(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 1]);

        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'annual_leave_allowance_days' => 3,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-13',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('leave_entitlements', [
            'user_id' => $employee->id,
            'year' => 2026,
            'attendance_code' => 'L100',
            'source' => LeaveEntitlement::SOURCE_USER_OVERRIDE,
            'claimable_allowance_days' => '3.00',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2027-05-10',
                'end_date' => '2027-05-11',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');

        $this->assertDatabaseHas('leave_entitlements', [
            'user_id' => $employee->id,
            'year' => 2027,
            'attendance_code' => 'L100',
            'source' => LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
            'claimable_allowance_days' => '1.00',
        ]);
    }

    public function test_inactive_annual_statuses_do_not_consume_entitlement_and_other_leave_codes_remain_unlimited(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 1]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);

        foreach ([LeavePlan::STATUS_DRAFT, LeavePlan::STATUS_REJECTED, LeavePlan::STATUS_CANCELLED, LeavePlan::STATUS_RECALLED, LeavePlan::STATUS_VOIDED] as $status) {
            LeavePlan::factory()->create([
                'user_id' => $employee->id,
                'department_id' => $employee->department_id,
                'attendance_code' => 'L100',
                'status' => $status,
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-11',
            ]);
        }

        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'attendance_code' => 'L120',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-13',
                'end_date' => '2026-05-13',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L120',
                'start_date' => '2026-05-14',
                'end_date' => '2026-05-18',
                'submit' => '1',
            ]))
            ->assertRedirect();
    }

    public function test_regional_annual_and_sick_leave_defaults_are_enforced(): void
    {
        $department = $this->department();
        $uaeEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-701',
        ]);
        $phEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-702',
        ]);

        LeavePlan::factory()->create([
            'user_id' => $uaeEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-30',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $phEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-09',
        ]);

        $this->actingAs($uaeEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-02-02',
                'end_date' => '2026-02-02',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($phEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-02-02',
                'end_date' => '2026-02-02',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');

        LeavePlan::factory()->create([
            'user_id' => $uaeEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L110',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-20',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $phEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L110',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
        ]);

        $this->actingAs($uaeEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-03-23',
                'end_date' => '2026-03-23',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($phEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-03-23',
                'end_date' => '2026-03-23',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_sick_leave_limit_blocks_submit_but_allows_draft(): void
    {
        LeaveSetting::where('key', LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 2]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'attendance_code' => 'L110',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-05-13',
                'end_date' => '2026-05-13',
                'submit' => '0',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('leave_plans', [
            'user_id' => $employee->id,
            'attendance_code' => 'L110',
            'status' => LeavePlan::STATUS_DRAFT,
            'start_date' => '2026-05-13 00:00:00',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-05-14',
                'end_date' => '2026-05-14',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_cross_year_sick_leave_consumes_each_year_separately(): void
    {
        LeaveSetting::where('key', LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 2]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-12-31',
                'end_date' => '2027-01-01',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-12-30',
                'end_date' => '2026-12-30',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-12-29',
                'end_date' => '2026-12-29',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_service_incentive_leave_is_philippines_only_and_limited(): void
    {
        $department = $this->department();
        $uaeEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-701',
        ]);
        $phEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-702',
        ]);

        $this->actingAs($uaeEmployee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertDontSee('L190 - Service Incentive Leave');

        $this->actingAs($uaeEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L190',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');

        $this->actingAs($phEmployee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertSee('L190 - Service Incentive Leave')
            ->assertSee('Service incentive leave')
            ->assertSee('5 days');

        $this->actingAs($phEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L190',
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-15',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($phEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L190',
                'start_date' => '2026-05-18',
                'end_date' => '2026-05-18',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_uae_sick_and_maternity_balances_show_full_pay_allowance_but_validate_total_limit(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'employee_code' => 'MEC-HR-2026-703',
            'gender' => 'female',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertSee('Full-pay allowance')
            ->assertSee('Full-pay remaining')
            ->assertSee('15 days')
            ->assertSee('45 days')
            ->assertDontSee('90 days')
            ->assertDontSee('60 days');

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-16',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L160',
                'start_date' => '2026-05-01',
                'end_date' => '2026-06-15',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L110',
                'start_date' => '2026-07-01',
                'end_date' => '2026-09-13',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L160',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-16',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');
    }

    public function test_parental_label_and_supporting_document_guidance_show_on_leave_form(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'marital_status' => 'married',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertSee('L170 - Parental Leave')
            ->assertSee('L180 - Bereavement / Compassionate Leave')
            ->assertSee('Supporting document needed')
            ->assertSee('Please add a link to your medical certificate in the Reason field.')
            ->assertSee('Please add a link to the birth certificate or hospital birth notification in the Reason field.')
            ->assertDontSee('Paternity Leave');
    }

    public function test_eligible_special_leave_policy_balances_are_visible_and_enforced(): void
    {
        LeaveSetting::where('key', LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 1]);
        LeaveSetting::where('key', LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 1]);
        LeaveSetting::where('key', LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 1]);

        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'gender' => 'female',
            'marital_status' => 'married',
        ]);

        foreach (['L160', 'L170', 'L180'] as $attendanceCode) {
            $this->actingAs($employee)
                ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                    'attendance_code' => $attendanceCode,
                    'start_date' => '2026-05-11',
                    'end_date' => '2026-05-12',
                    'submit' => '1',
                ]))
                ->assertSessionHasErrors('attendance_code');
        }

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertSee('Annual leave')
            ->assertSee('Sick leave')
            ->assertSee('Maternity leave')
            ->assertSee('Parental leave')
            ->assertSee('Bereavement / compassionate leave');
    }

    public function test_leave_entitlement_visibility_obeys_profile_eligibility_rules(): void
    {
        $department = $this->department();
        $femaleSingleEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'gender' => 'female',
            'marital_status' => 'single',
        ]);
        $maleMarriedEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'gender' => 'male',
            'marital_status' => 'married',
        ]);
        $profileIncompleteEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'gender' => null,
            'marital_status' => null,
        ]);

        $this->actingAs($femaleSingleEmployee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertSee('L160 - Maternity Leave')
            ->assertDontSee('L170 - Parental Leave')
            ->assertSee('Bereavement / compassionate leave');

        $this->actingAs($maleMarriedEmployee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertDontSee('L160 - Maternity Leave')
            ->assertSee('L170 - Parental Leave')
            ->assertSee('Bereavement / compassionate leave');

        $this->actingAs($profileIncompleteEmployee)
            ->get(route('employee.leave-plans.create'))
            ->assertOk()
            ->assertDontSee('L160 - Maternity Leave')
            ->assertDontSee('L170 - Parental Leave')
            ->assertSee('Bereavement / compassionate leave');
    }

    public function test_ineligible_maternity_and_parental_leave_cannot_be_saved_or_submitted(): void
    {
        $department = $this->department();
        $maleEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'gender' => 'male',
            'marital_status' => 'married',
        ]);
        $singleEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'gender' => 'female',
            'marital_status' => 'single',
        ]);

        $this->actingAs($maleEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L160',
                'submit' => '0',
            ]))
            ->assertSessionHasErrors('attendance_code');

        $this->actingAs($singleEmployee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'L170',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');

        $this->assertDatabaseMissing('leave_plans', [
            'user_id' => $maleEmployee->id,
            'attendance_code' => 'L160',
        ]);
        $this->assertDatabaseMissing('leave_plans', [
            'user_id' => $singleEmployee->id,
            'attendance_code' => 'L170',
        ]);
    }

    public function test_leave_plan_validation_and_overlap_warning(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'attendance_code' => 'O100',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('attendance_code');

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'duration_type' => 'half_day',
                'half_day_period' => 'morning',
                'end_date' => '2026-05-12',
                'submit' => '1',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-13',
                'end_date' => '2026-05-13',
                'duration_type' => 'half_day',
                'half_day_period' => 'afternoon',
                'submit' => '1',
            ]))
            ->assertRedirect();

        $halfDay = LeavePlan::where('user_id', $employee->id)
            ->where('duration_type', 'half_day')
            ->firstOrFail();
        $this->assertSame('afternoon', $halfDay->half_day_period);
        $this->assertSame('Half day - afternoon (0.5 counted leave day)', $halfDay->leaveLengthLabel());

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-12',
                'end_date' => '2026-05-11',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertSessionHas('warning');

        $this->assertSame(3, LeavePlan::where('user_id', $employee->id)->count());
    }

    public function test_duration_counts_include_counted_leave_days_only(): void
    {
        $leavePlan = LeavePlan::factory()->make([
            'start_date' => '2026-05-08',
            'end_date' => '2026-05-11',
            'duration_type' => 'full_day',
        ]);

        $this->assertSame(2.0, $leavePlan->countedLeaveDayCount());
        $this->assertSame('2 counted leave days', $leavePlan->durationLabel());

        $halfDay = LeavePlan::factory()->make([
            'duration_type' => 'half_day',
            'half_day_period' => 'morning',
        ]);

        $this->assertSame('Half day - morning (0.5 counted leave day)', $halfDay->leaveLengthLabel());
    }

    public function test_leave_plan_count_excludes_applicable_regional_holidays(): void
    {
        HolidayEvent::factory()->create([
            'name' => 'Global Holiday',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'region' => 'global',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'UAE Holiday',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'region' => 'uae',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'PH Holiday',
            'start_date' => '2026-05-13',
            'end_date' => '2026-05-13',
            'region' => 'ph',
        ]);

        $department = $this->department();
        $uaeEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-501',
        ]);
        $phEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-502',
        ]);
        $unknownEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'EMP-503',
        ]);

        $base = [
            'department_id' => $department->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-13',
            'duration_type' => 'full_day',
        ];

        $uaePlan = LeavePlan::factory()->create($base + ['user_id' => $uaeEmployee->id]);
        $phPlan = LeavePlan::factory()->create($base + ['user_id' => $phEmployee->id]);
        $unknownPlan = LeavePlan::factory()->create($base + ['user_id' => $unknownEmployee->id]);

        $this->assertSame(1.0, $uaePlan->countedLeaveDayCount());
        $this->assertSame(1.0, $phPlan->countedLeaveDayCount());
        $this->assertSame(2.0, $unknownPlan->countedLeaveDayCount());
        $this->assertSame('1 counted leave day', $uaePlan->durationLabel());
    }

    public function test_uae_sick_and_maternity_leave_count_calendar_days_and_show_pay_breakdown(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-801',
            'gender' => 'female',
        ]);

        HolidayEvent::factory()->create([
            'name' => 'UAE Holiday',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'region' => 'uae',
        ]);

        $sickPlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L110',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-08',
            'end_date' => '2026-05-12',
        ]);

        $this->assertSame(4.0, $sickPlan->countedLeaveDayCount());
        $this->assertSame('4 calendar days', $sickPlan->durationLabel());

        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L160',
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-15',
        ]);

        $maternityPlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L160',
            'status' => LeavePlan::STATUS_DRAFT,
            'start_date' => '2026-07-16',
            'end_date' => '2026-07-30',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.show', $maternityPlan))
            ->assertOk()
            ->assertSee('15 calendar days')
            ->assertDontSee('Payroll pay breakdown')
            ->assertDontSee('Half pay: 15 days');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.leave-plans.show', $maternityPlan))
            ->assertOk()
            ->assertSee('15 calendar days')
            ->assertSee('Payroll pay breakdown')
            ->assertSee('Half pay: 15 days');
    }

    public function test_leave_plan_count_ignores_inactive_holiday_events(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-504',
        ]);
        HolidayEvent::factory()->create([
            'name' => 'Inactive Holiday',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'region' => 'uae',
            'is_active' => false,
        ]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'duration_type' => 'full_day',
        ]);

        $this->assertSame(1.0, $leavePlan->countedLeaveDayCount());
    }

    public function test_half_day_leave_on_holiday_counts_zero(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-504',
        ]);
        HolidayEvent::factory()->create([
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'region' => 'uae',
        ]);

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'duration_type' => 'half_day',
            'half_day_period' => 'morning',
        ]);

        $this->assertSame(0.0, $leavePlan->countedLeaveDayCount());
        $this->assertSame('Half day - morning (0 counted leave days)', $leavePlan->leaveLengthLabel());

        $sickLeavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L110',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'duration_type' => 'half_day',
            'half_day_period' => 'afternoon',
        ]);

        $this->assertSame(0.0, $sickLeavePlan->countedLeaveDayCount());
        $this->assertSame('Half day - afternoon (0 calendar days)', $sickLeavePlan->leaveLengthLabel());
    }

    public function test_existing_leave_plan_label_recalculates_after_holiday_is_created(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-505',
        ]);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
        ]);

        $this->assertSame('2 counted leave days', $leavePlan->durationLabel());

        HolidayEvent::factory()->create([
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'region' => 'ph',
        ]);

        $this->assertSame('1 counted leave day', $leavePlan->fresh()->durationLabel());
    }

    public function test_overlap_warning_ignores_holiday_only_overlap(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-506',
        ]);
        HolidayEvent::factory()->create([
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'region' => 'uae',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload([
                'start_date' => '2026-05-11',
                'end_date' => '2026-05-11',
                'submit' => '1',
            ]))
            ->assertRedirect()
            ->assertSessionMissing('warning');
    }

    public function test_voided_leave_plan_does_not_trigger_overlap_warning(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'status' => LeavePlan::STATUS_VOIDED,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertRedirect()
            ->assertSessionMissing('warning');
    }

    public function test_employee_without_department_cannot_create_leave_plan(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => null]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.create'))
            ->assertRedirect(route('employee.leave-plans.index'));

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertRedirect(route('employee.leave-plans.index'));

        $this->assertSame(0, LeavePlan::count());
    }

    public function test_access_is_limited_to_owner_and_managed_departments(): void
    {
        $department = $this->department();
        $otherDepartment = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $otherEmployee = $this->userWithRole('employee', ['department_id' => $otherDepartment->id]);
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $otherEmployee->id,
            'department_id' => $otherDepartment->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.show', $leavePlan))
            ->assertForbidden();

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.show', $leavePlan))
            ->assertForbidden();
    }

    public function test_approved_leave_plans_appear_on_timesheet_form_as_warning(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'attendance_code' => 'L100',
            'start_date' => $period->start_date,
            'end_date' => $period->start_date,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Approved leave planned for this week')
            ->assertSee('L100 - Annual Leave')
            ->assertSee('1 counted leave day')
            ->assertDontSee('calendar day');
    }

    public function test_admin_can_filter_all_leave_plans_by_attendance_code_and_multiple_users(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $firstEmployee = $this->userWithRole('employee', ['name' => 'Ava Leave', 'department_id' => $department->id]);
        $secondEmployee = $this->userWithRole('employee', ['name' => 'Ben Leave', 'department_id' => $department->id]);
        $otherEmployee = $this->userWithRole('employee', ['name' => 'Cara Leave', 'department_id' => $department->id]);

        LeavePlan::factory()->create([
            'user_id' => $firstEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);
        LeavePlan::factory()->create([
            'user_id' => $secondEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);
        LeavePlan::factory()->create([
            'user_id' => $otherEmployee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L110',
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leave-plans.index', [
                'attendance_code' => 'L100',
                'employee_ids' => [$firstEmployee->id, $secondEmployee->id],
            ]))
            ->assertOk()
            ->assertSee('Ava Leave')
            ->assertSee('Ben Leave')
            ->assertDontSee('Cara Leave')
            ->assertSee('L100 - Annual Leave');
    }

    public function test_all_leave_plans_filters_preserve_query_strings_and_selected_users(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee', [
            'name' => 'Query String Employee',
            'department_id' => $department->id,
            'employee_code' => 'MEC-QS-001',
        ]);

        foreach (range(1, 16) as $day) {
            LeavePlan::factory()->create([
                'user_id' => $employee->id,
                'department_id' => $department->id,
                'attendance_code' => 'L100',
                'status' => LeavePlan::STATUS_SUBMITTED,
                'start_date' => '2026-05-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'end_date' => '2026-05-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.leave-plans.index', [
                'department_id' => $department->id,
                'status' => LeavePlan::STATUS_SUBMITTED,
                'attendance_code' => 'L100',
                'employee_ids' => [$employee->id],
            ]))
            ->assertOk()
            ->assertSee('Query String Employee')
            ->assertDontSee('Query String Employee - MEC-QS-001')
            ->assertSee('attendance_code=L100', false)
            ->assertSee('employee_ids%5B0%5D='.$employee->id, false);
    }

    public function test_admin_leave_plan_employee_lookup_is_paginated_browsable_and_minimal(): void
    {
        $admin = $this->userWithRole('admin');

        foreach (range(1, 52) as $index) {
            $this->userWithRole('employee', [
                'name' => 'Lookup Person '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'employee_code' => 'LOOK-'.$index,
            ]);
        }

        $this->userWithRole('employee', ['name' => 'Lookup Inactive', 'is_active' => false]);
        $this->userWithRole('admin', ['name' => 'Lookup Admin']);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.leave-plans.index', [
                'employee_lookup' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(50, 'results')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('page', 1);

        $this->assertArrayHasKey('value', $response->json('results.0'));
        $this->assertArrayHasKey('text', $response->json('results.0'));
        $this->assertArrayNotHasKey('email', $response->json('results.0'));

        $resultLabels = collect($response->json('results'))->pluck('text');
        $this->assertFalse($resultLabels->contains(fn ($label) => str_contains($label, 'Lookup Inactive')));
        $this->assertFalse($resultLabels->contains(fn ($label) => str_contains($label, 'Lookup Admin')));

        $this->actingAs($admin)
            ->getJson(route('admin.leave-plans.index', [
                'employee_lookup' => 1,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('page', 2);
    }

    public function test_admin_leave_plan_employee_lookup_can_search_by_name_or_employee_code(): void
    {
        $admin = $this->userWithRole('admin');
        $this->userWithRole('employee', [
            'name' => 'Searchable Leave Employee',
            'employee_code' => 'SPECIAL-LOOKUP-001',
        ]);
        $this->userWithRole('employee', [
            'name' => 'Other Leave Employee',
            'employee_code' => 'OTHER-LOOKUP-001',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.leave-plans.index', [
                'employee_lookup' => 1,
                'q' => 'SPECIAL',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'Searchable Leave Employee')
            ->assertJsonPath('has_more', false);
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

    private function setLeavePlanApprovers($director = null, $uaeHr = null, $phHr = null): void
    {
        LeavePlanApproverSetting::updateOrCreate(
            ['key' => LeavePlanApproverSetting::DIRECTOR],
            ['user_id' => $director?->id],
        );
        LeavePlanApproverSetting::updateOrCreate(
            ['key' => LeavePlanApproverSetting::HR_UAE],
            ['user_id' => $uaeHr?->id],
        );
        LeavePlanApproverSetting::updateOrCreate(
            ['key' => LeavePlanApproverSetting::HR_PH],
            ['user_id' => $phHr?->id],
        );
    }
}
