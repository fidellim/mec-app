<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HolidayEvent;
use App\Models\LeavePlan;
use App\Models\LeavePlanApproverSetting;
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
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);

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

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.reject', $rejectedPlan), ['rejection_comment' => 'Project coverage needed.'])
            ->assertRedirect();

        $this->assertSame(LeavePlan::STATUS_REJECTED, $rejectedPlan->fresh()->status);
        $this->assertSame('Project coverage needed.', $rejectedPlan->fresh()->rejection_comment);
        $this->assertSame(LeavePlan::APPROVAL_STAGE_HOD, $rejectedPlan->fresh()->rejected_approval_stage);
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
