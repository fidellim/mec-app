<?php

namespace Tests\Feature;

use App\Models\LeavePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class PerformanceQueryScalingTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_leave_calendar_query_count_does_not_grow_with_visible_plans(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $this->createCalendarPlans($employee->id, $department->id, 2);

        $baseline = $this->queryCountFor(fn () => $this->actingAs($employee)
            ->get(route('employee.leave-plans.calendar', ['month' => '2026-06']))
            ->assertOk());

        $this->createCalendarPlans($employee->id, $department->id, 8, 3);

        $scaled = $this->queryCountFor(fn () => $this->actingAs($employee)
            ->get(route('employee.leave-plans.calendar', ['month' => '2026-06']))
            ->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $scaled);
    }

    public function test_entitlement_page_query_count_is_bounded_by_batching(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');

        foreach (range(1, 2) as $number) {
            $this->userWithRole('employee', [
                'department_id' => $department->id,
                'employee_code' => sprintf('MEC-HR-2026-%03d', $number),
            ]);
        }

        $baseline = $this->queryCountFor(fn () => $this->actingAs($admin)
            ->get(route('admin.leave-entitlements.index', ['year' => 2026]))
            ->assertOk());

        foreach (range(3, 10) as $number) {
            $this->userWithRole('employee', [
                'department_id' => $department->id,
                'employee_code' => sprintf('MEC-HR-2026-%03d', $number),
            ]);
        }

        $scaled = $this->queryCountFor(fn () => $this->actingAs($admin)
            ->get(route('admin.leave-entitlements.index', ['year' => 2026]))
            ->assertOk());

        $this->assertLessThanOrEqual($baseline + 3, $scaled);
    }

    private function createCalendarPlans(int $userId, int $departmentId, int $count, int $startDay = 1): void
    {
        foreach (range($startDay, $startDay + $count - 1) as $day) {
            LeavePlan::factory()->create([
                'user_id' => $userId,
                'department_id' => $departmentId,
                'status' => LeavePlan::STATUS_APPROVED,
                'attendance_code' => 'L100',
                'start_date' => sprintf('2026-06-%02d', $day),
                'end_date' => sprintf('2026-06-%02d', $day),
            ]);
        }
    }

    private function queryCountFor(callable $callback): int
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        return $count;
    }
}
