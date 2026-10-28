<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TimesheetCorrectionRequest;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class TimesheetDepartmentTransferTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    private function scenario(string $status = 'approved'): array
    {
        $source = $this->department(['name' => 'Original discipline']);
        $destination = $this->department(['name' => 'Correct discipline']);
        $manager = $this->userWithRole('employee', ['department_id' => $source->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $source->id]);
        $project = $this->project(['project_manager_id' => $manager->id, 'start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $source->id, 'allocated_hours' => 1]);
        $allocation = $project->departmentAllocations()->create(['department_id' => $destination->id, 'allocated_hours' => 10]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project, ['status' => $status, 'total_regular_hours' => 8, 'total_overtime_hours' => 2, 'total_hours' => 10]);
        $entry = $sheet->entries()->first();
        $entry->update(['regular_hours' => 8, 'overtime_hours' => 2, 'description' => 'Historical engineering work']);
        $url = route('projects.entries.department-transfer.show', ['project' => $project, 'entry' => $entry]);
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin);

        return compact('source', 'destination', 'manager', 'worker', 'project', 'allocation', 'sheet', 'entry', 'url', 'admin');
    }

    private function preview(array $s, ?string $category = null): array
    {
        return $this->getJson($s['url'].'?'.http_build_query(['department_id' => $s['destination']->id, 'manpower_category' => $category]))->assertOk()->json();
    }

    private function payload(array $s, array $preview, ?string $category = null): array
    {
        return ['department_id' => $s['destination']->id, 'version' => $preview['version'], 'reason' => 'Correct a historical department booking.', 'manpower_category' => $category];
    }

    public static function consumingStatuses(): array
    {
        return [['approved'], ['submitted']];
    }

    #[DataProvider('consumingStatuses')]
    public function test_transfers_only_selected_entry_and_preserves_timesheet_and_work_details(string $status): void
    {
        $s = $this->scenario($status);
        $before = $s['entry']->fresh()->only(['project_id', 'timesheet_id', 'work_date', 'regular_hours', 'overtime_hours', 'description', 'remarks', 'attendance_code']);
        $sheetBefore = $s['sheet']->fresh()->getAttributes();
        $sibling = $s['entry']->replicate();
        $sibling->fill(['regular_hours' => 3, 'overtime_hours' => 0])->save();
        $preview = $this->preview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->assertEquals(-12, $preview['source_before']);
        $this->assertEquals(-2, $preview['source_after']);
        $this->assertEquals(0, $preview['destination_after']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertOk();
        $this->assertEquals($before, $s['entry']->fresh()->only(array_keys($before)));
        $this->assertEquals($sheetBefore, $s['sheet']->fresh()->getAttributes());
        $this->assertSame($s['destination']->id, $s['entry']->fresh()->department_id);
        $this->assertSame($s['source']->id, $sibling->fresh()->department_id);
        $this->assertSame($s['source']->id, $s['worker']->fresh()->department_id);
        $audit = AuditLog::where('action', 'timesheet_entry_department_transferred')->sole();
        $this->assertSame($s['admin']->id, $audit->user_id);
        $this->assertSame($s['entry']->id, $audit->auditable_id);
        $this->assertSame($s['source']->id, $audit->old_values['department_id']);
        $this->assertSame($s['destination']->id, $audit->new_values['department_id']);
        $this->assertSame('Correct a historical department booking.', $audit->new_values['reason']);
        $this->getJson($s['url'])->assertOk()->assertJsonPath('history.0.to', 'Correct discipline');
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertSame(1, AuditLog::where('action', 'timesheet_entry_department_transferred')->count());
    }

    public function test_only_super_admin_can_preview_or_save_and_entries_are_scoped_to_project(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        foreach ([$s['manager'], $this->userWithRole('admin'), $this->userWithRole('hod'), $s['worker']] as $viewer) {
            $this->actingAs($viewer)->getJson($s['url'])->assertForbidden();
            $this->postJson($s['url'], $this->payload($s, $preview))->assertForbidden();
        }
        $this->actingAs($s['admin'])->getJson(route('projects.entries.department-transfer.show', ['project' => $this->project(), 'entry' => $s['entry']]))->assertNotFound();
        $this->actingAs($s['manager'])->get(route('projects.utilization', $s['project']))->assertOk()->assertDontSee('data-department-transfer=', false);
        $this->actingAs($s['admin'])->get(route('projects.utilization', $s['project']))->assertOk()->assertSee('data-department-transfer=', false)->assertSee('Refresh preview');
    }

    public function test_insufficient_capacity_blocks_until_allocation_adjusted_and_preview_refreshed(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 2]);
        $preview = $this->preview($s);
        $this->assertFalse($preview['can_transfer']);
        $this->assertEquals(8, $preview['shortfall']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $s['allocation']->update(['allocated_hours' => 10]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertSame($s['source']->id, $s['entry']->fresh()->department_id);
        $this->postJson($s['url'], $this->payload($s, $this->preview($s)))->assertOk();
    }

    public function test_other_entries_in_same_sheet_and_pending_usage_count_but_other_projects_and_drafts_do_not(): void
    {
        $s = $this->scenario('submitted');
        $entry = $s['entry']->replicate();
        $entry->fill(['department_id' => $s['destination']->id, 'regular_hours' => 1, 'overtime_hours' => 0])->save();
        $preview = $this->preview($s);
        $this->assertEquals(9, $preview['destination_before']);
        $this->assertFalse($preview['can_transfer']);
        $entry->update(['project_id' => $this->project()->id]);
        $sheet = $this->submittedTimesheet($this->userWithRole('employee', ['department_id' => $s['destination']->id]), $s['sheet']->period, $s['project'], ['status' => 'draft']);
        $this->assertTrue($this->preview($s)['can_transfer']);
        $sheet->update(['status' => 'approved']);
        $this->assertFalse($this->preview($s)['can_transfer']);
    }

    public function test_stale_entry_status_hours_and_capacity_or_forged_version_cannot_be_saved(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        $s['entry']->update(['regular_hours' => 7]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable()->assertJsonValidationErrors('transfer');
        $preview = $this->preview($s);
        $s['sheet']->update(['status' => 'submitted']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $preview = $this->preview($s);
        $s['allocation']->update(['allocated_hours' => 11]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->postJson($s['url'], $this->payload($s, ['version' => str_repeat('a', 64)]))->assertUnprocessable();
        $this->assertSame($s['source']->id, $s['entry']->fresh()->department_id);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'timesheet_entry_department_transferred']);
    }

    public function test_missing_inactive_same_department_invalid_reason_and_zero_hour_entries_are_rejected(): void
    {
        $s = $this->scenario();
        $this->getJson($s['url'].'?department_id='.$s['source']->id)->assertUnprocessable();
        $unallocated = $this->department();
        $this->getJson($s['url'].'?department_id='.$unallocated->id)->assertUnprocessable();
        $s['destination']->update(['is_active' => false]);
        $this->getJson($s['url'].'?department_id='.$s['destination']->id)->assertUnprocessable();
        $s['destination']->update(['is_active' => true]);
        $preview = $this->preview($s);
        $this->postJson($s['url'], array_replace($this->payload($s, $preview), ['reason' => ' ']))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($s['url'], ['department_id' => $s['destination']->id, 'reason' => 'Correct booking'])->assertUnprocessable()->assertJsonValidationErrors('version');
        $s['entry']->update(['regular_hours' => 0, 'overtime_hours' => 0]);
        $this->getJson($s['url'])->assertUnprocessable();
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
    }

    public function test_open_corrections_and_non_consuming_statuses_block_transfers(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        $request = TimesheetCorrectionRequest::create(['timesheet_id' => $s['sheet']->id, 'requested_by' => $s['admin']->id, 'department_id' => $s['source']->id, 'status' => 'open', 'comment' => 'Check these hours.']);
        $request->entries()->create(['timesheet_entry_id' => $s['entry']->id, 'project_id' => $s['project']->id, 'work_date' => $s['entry']->work_date, 'project_code' => $s['project']->project_code, 'regular_hours' => 8, 'overtime_hours' => 2]);
        $this->getJson($s['url'])->assertUnprocessable();
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $request->update(['status' => 'withdrawn']);
        $this->getJson($s['url'])->assertOk();
        foreach (['draft', 'rejected', 'withdrawn', 'recalled', 'voided'] as $status) {
            $s['sheet']->update(['status' => $status]);
            $this->getJson($s['url'])->assertUnprocessable();
            $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        }
    }

    private function controlCategories(array $s, ?float $engineerHours): void
    {
        foreach (array_keys(config('manpower_categories.labels')) as $category) {
            $s['allocation']->manpowerCategoryAllocations()->create(['manpower_category' => $category, 'allocated_hours' => $category === 'engineer' ? $engineerHours : 0]);
        }
    }

    public function test_legacy_category_requires_explicit_classification_and_valid_historical_category_is_preserved(): void
    {
        $s = $this->scenario();
        $this->controlCategories($s, 10);
        $this->assertFalse($this->preview($s)['can_transfer']);
        $preview = $this->preview($s, 'engineer');
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['url'], $this->payload($s, $preview, 'engineer'))->assertOk();
        $this->assertSame('engineer', $s['entry']->fresh()->manpower_category_snapshot);
        $this->assertSame('reserved', $s['entry']->fresh()->allocation_bucket_snapshot);
        $old = AuditLog::where('action', 'timesheet_entry_department_transferred')->sole();
        $this->assertNull($old->old_values['manpower_category_snapshot']);
    }

    public function test_reserved_and_shared_pool_limits_include_legacy_usage(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 30]);
        $this->controlCategories($s, 4);
        $s['entry']->update(['manpower_category_snapshot' => 'engineer']);
        $preview = $this->preview($s);
        $this->assertFalse($preview['can_transfer']);
        $this->assertEquals(6, $preview['shortfall']);
        $this->getJson($s['url'].'?'.http_build_query(['department_id' => $s['destination']->id, 'manpower_category' => 'designer']))->assertOk()->assertJsonPath('can_transfer', false);
        $s['allocation']->manpowerCategoryAllocations()->where('manpower_category', 'engineer')->update(['allocated_hours' => null]);
        $legacy = $s['entry']->replicate();
        $legacy->fill(['department_id' => $s['destination']->id, 'manpower_category_snapshot' => 'old_title', 'allocation_bucket_snapshot' => null, 'regular_hours' => 21, 'overtime_hours' => 0])->save();
        $this->assertEquals(9, $this->preview($s)['pool_before']);
        $this->assertFalse($this->preview($s)['can_transfer']);
        $legacy->update(['regular_hours' => 20]);
        $preview = $this->preview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertOk();
        $this->assertSame('engineer', $s['entry']->fresh()->manpower_category_snapshot);
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
    }

    public function test_disallowed_and_incomplete_category_configurations_block_transfers(): void
    {
        $s = $this->scenario();
        $this->controlCategories($s, 0);
        $s['entry']->update(['manpower_category_snapshot' => 'engineer']);
        $this->assertFalse($this->preview($s)['can_transfer']);
        $s['allocation']->manpowerCategoryAllocations()->where('manpower_category', 'designer')->delete();
        $this->assertFalse($this->preview($s)['can_transfer']);
    }

    public function test_audit_failure_rolls_back_department_change(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        $this->mock(AuditLogService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson($s['url'], $this->payload($s, $preview));
            $this->fail('Expected the audit error.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit unavailable', $error->getMessage());
        }
        $this->assertSame($s['source']->id, $s['entry']->fresh()->department_id);
    }

    public function test_historical_category_is_not_replaced_by_current_assignment_and_reports_reconcile(): void
    {
        $s = $this->scenario();
        $s['project']->assignedUsers()->attach($s['worker']->id, ['manpower_category' => 'designer']);
        $s['entry']->update(['manpower_category_snapshot' => 'engineer', 'allocation_bucket_snapshot' => 'reserved']);
        $this->controlCategories($s, null);
        $before = $this->get(route('projects.utilization', ['project' => $s['project'], 'tab' => 'analytics']))->assertOk()->viewData('analytics');
        $this->postJson($s['url'], $this->payload($s, $this->preview($s)))->assertOk();
        $this->assertSame('engineer', $s['entry']->fresh()->manpower_category_snapshot);
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        $after = $this->get(route('projects.utilization', ['project' => $s['project'], 'tab' => 'analytics']))->assertOk()->viewData('analytics');
        foreach (['regular', 'overtime', 'allocated', 'approved', 'pending'] as $metric) {
            $this->assertSame($before[$metric], $after[$metric]);
        }
        $response = $this->get(route('projects.utilization', ['project' => $s['project'], 'review_department_id' => $s['destination']->id]))->assertOk();
        $ledger = $response->viewData('allocations')->keyBy('department_id');
        $this->assertEquals(0, $ledger[$s['source']->id]->approved_hours);
        $this->assertEquals(10, $ledger[$s['destination']->id]->approved_hours);
        $this->assertCount(1, $response->viewData('reviewTimesheets')->first()->entries);
        $this->get(route('projects.utilization', ['project' => $s['project'], 'review_department_id' => $s['source']->id]))->assertOk()
            ->assertViewHas('reviewTimesheets', fn ($rows) => $rows->isEmpty());
    }

    public function test_deleted_entry_guest_and_zero_destination_allocation_cannot_transfer(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        auth()->logout();
        $this->getJson($s['url'])->assertUnauthorized();
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnauthorized();
        $this->actingAs($s['admin']);
        $s['allocation']->update(['allocated_hours' => 0]);
        $this->assertFalse($this->preview($s)['can_transfer']);
        $s['entry']->delete();
        $this->postJson($s['url'], $this->payload($s, $preview))->assertNotFound();
    }

    public function test_super_admin_can_increase_destination_before_repairing_overdrawn_source_then_transfer(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 2]);
        $payload = [
            'project_code' => $s['project']->project_code, 'project_name' => $s['project']->project_name,
            'start_date' => '2026-01-01', 'project_manager_id' => $s['manager']->id,
            'timesheet_assignment_mode' => 'all_users', 'is_active' => 1,
            'department_allocations' => [$s['source']->id => 1, $s['destination']->id => 10],
            'allocation_change_reason' => 'Increase destination allowance to correct historical bookings.',
        ];
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $preview = $this->preview($s);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertOk();
        $payload['department_allocations'][$s['destination']->id] = 9;
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors('department_allocations.'.$s['destination']->id);
    }
}
