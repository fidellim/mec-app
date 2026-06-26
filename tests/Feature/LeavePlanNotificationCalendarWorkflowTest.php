<?php

namespace Tests\Feature;

use App\Mail\LeavePlanWorkflowMail;
use App\Models\LeavePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class LeavePlanNotificationCalendarWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_leave_plan_submission_notifies_active_hod_approvers_only(): void
    {
        Mail::fake();

        $department = $this->department();
        $primaryHod = $this->userWithRole('hod');
        $coveringHod = $this->userWithRole('hod');
        $inactiveHod = $this->userWithRole('hod', ['is_active' => false]);
        $blankEmailHod = $this->userWithRole('hod', ['email' => '']);
        $employeeApprover = $this->userWithRole('employee');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);

        $department->update(['hod_id' => $primaryHod->id]);
        $department->hods()->attach([$primaryHod->id, $coveringHod->id, $inactiveHod->id, $blankEmailHod->id, $employeeApprover->id]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.store'), $this->validLeavePlanPayload(['submit' => '1']))
            ->assertRedirect();

        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($primaryHod->email)
            && $mail->headline === 'Leave plan submitted for approval');
        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($coveringHod->email)
            && $mail->headline === 'Leave plan submitted for approval');
        Mail::assertNotQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($inactiveHod->email));
        Mail::assertNotQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($blankEmailHod->email));
        Mail::assertNotQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($employeeApprover->email));
        Mail::assertQueuedCount(2);
    }

    public function test_leave_plan_review_actions_notify_employee(): void
    {
        Mail::fake();

        [$department, $hod, $employee] = $this->departmentWithHodAndEmployee();
        $approvedPlan = $this->submittedLeavePlan($employee, $department);
        $rejectedPlan = $this->submittedLeavePlan($employee, $department, [
            'start_date' => '2026-06-11',
            'end_date' => '2026-06-11',
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve', $approvedPlan))
            ->assertRedirect();

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.reject', $rejectedPlan), ['rejection_comment' => 'Coverage is required.'])
            ->assertRedirect();

        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Leave plan approved');
        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Leave plan rejected'
            && $mail->comment === 'Coverage is required.');
        Mail::assertQueuedCount(2);
    }

    public function test_leave_plan_cancellation_notifications_are_sent(): void
    {
        Mail::fake();

        [$department, $hod, $employee] = $this->departmentWithHodAndEmployee();
        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $hod->id,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.leave-plans.cancel-request', $leavePlan), [
                'cancellation_reason' => 'Travel moved.',
            ])
            ->assertRedirect();

        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Leave plan cancellation requested'
            && $mail->comment === 'Travel moved.');

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.reject-cancellation', $leavePlan), [
                'cancellation_rejection_comment' => 'Keep the approved leave record.',
            ])
            ->assertRedirect();

        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Leave plan cancellation rejected'
            && $mail->comment === 'Keep the approved leave record.');

        $leavePlan->refresh()->update([
            'status' => LeavePlan::STATUS_CANCELLATION_REQUESTED,
            'cancellation_reason' => 'Travel cancelled again.',
            'cancellation_rejection_comment' => null,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.leave-plans.approve-cancellation', $leavePlan))
            ->assertRedirect();

        Mail::assertQueued(LeavePlanWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Leave plan cancellation approved');
    }

    public function test_employee_calendar_shows_only_own_leave_plans(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $otherEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);

        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'attendance_code' => 'L100',
            'start_date' => '2026-05-31',
            'end_date' => '2026-06-02',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'attendance_code' => 'L110',
            'duration_type' => 'half_day',
            'half_day_period' => 'morning',
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $otherEmployee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'attendance_code' => 'L120',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.leave-plans.calendar', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('My Leave Calendar')
            ->assertSee('L100')
            ->assertSee('L110')
            ->assertSee('Half day - morning (0.5 calendar days / 0.5 weekdays)')
            ->assertDontSee('>L120<', false)
            ->assertDontSee($otherEmployee->name);
    }

    public function test_hod_calendar_is_limited_to_managed_departments_and_filters_do_not_bypass_scope(): void
    {
        $managedDepartment = $this->department();
        $otherDepartment = $this->department();
        $hod = $this->userWithRole('hod');
        $managedDepartment->hods()->attach($hod);
        $employee = $this->userWithRole('employee', ['department_id' => $managedDepartment->id]);
        $otherEmployee = $this->userWithRole('employee', ['department_id' => $otherDepartment->id]);

        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $managedDepartment->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'attendance_code' => 'L100',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-03',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $otherEmployee->id,
            'department_id' => $otherDepartment->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'attendance_code' => 'L120',
            'start_date' => '2026-06-04',
            'end_date' => '2026-06-04',
        ]);

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.calendar', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('L100')
            ->assertDontSee($otherEmployee->name)
            ->assertDontSee('>L120<', false);

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.calendar', ['month' => '2026-06', 'employee_id' => $otherEmployee->id]))
            ->assertOk()
            ->assertDontSee($otherEmployee->name)
            ->assertDontSee('>L120<', false);

        $this->actingAs($hod)
            ->get(route('hod.leave-plans.calendar', ['month' => '2026-06', 'department_id' => $otherDepartment->id]))
            ->assertForbidden();
    }

    public function test_admin_calendar_can_see_all_leave_plans(): void
    {
        $admin = $this->userWithRole('admin');
        $firstDepartment = $this->department();
        $secondDepartment = $this->department();
        $firstEmployee = $this->userWithRole('employee', ['department_id' => $firstDepartment->id]);
        $secondEmployee = $this->userWithRole('employee', ['department_id' => $secondDepartment->id]);

        LeavePlan::factory()->create([
            'user_id' => $firstEmployee->id,
            'department_id' => $firstDepartment->id,
            'status' => LeavePlan::STATUS_APPROVED,
            'attendance_code' => 'L100',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-03',
        ]);
        LeavePlan::factory()->create([
            'user_id' => $secondEmployee->id,
            'department_id' => $secondDepartment->id,
            'status' => LeavePlan::STATUS_CANCELLATION_REQUESTED,
            'attendance_code' => 'L180',
            'start_date' => '2026-06-05',
            'end_date' => '2026-06-05',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leave-plans.calendar', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee($firstEmployee->name)
            ->assertSee($secondEmployee->name)
            ->assertSee('L100')
            ->assertSee('L180')
            ->assertSee('cancellation requested');
    }

    private function departmentWithHodAndEmployee(): array
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $department->hods()->attach($hod);

        return [$department, $hod, $employee];
    }

    private function submittedLeavePlan($employee, $department, array $attributes = []): LeavePlan
    {
        return LeavePlan::factory()->create(array_merge([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'status' => LeavePlan::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
        ], $attributes));
    }

    private function validLeavePlanPayload(array $overrides = []): array
    {
        return array_merge([
            'attendance_code' => 'L100',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'duration_type' => 'full_day',
            'half_day_period' => null,
            'reason' => 'Family travel.',
            'submit' => '0',
        ], $overrides);
    }
}
