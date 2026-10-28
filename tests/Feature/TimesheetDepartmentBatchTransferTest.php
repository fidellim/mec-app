<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TimesheetCorrectionRequest;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class TimesheetDepartmentBatchTransferTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    private function scenario(): array
    {
        $source = $this->department(['name' => 'Civil']);
        $secondSource = $this->department(['name' => 'Electrical']);
        $destination = $this->department(['name' => 'Mechanical']);
        $manager = $this->userWithRole('employee', ['department_id' => $source->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $source->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->departmentAllocations()->create(['department_id' => $source->id, 'allocated_hours' => 1]);
        $project->departmentAllocations()->create(['department_id' => $secondSource->id, 'allocated_hours' => 5]);
        $allocation = $project->departmentAllocations()->create(['department_id' => $destination->id, 'allocated_hours' => 18]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project, ['status' => 'approved', 'total_regular_hours' => 17, 'total_overtime_hours' => 4, 'total_hours' => 21]);
        $first = $sheet->entries()->first();
        $first->update(['regular_hours' => 8, 'overtime_hours' => 2, 'description' => 'First selected entry']);
        $second = $first->replicate();
        $second->fill(['work_date' => '2026-05-12', 'department_id' => $secondSource->id, 'regular_hours' => 6, 'description' => 'Second selected entry'])->save();
        $sibling = $first->replicate();
        $sibling->fill(['work_date' => '2026-05-13', 'regular_hours' => 3, 'overtime_hours' => 0, 'description' => 'Leave this entry unchanged'])->save();
        $ids = [$first->id, $second->id];
        $url = route('projects.entries.department-batch-transfer.show', ['project' => $project, 'timesheet' => $sheet]);
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin);

        return compact('source', 'secondSource', 'destination', 'manager', 'worker', 'project', 'allocation', 'sheet', 'first', 'second', 'sibling', 'ids', 'url', 'admin');
    }

    private function preview(array $s, array $classifications = []): array
    {
        return $this->getJson($s['url'].'?'.http_build_query(['entry_ids' => $s['ids'], 'department_id' => $s['destination']->id, 'classifications' => $classifications]))->assertOk()->json();
    }

    private function payload(array $s, array $preview, array $classifications = []): array
    {
        return ['entry_ids' => $s['ids'], 'department_id' => $s['destination']->id, 'classifications' => $classifications, 'version' => $preview['version'], 'reason' => 'Correct historical department charges for this week.'];
    }

    private function assertUnmoved(array $s): void
    {
        $this->assertSame($s['source']->id, $s['first']->fresh()->department_id);
        $this->assertSame($s['secondSource']->id, $s['second']->fresh()->department_id);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'timesheet_entry_department_transferred']);
    }

    private function categories(array $s, array $limits): void
    {
        foreach (array_keys(config('manpower_categories.labels')) as $category) {
            $s['allocation']->manpowerCategoryAllocations()->create(['manpower_category' => $category, 'allocated_hours' => array_key_exists($category, $limits) ? $limits[$category] : 0]);
        }
    }

    public function test_transfers_selection_atomically_with_multiple_sources_and_linked_audit_records(): void
    {
        $s = $this->scenario();
        $sheetBefore = $s['sheet']->fresh()->getAttributes();
        $fields = ['project_id', 'timesheet_id', 'regular_hours', 'overtime_hours', 'work_date', 'description', 'attendance_code', 'remarks'];
        $before = [$s['first']->fresh()->only($fields), $s['second']->fresh()->only($fields)];
        $preview = $this->preview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->assertEquals(18, $preview['hours']);
        $this->assertEquals(14, $preview['regular']);
        $this->assertEquals(4, $preview['overtime']);
        $this->assertEquals(0, $preview['destination_after']);
        $this->assertCount(2, $preview['sources']);
        $payload = $this->payload($s, $preview);
        $payload['entry_ids'] = array_reverse($payload['entry_ids']);
        $response = $this->postJson($s['url'], $payload)->assertOk()->assertJsonPath('count', 2);
        foreach ([$s['first'], $s['second']] as $index => $entry) {
            $this->assertSame($s['destination']->id, $entry->fresh()->department_id);
            $this->assertEquals($before[$index], $entry->fresh()->only($fields));
        }
        $this->assertSame($s['source']->id, $s['sibling']->fresh()->department_id);
        $this->assertSame($s['source']->id, $s['worker']->fresh()->department_id);
        $this->assertEquals($sheetBefore, $s['sheet']->fresh()->getAttributes());
        $audits = AuditLog::where('action', 'timesheet_entry_department_transferred')->get();
        $this->assertCount(2, $audits);
        $this->assertSame([$response->json('batch_id')], $audits->pluck('new_values.batch_id')->unique()->values()->all());
        $this->assertSame([2], $audits->pluck('new_values.batch_count')->unique()->all());
        $this->postJson($s['url'], $payload)->assertUnprocessable();
        $this->assertSame(2, AuditLog::where('action', 'timesheet_entry_department_transferred')->count());
        $data = $this->get(route('projects.utilization', ['project' => $s['project'], 'tab' => 'analytics']))->assertOk()->viewData('analytics');
        $this->assertSame(21.0, $data['approved']);
        $this->assertSame(0.0, $data['pending']);
        $this->assertSame(24.0, $data['allocated']);
    }

    public function test_combined_department_usage_cannot_pass_individual_entry_capacity_checks(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 10]);
        foreach ([$s['first'], $s['second']] as $entry) {
            $url = route('projects.entries.department-transfer.show', ['project' => $s['project'], 'entry' => $entry, 'department_id' => $s['destination']->id]);
            $this->getJson($url)->assertOk()->assertJsonPath('can_transfer', true);
        }
        $preview = $this->preview($s);
        $this->assertFalse($preview['can_transfer']);
        $this->assertEquals(-8, $preview['destination_after']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertUnmoved($s);
        $s['allocation']->update(['allocated_hours' => 18]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->postJson($s['url'], $this->payload($s, $this->preview($s)))->assertOk();
    }

    public function test_reserved_pool_is_checked_for_combined_category_hours(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 30]);
        $this->categories($s, ['engineer' => 10, 'designer' => 20]);
        foreach ([$s['first'], $s['second']] as $entry) {
            $entry->update(['manpower_category_snapshot' => 'engineer']);
        }
        $preview = $this->preview($s);
        $this->assertFalse($preview['can_transfer']);
        $this->assertEquals(12, $preview['destination_after']);
        $this->assertEquals(-8, $preview['pools'][0]['after']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertUnmoved($s);
    }

    public function test_different_categories_in_the_same_shared_pool_are_combined_including_legacy_usage(): void
    {
        $s = $this->scenario();
        $s['allocation']->update(['allocated_hours' => 40]);
        $this->categories($s, ['engineer' => null, 'designer' => null, 'lead_engineer_checker' => 10]);
        $s['first']->update(['manpower_category_snapshot' => 'engineer']);
        $s['second']->update(['manpower_category_snapshot' => 'designer']);
        $legacy = $s['first']->replicate();
        $legacy->fill(['department_id' => $s['destination']->id, 'manpower_category_snapshot' => 'old_title', 'allocation_bucket_snapshot' => null, 'regular_hours' => 13, 'overtime_hours' => 0])->save();
        $preview = $this->preview($s);
        $this->assertCount(1, $preview['pools']);
        $this->assertEquals(17, $preview['pools'][0]['before']);
        $this->assertEquals(-1, $preview['pools'][0]['after']);
        $this->assertFalse($preview['can_transfer']);
        $legacy->update(['regular_hours' => 12]);
        $preview = $this->preview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertOk();
        $this->assertSame('shared', $s['first']->fresh()->allocation_bucket_snapshot);
        $this->assertSame('shared', $s['second']->fresh()->allocation_bucket_snapshot);
    }

    public function test_explicit_classification_per_legacy_entry_preserves_recorded_categories(): void
    {
        $s = $this->scenario();
        $this->categories($s, ['engineer' => 10, 'designer' => 8]);
        $preview = $this->preview($s);
        $this->assertFalse($preview['can_transfer']);
        $sameCategory = [$s['first']->id => 'engineer', $s['second']->id => 'engineer'];
        $this->assertFalse($this->preview($s, $sameCategory)['can_transfer']);
        $classifications = [$s['first']->id => 'engineer', $s['second']->id => 'designer'];
        $preview = $this->preview($s, $classifications);
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['url'], $this->payload($s, $preview, $classifications))->assertOk();
        $this->assertSame('engineer', $s['first']->fresh()->manpower_category_snapshot);
        $this->assertSame('designer', $s['second']->fresh()->manpower_category_snapshot);
        $this->assertSame('reserved', $s['first']->fresh()->allocation_bucket_snapshot);
        $this->assertSame('reserved', $s['second']->fresh()->allocation_bucket_snapshot);
        $this->assertNull(AuditLog::where('auditable_id', $s['first']->id)->where('action', 'timesheet_entry_department_transferred')->sole()->old_values['manpower_category_snapshot']);
    }

    public function test_stale_member_or_capacity_blocks_the_entire_selection(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        $s['second']->update(['regular_hours' => 5]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable()->assertJsonValidationErrors('transfer');
        $this->assertUnmoved($s);
        $preview = $this->preview($s);
        $s['allocation']->update(['allocated_hours' => 20]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertUnmoved($s);
        $preview = $this->preview($s);
        $s['sheet']->update(['status' => 'submitted']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertUnmoved($s);
    }

    public function test_one_conflicting_correction_or_invalid_entry_blocks_all_entries(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        $correction = TimesheetCorrectionRequest::create(['timesheet_id' => $s['sheet']->id, 'requested_by' => $s['admin']->id, 'department_id' => $s['source']->id, 'status' => 'open', 'comment' => 'Check the second entry.']);
        $correction->entries()->create(['timesheet_entry_id' => $s['second']->id, 'project_id' => $s['project']->id, 'work_date' => $s['second']->work_date, 'project_code' => $s['project']->project_code, 'regular_hours' => 6, 'overtime_hours' => 2]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertUnmoved($s);
        $correction->update(['status' => 'withdrawn']);
        $s['second']->update(['regular_hours' => 0, 'overtime_hours' => 0]);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertUnmoved($s);
        $s['second']->delete();
        $this->postJson($s['url'], $this->payload($s, $preview))->assertUnprocessable();
        $this->assertSame($s['source']->id, $s['first']->fresh()->department_id);
    }

    public function test_selection_cannot_mix_employees_weeks_or_projects_or_include_duplicates(): void
    {
        $s = $this->scenario();
        $worker = $this->userWithRole('employee', ['department_id' => $s['source']->id]);
        $foreignSheet = $this->submittedTimesheet($worker, $s['sheet']->period, $s['project']);
        $otherWeek = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $otherWeekSheet = $this->submittedTimesheet($s['worker'], $otherWeek, $s['project']);
        $foreignProject = $s['first']->replicate();
        $foreignProject->project_id = $this->project()->id;
        $foreignProject->save();
        foreach ([$foreignSheet->entries()->first()->id, $otherWeekSheet->entries()->first()->id, $foreignProject->id, $s['first']->id, 999999] as $invalidId) {
            $this->getJson($s['url'].'?'.http_build_query(['entry_ids' => [$s['first']->id, $invalidId]]))->assertUnprocessable();
            $this->postJson($s['url'], ['entry_ids' => [$s['first']->id, $invalidId], 'department_id' => $s['destination']->id, 'version' => str_repeat('a', 64), 'reason' => 'Invalid batch must not be accepted.'])->assertUnprocessable();
        }
        $this->getJson($s['url'])->assertUnprocessable();
        $this->getJson($s['url'].'?'.http_build_query(['entry_ids' => range(1, 51)]))->assertUnprocessable();
        $this->assertUnmoved($s);
    }

    public function test_permissions_and_same_destination_are_enforced(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        foreach ([$s['manager'], $s['worker'], $this->userWithRole('admin'), $this->userWithRole('hod')] as $viewer) {
            $this->actingAs($viewer)->getJson($s['url'].'?'.http_build_query(['entry_ids' => $s['ids']]))->assertForbidden();
            $this->postJson($s['url'], $this->payload($s, $preview))->assertForbidden();
        }
        auth()->logout();
        $this->getJson($s['url'])->assertUnauthorized();
        $this->actingAs($s['admin']);
        $this->getJson($s['url'].'?'.http_build_query(['entry_ids' => $s['ids'], 'department_id' => $s['secondSource']->id]))->assertUnprocessable();
        $this->assertUnmoved($s);
    }

    public function test_audit_failure_on_second_entry_rolls_back_first_entry_and_its_audit(): void
    {
        $s = $this->scenario();
        $preview = $this->preview($s);
        $calls = 0;
        $this->mock(AuditLogService::class)->shouldReceive('record')->twice()->andReturnUsing(function (...$arguments) use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('Second audit failed');
            }
            (new AuditLogService)->record(...$arguments);
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson($s['url'], $this->payload($s, $preview));
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Second audit failed', $error->getMessage());
        }
        $this->assertUnmoved($s);
    }

    public function test_partial_selection_decimal_hours_and_pending_status_are_supported(): void
    {
        $s = $this->scenario();
        $s['sheet']->update(['status' => 'submitted']);
        $s['first']->update(['regular_hours' => 1.10, 'overtime_hours' => 0.20]);
        $s['allocation']->update(['allocated_hours' => 1.30]);
        $s['ids'] = [$s['first']->id];
        $preview = $this->preview($s);
        $this->assertTrue($preview['can_transfer']);
        $this->postJson($s['url'], $this->payload($s, $preview))->assertOk()->assertJsonPath('count', 1);
        $this->assertSame($s['secondSource']->id, $s['second']->fresh()->department_id);
        $this->assertSame('submitted', $s['sheet']->fresh()->status);
    }

    public function test_category_keys_and_known_historical_classifications_cannot_be_rewritten(): void
    {
        $s = $this->scenario();
        $this->categories($s, ['engineer' => 18]);
        $s['first']->update(['manpower_category_snapshot' => 'engineer']);
        $query = ['entry_ids' => $s['ids'], 'department_id' => $s['destination']->id];
        $this->getJson($s['url'].'?'.http_build_query($query + ['classifications' => [$s['sibling']->id => 'engineer']]))->assertUnprocessable();
        $this->getJson($s['url'].'?'.http_build_query($query + ['classifications' => [$s['first']->id => 'designer']]))->assertUnprocessable();
        $this->getJson($s['url'].'?'.http_build_query($query + ['classifications' => [$s['second']->id => 'invalid']]))->assertUnprocessable();
        $this->assertUnmoved($s);
    }
}
