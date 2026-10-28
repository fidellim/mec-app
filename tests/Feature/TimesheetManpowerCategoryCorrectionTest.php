<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TimesheetCorrectionRequest;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class TimesheetManpowerCategoryCorrectionTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    private function scenario(): array
    {
        $department = $this->department(['name' => 'Engineering']);
        $other = $this->department(['name' => 'Design']);
        $manager = $this->userWithRole('employee', ['department_id' => $department->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->assignedUsers()->attach($worker->id, ['manpower_category' => 'engineer']);
        $allocation = $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 10]);
        $otherAllocation = $project->departmentAllocations()->create(['department_id' => $other->id, 'allocated_hours' => 20]);
        foreach ([$allocation, $otherAllocation] as $budget) {
            foreach (array_keys(config('manpower_categories.labels')) as $category) {
                $budget->manpowerCategoryAllocations()->create(['manpower_category' => $category, 'allocated_hours' => in_array($category, ['engineer', 'designer'], true) ? ($budget->id === $allocation->id ? 5 : 10) : 0]);
            }
        }
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project, ['status' => 'approved', 'total_regular_hours' => 8, 'total_overtime_hours' => 2, 'total_hours' => 10]);
        $first = $sheet->entries()->first();
        $first->update(['regular_hours' => 4, 'overtime_hours' => 1, 'manpower_category_snapshot' => 'engineer', 'allocation_bucket_snapshot' => 'reserved']);
        $second = $first->replicate();
        $second->fill(['work_date' => '2026-05-12', 'manpower_category_snapshot' => 'designer'])->save();
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin);
        $single = route('projects.entries.department-transfer.show', ['project' => $project, 'entry' => $first]);
        $batch = route('projects.entries.department-batch-transfer.show', ['project' => $project, 'timesheet' => $sheet]);
        $ids = [$first->id, $second->id];

        return compact('department', 'other', 'manager', 'worker', 'project', 'allocation', 'otherAllocation', 'sheet', 'first', 'second', 'admin', 'single', 'batch', 'ids');
    }

    private function singlePreview(array $s, string $category = 'designer', array $extra = []): array
    {
        return $this->getJson($s['single'].'?'.http_build_query(['category_only' => 1, 'manpower_category' => $category] + $extra))->assertOk()->json();
    }

    private function batchPreview(array $s, array $classifications): array
    {
        return $this->getJson($s['batch'].'?'.http_build_query(['entry_ids' => $s['ids'], 'category_only' => 1, 'classifications' => $classifications]))->assertOk()->json();
    }

    private function payload(array $preview, array $extra = []): array
    {
        return ['category_only' => true, 'version' => $preview['version'], 'reason' => 'Correct the manpower classification recorded for this work.'] + $extra;
    }

    public function test_single_category_correction_requires_capacity_then_preserves_all_other_data_and_assignment(): void
    {
        $s = $this->scenario();
        $before = $s['first']->fresh()->only(['department_id', 'project_id', 'timesheet_id', 'work_date', 'regular_hours', 'overtime_hours', 'description', 'remarks']);
        $sheet = $s['sheet']->fresh()->getAttributes();
        $preview = $this->singlePreview($s, 'designer', ['department_id' => $s['other']->id]);
        $this->assertFalse($preview['can_transfer']);
        $this->assertSame($s['department']->id, $preview['destination_id']);
        $this->assertEquals(0, $preview['destination_before']);
        $this->assertEquals(0, $preview['destination_after']);
        $this->postJson($s['single'], $this->payload($preview, ['manpower_category' => 'designer']))->assertUnprocessable();
        $s['allocation']->update(['allocated_hours' => 15]);
        $s['allocation']->manpowerCategoryAllocations()->where('manpower_category', 'designer')->update(['allocated_hours' => 10]);
        $preview = $this->singlePreview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['single'], $this->payload($preview, ['manpower_category' => 'designer', 'department_id' => $s['other']->id]))->assertOk();
        $this->assertSame('designer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertEquals($before, $s['first']->fresh()->only(array_keys($before)));
        $this->assertEquals($sheet, $s['sheet']->fresh()->getAttributes());
        $this->assertSame('engineer', $s['project']->assignedUsers()->find($s['worker']->id)->pivot->manpower_category);
        $audit = AuditLog::where('action', 'timesheet_entry_manpower_category_corrected')->sole();
        $this->assertSame('engineer', $audit->old_values['manpower_category_snapshot']);
        $this->assertSame('designer', $audit->new_values['manpower_category_snapshot']);
        $this->getJson($s['single'])->assertOk()->assertJsonPath('history.0.category_from', 'Engineer')->assertJsonPath('history.0.category_to', 'Designer');
    }

    public function test_batch_swap_of_full_reserved_pools_uses_net_usage_and_does_not_double_count(): void
    {
        $s = $this->scenario();
        $classes = [$s['first']->id => 'designer', $s['second']->id => 'engineer'];
        $preview = $this->batchPreview($s, $classes);
        $this->assertTrue($preview['can_transfer']);
        $this->assertCount(1, $preview['departments']);
        $this->assertEquals(0, $preview['departments'][0]['before']);
        $this->assertEquals(0, $preview['departments'][0]['after']);
        foreach ($preview['pools'] as $pool) {
            $this->assertEquals(0, $pool['before']);
            $this->assertEquals(0, $pool['after']);
        }
        $this->postJson($s['batch'], $this->payload($preview, ['entry_ids' => $s['ids'], 'classifications' => $classes]))->assertOk()->assertJsonPath('count', 2);
        $this->assertSame('designer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertSame('engineer', $s['second']->fresh()->manpower_category_snapshot);
        $this->assertSame($s['department']->id, $s['first']->fresh()->department_id);
        $audits = AuditLog::where('action', 'timesheet_entry_manpower_category_corrected')->get();
        $this->assertCount(2, $audits);
        $this->assertCount(1, $audits->pluck('new_values.batch_id')->unique());
    }

    public function test_category_only_batch_keeps_each_entries_department_when_sources_are_mixed(): void
    {
        $s = $this->scenario();
        $s['second']->update(['department_id' => $s['other']->id, 'manpower_category_snapshot' => 'engineer']);
        $classes = [$s['first']->id => 'designer', $s['second']->id => 'designer'];
        $preview = $this->batchPreview($s, $classes);
        $this->assertTrue($preview['can_transfer']);
        $this->assertCount(2, $preview['departments']);
        foreach ($preview['departments'] as $department) {
            $this->assertEquals($department['before'], $department['after']);
        }
        $this->postJson($s['batch'], $this->payload($preview, ['entry_ids' => $s['ids'], 'classifications' => $classes, 'department_id' => $s['other']->id]))->assertOk();
        $this->assertSame($s['department']->id, $s['first']->fresh()->department_id);
        $this->assertSame($s['other']->id, $s['second']->fresh()->department_id);
    }

    public function test_same_shared_pool_relabelling_does_not_require_additional_hours_even_with_existing_overrun(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 8]);
        $s['allocation']->manpowerCategoryAllocations()->whereIn('manpower_category', ['engineer', 'designer'])->update(['allocated_hours' => null]);
        $s['first']->update(['allocation_bucket_snapshot' => 'shared']);
        $s['second']->update(['allocation_bucket_snapshot' => 'shared']);
        $preview = $this->singlePreview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->assertEquals(-2, $preview['destination_before']);
        $this->assertEquals(-2, $preview['destination_after']);
        $this->assertCount(1, $preview['pools']);
        $this->assertEquals(-2, $preview['pools'][0]['after']);
        $this->postJson($s['single'], $this->payload($preview, ['manpower_category' => 'designer']))->assertOk();
    }

    public function test_legacy_shared_usage_is_released_before_charging_reserved_category(): void
    {
        $s = $this->scenario();
        $s['first']->update(['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null]);
        $preview = $this->singlePreview($s, 'engineer');
        $this->assertTrue($preview['can_transfer']);
        $this->assertEquals(0, $preview['destination_after']);
        $this->postJson($s['single'], $this->payload($preview, ['manpower_category' => 'engineer']))->assertOk();
        $this->assertSame('reserved', $s['first']->fresh()->allocation_bucket_snapshot);
    }

    public function test_department_and_category_can_change_together(): void
    {
        $s = $this->scenario();
        $query = ['department_id' => $s['other']->id, 'manpower_category' => 'designer'];
        $preview = $this->getJson($s['single'].'?'.http_build_query($query))->assertOk()->json();
        $this->postJson($s['single'], ['version' => $preview['version'], 'reason' => 'Correct department and category together.'] + $query)->assertOk();
        $this->assertSame($s['other']->id, $s['first']->fresh()->department_id);
        $this->assertSame('designer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertSame('engineer', AuditLog::where('action', 'timesheet_entry_department_transferred')->sole()->old_values['manpower_category_snapshot']);
    }

    public function test_batch_can_change_recorded_categories_and_department_together(): void
    {
        $s = $this->scenario();
        $classes = [$s['first']->id => 'designer', $s['second']->id => 'engineer'];
        $query = ['entry_ids' => $s['ids'], 'department_id' => $s['other']->id, 'classifications' => $classes];
        $preview = $this->getJson($s['batch'].'?'.http_build_query($query))->assertOk()->json();
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['batch'], ['version' => $preview['version'], 'reason' => 'Correct departments and recorded categories together.'] + $query)->assertOk();
        $this->assertSame($s['other']->id, $s['first']->fresh()->department_id);
        $this->assertSame($s['other']->id, $s['second']->fresh()->department_id);
        $this->assertSame('designer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertSame('engineer', $s['second']->fresh()->manpower_category_snapshot);
        $this->assertSame(2, AuditLog::where('action', 'timesheet_entry_department_transferred')->count());
    }

    public function test_no_op_invalid_and_disallowed_categories_are_rejected_without_changes(): void
    {
        $s = $this->scenario();
        foreach (['engineer', 'invalid'] as $category) {
            $this->getJson($s['single'].'?'.http_build_query(['category_only' => 1, 'manpower_category' => $category]))->assertUnprocessable();
        }
        $preview = $this->singlePreview($s, 'senior_engineer');
        $this->assertFalse($preview['can_transfer']);
        $this->postJson($s['single'], $this->payload($preview, ['manpower_category' => 'senior_engineer']))->assertUnprocessable();
        $this->assertSame('engineer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_category_changes_are_super_admin_only_and_stale_batches_cannot_partially_save(): void
    {
        $s = $this->scenario();
        $classes = [$s['first']->id => 'designer', $s['second']->id => 'engineer'];
        $preview = $this->batchPreview($s, $classes);
        foreach ([$s['manager'], $s['worker'], $this->userWithRole('admin')] as $viewer) {
            $this->actingAs($viewer)->getJson($s['single'].'?category_only=1&manpower_category=designer')->assertForbidden();
            $this->postJson($s['batch'], $this->payload($preview, ['entry_ids' => $s['ids'], 'classifications' => $classes]))->assertForbidden();
        }
        $this->actingAs($s['admin']);
        $s['second']->update(['regular_hours' => 3]);
        $this->postJson($s['batch'], $this->payload($preview, ['entry_ids' => $s['ids'], 'classifications' => $classes]))->assertUnprocessable();
        $this->assertSame('engineer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertSame('designer', $s['second']->fresh()->manpower_category_snapshot);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_open_requests_non_consuming_status_and_audit_failure_block_category_changes(): void
    {
        $s = $this->scenario();
        $classes = [$s['first']->id => 'designer', $s['second']->id => 'engineer'];
        $preview = $this->batchPreview($s, $classes);
        $correction = TimesheetCorrectionRequest::create(['timesheet_id' => $s['sheet']->id, 'requested_by' => $s['admin']->id, 'department_id' => $s['department']->id, 'status' => 'open', 'comment' => 'Review this work.']);
        $correction->entries()->create(['timesheet_entry_id' => $s['first']->id, 'project_id' => $s['project']->id, 'work_date' => $s['first']->work_date, 'project_code' => $s['project']->project_code, 'regular_hours' => 4, 'overtime_hours' => 1]);
        $payload = $this->payload($preview, ['entry_ids' => $s['ids'], 'classifications' => $classes]);
        $this->postJson($s['batch'], $payload)->assertUnprocessable();
        $correction->update(['status' => 'withdrawn']);
        $s['sheet']->update(['status' => 'draft']);
        $this->postJson($s['batch'], $payload)->assertUnprocessable();
        $s['sheet']->update(['status' => 'approved']);
        $preview = $this->batchPreview($s, $classes);
        $this->mock(AuditLogService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson($s['batch'], $this->payload($preview, ['entry_ids' => $s['ids'], 'classifications' => $classes]));
            $this->fail('Expected failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit failed', $error->getMessage());
        }
        $this->assertSame('engineer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_uncontrolled_department_category_corrections_do_not_add_allocation_usage(): void
    {
        $s = $this->scenario();
        $s['allocation']->manpowerCategoryAllocations()->delete();
        $preview = $this->singlePreview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->assertSame([], $preview['pools']);
        $this->postJson($s['single'], $this->payload($preview, ['manpower_category' => 'designer']))->assertOk();
        $this->assertSame('shared', $s['first']->fresh()->allocation_bucket_snapshot);
    }
}
