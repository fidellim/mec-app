<?php

namespace Tests\Feature;

use App\Models\HolidayEvent;
use App\Models\LeavePlan;
use App\Services\AdminApprovedLeaveService;
use App\Services\LeaveCoverageService;
use App\Services\LeaveEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AnnualLeaveCoverageTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    private function employee()
    {
        Mail::fake();

        return $this->userWithRole('employee', ['department_id' => $this->department()->id, 'joining_date' => '2020-01-01']);
    }

    private function plan($user, array $attributes = [])
    {
        return LeavePlan::factory()->create(array_merge([
            'user_id' => $user->id, 'department_id' => $user->department_id,
            'start_date' => '2026-05-12', 'end_date' => '2026-05-12', 'status' => 'approved',
        ], $attributes));
    }

    private function payload(array $attributes = []): array
    {
        return array_merge(['attendance_code' => 'L100', 'start_date' => '2026-05-11', 'end_date' => '2026-05-13',
            'duration_type' => 'full_day', 'submit' => '1'], $attributes);
    }

    public function test_overlap_reserves_only_additional_days_and_preview_explains_it(): void
    {
        $user = $this->employee();
        $this->plan($user);
        $this->actingAs($user)->getJson(route('employee.leave-plans.create', $this->payload(['coverage_preview' => '1'])))
            ->assertOk()->assertJsonPath('coverage.selected', 3)->assertJsonPath('coverage.approved', 1)
            ->assertJsonPath('coverage.pending', 0)->assertJsonPath('coverage.additional', 2);
        $this->post(route('employee.leave-plans.store'), $this->payload())->assertSessionHasNoErrors();
        $balance = app(LeaveEntitlementService::class)->balanceFor($user, 2026);
        $this->assertEquals(3, $balance['used']);
        $this->assertEquals(1, $balance['approved_days']);
        $this->assertEquals(2, $balance['pending_days']);
        $new = LeavePlan::latest('id')->first();
        $this->get(route('employee.leave-plans.show', $new))->assertOk()->assertSee('Annual leave allocation');
    }

    public function test_cancellation_preserves_other_approved_coverage_but_never_grants_approval(): void
    {
        $user = $this->employee();
        $original = $this->plan($user);
        $extension = $this->plan($user, ['start_date' => '2026-05-11', 'end_date' => '2026-05-13', 'status' => 'submitted']);
        $original->update(['status' => 'cancelled']);
        $balance = app(LeaveEntitlementService::class)->balanceFor($user, 2026);
        $this->assertEquals(0, $balance['approved_days']);
        $this->assertEquals(3, $balance['pending_days']);
        $extension->update(['status' => 'approved']);
        $balance = app(LeaveEntitlementService::class)->balanceFor($user, 2026);
        $this->assertEquals(3, $balance['approved_days']);
        $this->assertEquals(0, $balance['pending_days']);
        $original->update(['status' => 'approved']);
        $extension->update(['status' => 'rejected']);
        $this->assertEquals(1, app(LeaveEntitlementService::class)->balanceFor($user, 2026)['used']);
    }

    public function test_pending_overlaps_allowed_but_fully_covered_duplicates_blocked(): void
    {
        $user = $this->employee();
        $this->plan($user, ['status' => 'submitted']);
        $this->actingAs($user)->post(route('employee.leave-plans.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('employee.leave-plans.store'), $this->payload())->assertSessionHasErrors('attendance_code');
        $balance = app(LeaveEntitlementService::class)->balanceFor($user, 2026);
        $this->assertEquals(3, $balance['pending_days']);
        $this->assertEquals(0, $balance['approved_days']);
    }

    public function test_pending_cancellation_blocks_overlapping_new_leave_until_decided(): void
    {
        $user = $this->employee();
        $plan = $this->plan($user, ['status' => 'cancellation_requested']);
        $this->actingAs($user)->post(route('employee.leave-plans.store'), $this->payload())->assertSessionHasErrors('attendance_code');
        $this->assertEquals(1, app(LeaveEntitlementService::class)->balanceFor($user, 2026)['approved_days']);
        $plan->update(['status' => 'cancelled']);
        $this->post(route('employee.leave-plans.store'), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_half_days_and_different_leave_types_are_checked_per_period(): void
    {
        $user = $this->employee();
        $morning = $this->plan($user, ['duration_type' => 'half_day', 'half_day_period' => 'morning']);
        $coverage = app(LeaveCoverageService::class);
        $full = $this->payload(['start_date' => '2026-05-12', 'end_date' => '2026-05-12']);
        $this->assertEquals(0.5, $coverage->preview($user, $full)['additional']);
        $duplicate = array_merge($full, ['duration_type' => 'half_day', 'half_day_period' => 'morning']);
        $this->actingAs($user)->post(route('employee.leave-plans.store'), $duplicate)->assertSessionHasErrors('attendance_code');
        $afternoonSick = array_merge($duplicate, ['attendance_code' => 'L110', 'half_day_period' => 'afternoon']);
        $this->post(route('employee.leave-plans.store'), $afternoonSick)->assertSessionHasNoErrors();
        $this->post(route('employee.leave-plans.store'), $full)->assertSessionHasErrors('attendance_code');
        $this->assertEquals(0.5, app(LeaveEntitlementService::class)->balanceFor($user, 2026)['used']);
    }

    public function test_two_half_days_and_full_day_union_is_one_day(): void
    {
        $user = $this->employee();
        $this->plan($user, ['duration_type' => 'half_day', 'half_day_period' => 'morning']);
        $this->plan($user, ['duration_type' => 'half_day', 'half_day_period' => 'afternoon', 'status' => 'submitted']);
        $this->plan($user, ['status' => 'submitted']);
        $balance = app(LeaveEntitlementService::class)->balanceFor($user, 2026);
        $this->assertEquals(1, $balance['used']);
        $this->assertEquals(0.5, $balance['approved_days']);
        $this->assertEquals(0.5, $balance['pending_days']);
    }

    public function test_year_boundary_and_inactive_requests(): void
    {
        $user = $this->employee();
        $this->plan($user, ['start_date' => '2026-12-31', 'end_date' => '2027-01-04']);
        $this->plan($user, ['start_date' => '2026-12-31', 'end_date' => '2026-12-31']);
        foreach (['draft', 'rejected', 'cancelled', 'recalled', 'voided'] as $status) {
            $this->plan($user, ['status' => $status]);
        }
        $totals = app(LeaveEntitlementService::class)->usedAnnualDaysByYear($user);
        $this->assertEquals(1, $totals[2026]);
        $this->assertEquals(2, $totals[2027]);
    }

    public function test_edit_excludes_itself_and_preview_cannot_access_another_employee_request(): void
    {
        $user = $this->employee();
        $draft = $this->plan($user, ['status' => 'draft']);
        $this->actingAs($user)->put(route('employee.leave-plans.update', $draft), $this->payload())->assertSessionHasNoErrors();
        $other = $this->employee();
        $this->actingAs($other)->getJson(route('employee.leave-plans.edit', ['leavePlan' => $draft, 'coverage_preview' => '1']))->assertForbidden();
    }

    public function test_balance_checks_only_additional_days_and_bulk_summaries_match(): void
    {
        $user = $this->employee();
        $user->update(['annual_leave_allowance_days' => 3]);
        $this->plan($user);
        $this->actingAs($user)->post(route('employee.leave-plans.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('employee.leave-plans.store'), $this->payload(['end_date' => '2026-05-14']))->assertSessionHasErrors('attendance_code');
        $service = app(LeaveEntitlementService::class);
        $bulk = $service->annualBalancesForUsers(collect([$user]), 2026)[$user->id];
        $this->assertEquals(3, $bulk['used']);
        $this->assertEquals(1, $bulk['approved_days']);
        $this->assertEquals(2, $bulk['pending_days']);
        $this->assertEquals(0, $bulk['remaining']);
        $excluded = $service->balanceFor($user, 2026, LeavePlan::where('user_id', $user->id)->where('status', 'approved')->first()->id);
        $this->assertEquals(0, $excluded['approved_days']);
        $this->assertEquals(3, $excluded['pending_days']);
        $visible = app(LeaveEntitlementService::class)->visibleBalancesForUsers(collect([$user]), 2026)[$user->id]['L100'];
        $this->assertEquals($bulk['used'], $visible['used']);
    }

    public function test_holidays_are_excluded_before_overlap_allocation(): void
    {
        $user = $this->employee();
        HolidayEvent::factory()->create(['start_date' => '2026-05-12', 'end_date' => '2026-05-12', 'region' => 'uae']);
        $this->plan($user);
        $preview = app(LeaveCoverageService::class)->preview($user, $this->payload());
        $this->assertEquals(2, $preview['selected']);
        $this->assertEquals(0, $preview['approved']);
        $this->assertEquals(2, $preview['additional']);
    }

    public function test_admin_import_preview_and_creation_follow_annual_overlap_rules(): void
    {
        $user = $this->employee();
        $this->actingAs($this->userWithRole('super_admin'));
        $this->plan($user);
        $service = app(AdminApprovedLeaveService::class);
        $row = array_merge($this->payload(), ['employee_code' => $user->employee_code, 'half_day_period' => '',
            'bereavement_relationship' => '', 'reason' => 'Previously approved', 'approved_at' => '2026-05-01', 'policy_exception_reason' => '']);
        $rows = $service->previewRows([$row, $row]);
        $this->assertTrue($rows[0]['valid'], implode(' ', $rows[0]['errors']));
        $this->assertFalse($rows[1]['valid']);
        $service->createApprovedLeave($rows[0]['attributes'], $user);
        $this->assertEquals(3, app(LeaveEntitlementService::class)->balanceFor($user, 2026)['approved_days']);
    }

    public function test_real_cancellation_workflow_keeps_other_pending_coverage(): void
    {
        $user = $this->employee();
        $original = $this->plan($user);
        $this->plan($user, ['start_date' => '2026-05-11', 'end_date' => '2026-05-13', 'status' => 'submitted']);
        $this->actingAs($user)->post(route('employee.leave-plans.cancel-request', $original), ['cancellation_reason' => 'Replace original leave'])->assertSessionHasNoErrors();
        $this->assertSame('cancellation_requested', $original->fresh()->status);
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin)->post(route('admin.leave-plans.approve-cancellation', $original))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $original->fresh()->status);
        $balance = app(LeaveEntitlementService::class)->balanceFor($user, 2026);
        $this->assertEquals(0, $balance['approved_days']);
        $this->assertEquals(3, $balance['pending_days']);
    }
}
