<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Services\AuditLogService;
use App\Services\ProjectAllocationSpreadsheetService;
use App\Services\TimesheetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ManpowerPoolConversionTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_any_standard_shared_category_can_become_reserved_without_changing_entry_details(): void
    {
        $s = $this->scenario();
        foreach (array_keys(config('manpower_categories.labels')) as $category) {
            $s['entry']->refresh()->update(['manpower_category_snapshot' => $category, 'allocation_bucket_snapshot' => 'shared']);
            $payload = $this->payload($s, [$category => 10], 10);
            $before = $s['entry']->fresh()->only(['timesheet_id', 'project_id', 'department_id', 'manpower_category_snapshot', 'regular_hours', 'overtime_hours', 'work_date']);
            $plan = $this->preview($s, $payload)->assertJsonPath('valid', true)->assertJsonPath('conversions.0.count', 1)
                ->assertJsonPath('conversions.0.approved', 10)->assertJsonPath('balances.0.remaining', 0)->json();
            $payload['pool_conversion_version'] = $plan['version'];
            $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('reserved', $s['entry']->fresh()->allocation_bucket_snapshot);
            $this->assertEquals($before, $s['entry']->fresh()->only(array_keys($before)));
            $this->assertSame(Timesheet::STATUS_APPROVED, $s['sheet']->fresh()->status);
            $log = AuditLog::where('action', 'timesheet_entry_allocation_pool_converted')->latest('id')->firstOrFail();
            $this->assertSame('shared', $log->old_values['allocation_bucket_snapshot']);
            $this->assertSame('reserved', $log->new_values['allocation_bucket_snapshot']);
            $this->assertSame($payload['allocation_change_reason'], $log->new_values['reason']);
            $this->get(route('projects.entries.department-transfer.show', ['project' => $s['project'], 'entry' => $s['entry']]))
                ->assertOk()->assertJsonPath('history.0.pool_from', 'shared')->assertJsonPath('history.0.pool_to', 'reserved');
            // Restore only fixture configuration for the next independently tested category.
            $s['allocation']->manpowerCategoryAllocations()->update(['allocated_hours' => null]);
        }
        $this->assertSame('engineer', $s['project']->assignedUsers()->first()->pivot->manpower_category);
    }

    public function test_electrical_example_can_convert_321_hours_and_reduce_department_to_11250_in_one_save(): void
    {
        $s = $this->scenario('lead_engineer_checker');
        $s['allocation']->update(['allocated_hours' => 12535.50]);
        $s['entry']->update(['regular_hours' => 321, 'overtime_hours' => 0]);
        $budgets = ['lead_engineer_checker' => 321, 'senior_engineer' => 702, 'engineer' => 2584.5, 'designer' => 7642.5];
        foreach (array_slice($budgets, 1) as $category => $hours) {
            $s['allocation']->manpowerCategoryAllocations()->where('manpower_category', $category)->update(['allocated_hours' => $hours]);
            $this->copyEntry($s, ['manpower_category_snapshot' => $category, 'allocation_bucket_snapshot' => 'reserved', 'regular_hours' => $hours, 'overtime_hours' => 0]);
        }
        $payload = $this->payload($s, $budgets, 11250);
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->assertJsonPath('valid', true)
            ->assertJsonPath('conversions.0.hours', 321)->assertJsonPath('balances.0.shared_remaining', 0)->json('version');
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(11250, $s['allocation']->fresh()->allocated_hours);
        $this->assertSame('reserved', $s['entry']->fresh()->allocation_bucket_snapshot);
        $this->get(route('projects.utilization', $s['project']))->assertOk()
            ->assertViewHas('allocations', fn ($a) => $a->sole()->manpower_category_usage->where('state', 'shared')->isEmpty()
                && $a->sole()->manpower_category_usage->every(fn ($row) => $row->allocated_hours == $row->approved_hours + $row->pending_hours));
    }

    public function test_multiple_categories_include_pending_and_existing_reserved_usage_and_link_audits(): void
    {
        $s = $this->scenario();
        $second = $this->copyEntry($s, ['manpower_category_snapshot' => 'designer', 'regular_hours' => 1.25, 'overtime_hours' => 0.10]);
        $reserved = $this->copyEntry($s, ['allocation_bucket_snapshot' => 'reserved', 'regular_hours' => 2, 'overtime_hours' => 0]);
        $s['sheet']->update(['status' => Timesheet::STATUS_SUBMITTED]);
        $payload = $this->payload($s, ['engineer' => 12, 'designer' => 1.35], 13.35);
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->assertJsonPath('valid', true)
            ->assertJsonPath('conversions.0.pending', 10)->json('version');
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors();
        $this->assertSame('reserved', $second->fresh()->allocation_bucket_snapshot);
        $this->assertSame('reserved', $reserved->fresh()->allocation_bucket_snapshot);
        $logs = AuditLog::where('action', 'timesheet_entry_allocation_pool_converted')->get();
        $this->assertCount(2, $logs);
        $this->assertSame($logs[0]->new_values['batch_id'], $logs[1]->new_values['batch_id']);
        $this->assertSame(2, $logs[0]->new_values['batch_count']);
    }

    public function test_legacy_or_incomplete_consumed_entries_in_same_department_block_conversion(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s, ['engineer' => 10], 30);
        $legacy = $this->copyEntry($s, ['regular_hours' => 1, 'overtime_hours' => 0]);
        foreach ([[null, 'shared'], ['obsolete', 'shared'], ['designer', null], ['designer', 'unknown']] as [$category, $bucket]) {
            $legacy->update(['manpower_category_snapshot' => $category, 'allocation_bucket_snapshot' => $bucket]);
            $this->preview($s, $payload)->assertJsonPath('valid', false)->assertJsonPath('errors', fn ($messages) => str_contains(collect($messages)->flatten()->implode(' '), 'Legacy / Unclassified'));
            $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors('job_level_allocations.'.$s['department']->id);
            $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
            $this->assertEquals(100, $s['allocation']->fresh()->allocated_hours);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_other_project_department_and_nonconsuming_legacy_do_not_block_or_get_changed(): void
    {
        $s = $this->scenario();
        $foreign = $this->copyEntry($s, ['project_id' => $this->project()->id, 'manpower_category_snapshot' => null]);
        $otherDepartment = $this->copyEntry($s, ['department_id' => $this->department()->id, 'manpower_category_snapshot' => null]);
        $zero = $this->copyEntry($s, ['regular_hours' => 0, 'overtime_hours' => 0, 'manpower_category_snapshot' => null]);
        $worker = $this->userWithRole('employee', ['department_id' => $s['department']->id]);
        $inactive = $this->submittedTimesheet($worker, $s['sheet']->period, $s['project'], ['status' => Timesheet::STATUS_DRAFT]);
        foreach ([Timesheet::STATUS_DRAFT, Timesheet::STATUS_REJECTED, Timesheet::STATUS_RECALLED, Timesheet::STATUS_VOIDED] as $status) {
            $inactive->update(['status' => $status]);
            $this->preview($s, $this->payload($s, ['engineer' => 10], 10))->assertJsonPath('valid', true);
        }
        $payload = $this->payload($s, ['engineer' => 10], 10);
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->json('version');
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors();
        foreach ([$foreign, $otherDepartment, $zero, $inactive->entries()->first()] as $entry) {
            $this->assertNull($entry->fresh()->manpower_category_snapshot);
            $this->assertNotSame('reserved', $entry->fresh()->allocation_bucket_snapshot);
        }
    }

    public function test_reservation_department_and_remaining_shared_capacity_are_enforced(): void
    {
        $s = $this->scenario();
        $other = $this->copyEntry($s, ['manpower_category_snapshot' => 'designer', 'regular_hours' => 5, 'overtime_hours' => 0]);
        $reserved = $this->copyEntry($s, ['allocation_bucket_snapshot' => 'reserved', 'regular_hours' => 2, 'overtime_hours' => 0]);
        foreach ([[['engineer' => 10], 17], [['engineer' => 12], 16], [['engineer' => 15], 17]] as [$settings, $total]) {
            $payload = $this->payload($s, $settings, $total);
            $this->preview($s, $payload)->assertJsonPath('valid', false);
            $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors();
        }
        $payload = $this->payload($s, ['engineer' => 12], 17);
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->assertJsonPath('valid', true)->assertJsonPath('balances.0.shared_consumed', 5)->json('version');
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors();
        $this->assertSame('shared', $other->fresh()->allocation_bucket_snapshot);
        $this->assertSame('reserved', $reserved->fresh()->allocation_bucket_snapshot);
    }

    public function test_open_correction_blocks_conversion_until_resolved(): void
    {
        $s = $this->scenario();
        $request = TimesheetCorrectionRequest::create(['timesheet_id' => $s['sheet']->id, 'requested_by' => auth()->id(), 'department_id' => $s['department']->id, 'status' => 'open', 'comment' => 'Please check.']);
        $request->entries()->create(['timesheet_entry_id' => $s['entry']->id, 'work_date' => $s['entry']->work_date]);
        $payload = $this->payload($s, ['engineer' => 10], 10);
        $this->preview($s, $payload)->assertJsonPath('valid', false)->assertSee('correction requests');
        $request->update(['status' => 'withdrawn']);
        $this->preview($s, $payload)->assertJsonPath('valid', true);
    }

    public function test_missing_stale_and_tampered_previews_cannot_partially_save(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s, ['engineer' => 10], 10);
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors('pool_conversion_version');
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->json('version');
        $s['sheet']->update(['status' => Timesheet::STATUS_SUBMITTED]);
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors('pool_conversion_version');
        $payload['pool_conversion_version'] = str_repeat('0', 64);
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors('pool_conversion_version');
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->json('version');
        $payload['department_allocations'][$s['department']->id] = 20;
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasErrors('pool_conversion_version');
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_super_admin_can_convert_consumed_hours_and_preview_is_read_only(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s, ['engineer' => 10], 10);
        $this->preview($s, $payload)->assertJsonPath('valid', true);
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        foreach (['admin', 'employee', 'hod'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->postJson(route('manage.projects.pool-conversion.preview', $s['project']), $payload)->assertForbidden();
            $response = $this->put(route('manage.projects.update', $s['project']), $payload);
            if ($role === 'admin') {
                $response->assertSessionHasErrors('job_level_allocations.'.$s['department']->id);
            } else {
                $response->assertForbidden();
            }
        }
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
    }

    public function test_audit_failure_rolls_back_entry_and_allocation_changes(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s, ['engineer' => 10], 10);
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->json('version');
        $this->mock(AuditLogService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->put(route('manage.projects.update', $s['project']), $payload);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        $this->assertEquals(100, $s['allocation']->fresh()->allocated_hours);
        $this->assertNull($s['allocation']->manpowerCategoryAllocations()->where('manpower_category', 'engineer')->first()->allocated_hours);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_incomplete_pool_can_be_explicitly_classified_without_changing_the_recorded_category(): void
    {
        $s = $this->scenario();
        $s['entry']->update(['allocation_bucket_snapshot' => null]);
        $url = route('projects.entries.department-transfer.show', ['project' => $s['project'], 'entry' => $s['entry']]);
        $preview = $this->getJson($url.'?category_only=1&manpower_category=engineer')->assertOk()->assertJsonPath('can_transfer', true)->json();
        $this->postJson($url, ['category_only' => true, 'manpower_category' => 'engineer', 'version' => $preview['version'], 'reason' => 'Confirm the recorded category and repair its missing pool.'])->assertOk();
        $this->assertSame('engineer', $s['entry']->fresh()->manpower_category_snapshot);
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        $this->preview($s, $this->payload($s, ['engineer' => 10], 10))->assertJsonPath('valid', true);
    }

    public function test_subsequent_submissions_use_the_new_reserved_pool_and_count_converted_consumption(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s, ['engineer' => 15], 20);
        $payload['pool_conversion_version'] = $this->preview($s, $payload)->json('version');
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors();
        $worker = $this->userWithRole('employee', ['department_id' => $s['department']->id]);
        $s['project']->assignedUsers()->attach($worker, ['manpower_category' => 'engineer']);
        $service = app(TimesheetAllocationService::class);
        $snapshots = DB::transaction(fn () => $service->validateSubmission($worker, $this->validEntries($s['project'], ['2026-05-11' => ['regular_hours' => 5]])));
        $this->assertSame('reserved', $snapshots[0]['allocation_bucket_snapshot']);
        try {
            DB::transaction(fn () => $service->validateSubmission($worker, $this->validEntries($s['project'], ['2026-05-11' => ['regular_hours' => 6]])));
            $this->fail('Converted consumption must count against the reservation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(TimesheetAllocationService::EXCEEDED_MESSAGE, implode(' ', $exception->validator->errors()->all()));
        }
    }

    public function test_excel_preview_uses_the_conversion_rules_without_mutating_entries(): void
    {
        $s = $this->scenario();
        $service = app(ProjectAllocationSpreadsheetService::class);
        $book = $service->template($s['project']);
        $sheet = $book->getSheetByName('Department Allocations');
        $sheet->setCellValue('D2', 10);
        $sheet->setCellValue('J2', 'Reserved');
        $sheet->setCellValue('K2', 10);
        $path = tempnam(sys_get_temp_dir(), 'pool-conversion-');
        try {
            (new Xlsx($book))->save($path);
            $plan = $service->preview($path, $this->payload($s, [], 100), $s['project']);
            $this->assertTrue($plan['valid'], json_encode($plan));
            $this->assertStringContainsString('recorded shared hours will move', implode(' ', $plan['rows'][0]['warnings']));
            $this->copyEntry($s, ['manpower_category_snapshot' => null]);
            $plan = $service->preview($path, $this->payload($s, [], 100), $s['project']);
            $this->assertFalse($plan['valid']);
            $this->assertStringContainsString('Legacy / Unclassified', implode(' ', $plan['rows'][0]['errors']));
            $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_older_project_without_category_controls_keeps_legacy_hours_and_accepts_budget_edits_and_submissions(): void
    {
        $s = $this->scenario();
        $s['project']->update(['start_date' => '2019-01-01']);
        $s['allocation']->manpowerCategoryAllocations()->delete();
        $s['entry']->update(['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null]);
        $payload = $this->payload($s, [], 90);
        $payload['start_date'] = '2019-01-01';
        $payload['job_level_controls'] = [$s['department']->id => 0];
        $payload['assigned_user_categories'] = [$s['worker']->id => null];
        $this->preview($s, $payload)->assertJsonPath('valid', true)->assertJsonPath('conversions', []);
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(90, $s['allocation']->fresh()->allocated_hours);
        $this->assertSame(0, $s['allocation']->manpowerCategoryAllocations()->count());
        $this->assertNull($s['entry']->fresh()->manpower_category_snapshot);
        $this->assertNull($s['entry']->fresh()->allocation_bucket_snapshot);
        $snapshots = DB::transaction(fn () => app(TimesheetAllocationService::class)->validateSubmission($s['worker'], $this->validEntries($s['project'])));
        $this->assertSame(['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null], $snapshots[0]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'timesheet_entry_allocation_pool_converted']);
    }

    public function test_older_project_can_keep_shared_categories_and_continue_charging_with_legacy_consumption(): void
    {
        $s = $this->scenario();
        $s['project']->update(['start_date' => '2019-01-01']);
        $legacy = $this->copyEntry($s, ['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null]);
        $payload = $this->payload($s, [], 100);
        $payload['start_date'] = '2019-01-01';
        $this->preview($s, $payload)->assertJsonPath('valid', true)->assertJsonPath('conversions', []);
        $this->put(route('manage.projects.update', $s['project']), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($s['allocation']->manpowerCategoryAllocations()->get()->every(fn ($row) => $row->allocated_hours === null));
        $this->assertNull($legacy->fresh()->manpower_category_snapshot);
        $this->assertNull($legacy->fresh()->allocation_bucket_snapshot);
        $this->assertSame('shared', $s['entry']->fresh()->allocation_bucket_snapshot);
        $snapshots = DB::transaction(fn () => app(TimesheetAllocationService::class)->validateSubmission($s['worker'], $this->validEntries($s['project'])));
        $this->assertSame('shared', $snapshots[0]['allocation_bucket_snapshot']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'timesheet_entry_allocation_pool_converted']);
    }

    private function scenario(string $category = 'engineer'): array
    {
        $department = $this->department();
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $worker->id, 'start_date' => '2026-01-01', 'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS]);
        $project->assignedUsers()->attach($worker, ['manpower_category' => 'engineer']);
        $allocation = $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);
        foreach (array_keys(config('manpower_categories.labels')) as $value) {
            $allocation->manpowerCategoryAllocations()->create(['manpower_category' => $value, 'allocated_hours' => null]);
        }
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project, ['status' => Timesheet::STATUS_APPROVED]);
        $entry = $sheet->entries()->first();
        $entry->update(['manpower_category_snapshot' => $category, 'allocation_bucket_snapshot' => 'shared', 'regular_hours' => 8, 'overtime_hours' => 2]);
        $this->actingAs($this->userWithRole('super_admin'));

        return compact('department', 'worker', 'project', 'allocation', 'sheet', 'entry');
    }

    private function payload(array $s, array $reservations, float $total): array
    {
        $settings = [];
        foreach (array_keys(config('manpower_categories.labels')) as $category) {
            $settings[$category] = isset($reservations[$category]) ? ['mode' => 'reserved', 'hours' => $reservations[$category]] : ['mode' => 'shared'];
        }

        return ['project_code' => $s['project']->project_code, 'project_name' => $s['project']->project_name,
            'start_date' => '2026-01-01', 'project_manager_id' => $s['worker']->id, 'is_active' => 1,
            'timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS, 'assigned_user_ids' => [$s['worker']->id],
            'assigned_user_categories' => [$s['worker']->id => 'engineer'],
            'department_allocations' => [$s['department']->id => $total], 'job_level_controls' => [$s['department']->id => 1],
            'job_level_allocations' => [$s['department']->id => $settings], 'allocation_change_reason' => 'Reserve the previously classified shared hours.'];
    }

    private function preview(array $s, array $payload)
    {
        return $this->postJson(route('manage.projects.pool-conversion.preview', $s['project']), $payload)->assertOk();
    }

    private function copyEntry(array $s, array $attributes)
    {
        $entry = $s['entry']->replicate();
        $entry->fill($attributes)->save();

        return $entry;
    }
}
