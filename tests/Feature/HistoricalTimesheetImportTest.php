<?php

namespace Tests\Feature;

use App\Models\LeavePlan;
use App\Models\Timesheet;
use App\Models\TimesheetStatusHistory;
use App\Services\HistoricalTimesheetImportService;
use App\Services\HistoricalTimesheetImportStore;
use App\Services\HistoricalTimesheetWorkbookReader;
use App\Services\LeaveEntitlementService;
use App\Services\TimesheetAllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class HistoricalTimesheetImportTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));
        config(['timesheet_import.directory' => sys_get_temp_dir().'/timesheet-import-test-'.Str::uuid()]);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(config('timesheet_import.directory'));
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function scenario(bool $categories = false): array
    {
        $department = $this->department();
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $admin = $this->userWithRole('super_admin');
        $period = $this->openPeriod(['status' => 'closed']);
        $project = $this->project();
        $allocation = $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);
        if ($categories) {
            foreach (array_keys(config('manpower_categories.labels')) as $category) {
                $allocation->manpowerCategoryAllocations()->create(['manpower_category' => $category, 'allocated_hours' => $category === 'engineer' ? 50 : null]);
            }
        }
        $this->actingAs($admin);

        return compact('department', 'worker', 'admin', 'period', 'project', 'allocation');
    }

    private function item(array $s, array $entryOverrides = []): array
    {
        return app(HistoricalTimesheetImportService::class)->populate([
            'filename' => 'historical.xlsx', 'sha256' => str_repeat('a', 64), 'worksheet' => 'Employee Time Record',
            'employee_code' => $s['worker']->employee_code, 'employee_name' => $s['worker']->name, 'start_date' => '2026-05-11',
            'entries' => [array_replace(['source_row' => 17, 'work_date' => '2026-05-11', 'attendance_code' => 'O100', 'project_code' => $s['project']->project_code, 'department_id' => null, 'manpower_category' => null, 'regular_hours' => 8, 'overtime_hours' => 0, 'remarks' => 'Historical work'], $entryOverrides)],
        ]);
    }

    private function stage(array $items): string
    {
        return app(HistoricalTimesheetImportStore::class)->create(auth()->id(), $items);
    }

    private function workbook(array $s, string $extension = 'xlsx', ?callable $change = null): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Employee Time Record');
        foreach (['B15' => 'Project No.', 'C15' => 'Code', 'Q8' => $s['worker']->employee_code, 'D8' => $s['worker']->name, 'C5' => Date::PHPToExcel(Carbon::parse('2026-05-11')), 'B17' => $s['project']->project_code, 'C17' => 'O100', 'D17' => 8, 'C18' => 'TOTAL'] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $change?->__invoke($sheet);
        $path = tempnam(sys_get_temp_dir(), 'timesheet-test-');
        $this->temporaryFiles[] = $path;
        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'historical.'.$extension, null, null, true);
    }

    public function test_only_super_admin_can_access_every_import_endpoint(): void
    {
        $s = $this->scenario();
        $id = $this->stage([$this->item($s)]);
        foreach (['employee', 'hod', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get(route('admin.timesheets.import.index'))->assertForbidden();
            $this->get(route('admin.timesheets.import.show', $id))->assertForbidden();
            $this->post(route('admin.timesheets.import.upload'))->assertForbidden();
            $this->post(route('admin.timesheets.import.update', [$id, 0]))->assertForbidden();
            $this->post(route('admin.timesheets.import.confirm', $id))->assertForbidden();
            $this->delete(route('admin.timesheets.import.cancel', $id))->assertForbidden();
        }
        $this->assertDatabaseCount('timesheets', 0);
    }

    public function test_upload_reads_multiple_workbooks_and_deletes_successful_and_failed_uploads(): void
    {
        $s = $this->scenario();
        $valid = $this->workbook($s, 'xlsm');
        $broken = UploadedFile::fake()->createWithContent('broken.xlsx', 'not a workbook');
        $paths = [$valid->getPathname(), $broken->getPathname()];
        $response = $this->post(route('admin.timesheets.import.upload'), ['workbooks' => [$valid, $broken]])->assertRedirect();
        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path);
        }
        $this->assertEmpty(File::glob(config('timesheet_import.directory').'/*.upload'));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('1 of 2 timesheets are ready');
        $this->get($response->headers->get('Location').'?sheet=1')->assertOk()->assertSee('not a readable Excel workbook');
        $this->assertDatabaseCount('timesheets', 0);
        $encrypted = File::get(File::glob(config('timesheet_import.directory').'/*.batch')[0]);
        $this->assertStringNotContainsString($s['worker']->employee_code, $encrypted);
    }

    public function test_invalid_extension_and_batch_limit_delete_php_temporary_files(): void
    {
        $this->scenario();
        $file = UploadedFile::fake()->createWithContent('bad.csv', 'invalid');
        $path = $file->getPathname();
        $this->post(route('admin.timesheets.import.upload'), ['workbooks' => [$file]])->assertSessionHasErrors('workbooks.0');
        $this->assertFileDoesNotExist($path);
        config(['timesheet_import.max_batch_kb' => 0]);
        $file = UploadedFile::fake()->createWithContent('too-large.xlsx', 'invalid');
        $path = $file->getPathname();
        $this->post(route('admin.timesheets.import.upload'), ['workbooks' => [$file]])->assertSessionHasErrors('workbooks');
        $this->assertFileDoesNotExist($path);
    }

    public function test_confirmation_is_atomic_with_history_and_preserves_unknown_approval_and_leave_balance(): void
    {
        $s = $this->scenario();
        LeavePlan::factory()->create(['user_id' => $s['worker']->id, 'department_id' => $s['department']->id, 'attendance_code' => 'L100', 'status' => 'approved', 'start_date' => '2026-05-11', 'end_date' => '2026-05-11']);
        $before = app(LeaveEntitlementService::class)->balanceFor($s['worker'], 2026);
        $item = $this->item($s, ['attendance_code' => 'L100', 'project_code' => '']);
        $id = $this->stage([$item]);
        $this->get(route('admin.timesheets.import.show', $id))->assertOk()->assertSee('Timesheet display preview');
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertRedirect();
        $sheet = Timesheet::sole();
        $this->assertSame('approved', $sheet->status);
        $this->assertNull($sheet->approved_by);
        $this->assertNull($sheet->approved_at);
        $this->assertNull($sheet->submitted_at);
        $this->assertEquals(8, $sheet->total_hours);
        $history = TimesheetStatusHistory::sole();
        $this->assertSame($s['admin']->id, $history->actor_id);
        $this->assertSame('timesheet_historical_imported', $history->action);
        $this->assertSame($id, $history->metadata['batch_id']);
        $this->assertSame('historical_import', $history->metadata['source']);
        $this->assertTrue($history->occurred_at->equalTo(now()));
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('leave_plans', 1);
        $this->app->forgetInstance(LeaveEntitlementService::class);
        $this->assertSame($before, app(LeaveEntitlementService::class)->balanceFor($s['worker'], 2026));
        Mail::assertNothingSent();
        $this->get(route('admin.timesheets.history', $sheet))->assertOk()->assertSee('Historical Timesheet Imported')->assertSee('historical.xlsx');
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('timesheets', 1);
        $this->assertDatabaseCount('timesheet_status_histories', 1);
        app(HistoricalTimesheetImportStore::class)->access($id, $s['admin']->id, function ($data) {
            $this->assertSame([], $data['items']);
            $this->assertSame('imported', $data['results'][0]['state']);
        });
    }

    public function test_preview_edits_set_historical_category_without_changing_current_assignment(): void
    {
        $s = $this->scenario(true);
        $s['project']->assignedUsers()->attach($s['worker']->id, ['manpower_category' => 'designer']);
        $item = $this->item($s);
        $id = $this->stage([$item]);
        $entries = $item['entries'];
        $entries[0]['manpower_category'] = 'engineer';
        $entries[0]['regular_hours'] = '7.25';
        $entries[0]['overtime_hours'] = '0.75';
        $entries[0]['remarks'] = '<script>historical edit</script>';
        $this->postJson(route('admin.timesheets.import.update', [$id, 0]), ['revision' => 1, 'entry_count' => 1, 'entries' => $entries])->assertRedirect();
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertConflict();
        $this->get(route('admin.timesheets.import.show', $id))->assertOk()->assertSee('&lt;script&gt;historical edit&lt;/script&gt;', false)->assertDontSee('<script>historical edit</script>', false);
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 2])->assertRedirect();
        $entry = Timesheet::sole()->entries()->sole();
        $this->assertSame('engineer', $entry->manpower_category_snapshot);
        $this->assertSame('reserved', $entry->allocation_bucket_snapshot);
        $this->assertEquals(7.25, $entry->regular_hours);
        $this->assertEquals(.75, $entry->overtime_hours);
        $this->assertSame('designer', $s['project']->assignedUsers()->find($s['worker']->id)->pivot->manpower_category);
        $history = TimesheetStatusHistory::sole();
        $this->assertNull($history->metadata['original_entries'][0]['manpower_category']);
        $this->assertSame('designer', $history->metadata['initial_preview_entries'][0]['manpower_category']);
        $this->assertSame('engineer', $history->metadata['imported_entries'][0]['manpower_category']);
        $this->assertTrue($history->metadata['preview_edited']);
    }

    public function test_missing_category_is_blocked_and_inactive_unassigned_projects_are_warnings_only(): void
    {
        $s = $this->scenario(true);
        $s['project']->update(['is_active' => false, 'timesheet_assignment_mode' => 'selected_users']);
        $item = $this->item($s);
        $imports = app(HistoricalTimesheetImportService::class);
        $review = $imports->preview([$item])[0];
        $this->assertSame('blocked', $review['state']);
        $this->assertStringContainsString('Manpower Category', implode(' ', $review['errors']));
        $this->assertCount(2, $review['warnings']);
        $item['entries'][0]['manpower_category'] = 'engineer';
        $this->assertSame('ready', $imports->preview([$item])[0]['state']);
        $this->assertSame('imported', $imports->commit([$item], 'test', $s['admin'])[0]['state']);
    }

    public function test_existing_and_duplicate_timesheets_are_skipped_without_overwrite(): void
    {
        $s = $this->scenario();
        $original = $this->submittedTimesheet($s['worker'], $s['period'], $s['project']);
        $before = $original->fresh()->toArray();
        $item = $this->item($s);
        $imports = app(HistoricalTimesheetImportService::class);
        $this->assertSame('conflict', $imports->preview([$item])[0]['state']);
        $this->assertSame('skipped', $imports->commit([$item], 'test', $s['admin'])[0]['state']);
        $this->assertSame($before, $original->fresh()->toArray());
        $this->assertDatabaseCount('timesheet_status_histories', 0);
        $original->update(['status' => 'voided']);
        $review = $imports->preview([$item, $item]);
        $this->assertSame(['ready', 'conflict'], array_column($review, 'state'));
        $results = $imports->commit([$item, $item], 'test', $s['admin']);
        $this->assertSame(['imported', 'skipped'], array_column($results, 'state'));
        $this->assertDatabaseCount('timesheets', 2);
    }

    public function test_allocations_are_checked_cumulatively_and_confirmation_rechecks_capacity(): void
    {
        $s = $this->scenario(true);
        $s['allocation']->update(['allocated_hours' => 10]);
        $other = $this->userWithRole('employee', ['department_id' => $s['department']->id]);
        $first = $this->item($s);
        $first['entries'][0]['manpower_category'] = 'engineer';
        $second = $first;
        $second['employee_code'] = $other->employee_code;
        $imports = app(HistoricalTimesheetImportService::class);
        $this->assertSame(['ready', 'blocked'], array_column($imports->preview([$first, $second]), 'state'));
        $id = $this->stage([$first]);
        $this->get(route('admin.timesheets.import.show', $id))->assertOk();
        $s['allocation']->update(['allocated_hours' => 7]);
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('timesheets', 0);
        $this->assertDatabaseCount('timesheet_status_histories', 0);
        $s['allocation']->update(['allocated_hours' => 20]);
        $this->assertSame(['imported', 'imported'], array_column($imports->commit([$first, $second], 'retry', $s['admin']), 'state'));
    }

    public function test_reserved_and_shared_limits_cannot_be_bypassed_by_historical_categories(): void
    {
        $s = $this->scenario(true);
        $s['allocation']->update(['allocated_hours' => 10]);
        $s['allocation']->manpowerCategoryAllocations()->where('manpower_category', 'engineer')->update(['allocated_hours' => 5]);
        $item = $this->item($s);
        foreach (['engineer', 'designer'] as $category) {
            $item['entries'][0]['manpower_category'] = $category;
            $review = app(HistoricalTimesheetImportService::class)->preview([$item])[0];
            $this->assertSame('blocked', $review['state']);
            $this->assertStringContainsString('allocated hours', implode(' ', $review['errors']));
        }
        $item['entries'][0]['regular_hours'] = 5;
        $item['entries'][0]['manpower_category'] = 'engineer';
        $this->assertSame('imported', app(HistoricalTimesheetImportService::class)->commit([$item], 'exact', $s['admin'])[0]['state']);
        $this->assertSame('reserved', Timesheet::sole()->entries()->sole()->allocation_bucket_snapshot);
    }

    public function test_history_failure_rolls_back_that_timesheet_and_batch_continues(): void
    {
        $s = $this->scenario();
        $other = $this->userWithRole('employee', ['department_id' => $s['department']->id]);
        $first = $this->item($s);
        $second = $first;
        $second['employee_code'] = $other->employee_code;
        TimesheetStatusHistory::creating(function ($history) use ($s) {
            if ($history->timesheet->user_id === $s['worker']->id) {
                throw new \RuntimeException('Simulated history write failure');
            }
        });
        $results = app(HistoricalTimesheetImportService::class)->commit([$first, $second], 'test', $s['admin']);
        $this->assertSame(['failed', 'imported'], array_column($results, 'state'));
        $this->assertDatabaseCount('timesheets', 1);
        $this->assertDatabaseCount('timesheet_entries', 1);
        $this->assertDatabaseCount('timesheet_status_histories', 1);
        $this->assertSame($other->id, Timesheet::sole()->user_id);
    }

    public function test_invalid_references_dates_hours_and_leave_combinations_block_only_affected_timesheet(): void
    {
        $s = $this->scenario();
        $imports = app(HistoricalTimesheetImportService::class);
        $valid = $this->item($s);
        $cases = [
            ['project_code' => 'UNKNOWN'], ['department_id' => 99999], ['work_date' => '2026-05-18'],
            ['regular_hours' => -1], ['regular_hours' => 25], ['regular_hours' => 1.234],
            ['regular_hours' => 'NaN'], ['regular_hours' => 20, 'overtime_hours' => 5],
            ['attendance_code' => 'UNKNOWN'], ['attendance_code' => 'L100'],
            ['attendance_code' => 'L100', 'project_code' => '', 'overtime_hours' => 1],
            ['regular_hours' => 0, 'overtime_hours' => 0], ['manpower_category' => 'UNKNOWN'],
        ];
        foreach ($cases as $overrides) {
            $item = $valid;
            $item['entries'][0] = array_replace($item['entries'][0], $overrides);
            $this->assertSame('blocked', $imports->preview([$item])[0]['state'], json_encode($overrides));
        }
        $unknown = $valid;
        $unknown['employee_code'] = 'UNKNOWN';
        $this->assertSame('blocked', $imports->preview([$unknown])[0]['state']);
        $s['period']->delete();
        $this->assertSame('blocked', $imports->preview([$valid])[0]['state']);
        $this->assertDatabaseCount('timesheets', 0);
    }

    public function test_combined_hours_across_entries_are_limited_per_day(): void
    {
        $s = $this->scenario();
        $item = $this->item($s, ['regular_hours' => 13]);
        $item['entries'][] = $item['entries'][0];
        $this->assertSame('blocked', app(HistoricalTimesheetImportService::class)->preview([$item])[0]['state']);
    }

    public function test_zero_hour_entries_are_excluded_even_when_their_other_fields_need_correction(): void
    {
        $s = $this->scenario();
        $item = $this->item($s);
        $item['entries'][] = array_replace($item['entries'][0], ['attendance_code' => 'UNKNOWN', 'project_code' => 'UNKNOWN', 'department_id' => null, 'regular_hours' => '0', 'overtime_hours' => '0']);
        $results = app(HistoricalTimesheetImportService::class)->commit([$item], 'zero-hours', $s['admin']);
        $this->assertSame('imported', $results[0]['state']);
        $this->assertDatabaseCount('timesheet_entries', 1);
    }

    public function test_extracted_preview_size_limit_fails_cleanly_and_removes_workbook(): void
    {
        $s = $this->scenario();
        config(['timesheet_import.max_preview_kb' => 0]);
        $file = $this->workbook($s);
        $path = $file->getPathname();
        $response = $this->post(route('admin.timesheets.import.upload'), ['workbooks' => [$file]])->assertRedirect();
        $this->assertFileDoesNotExist($path);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('extracted preview exceeds the batch limit');
        $this->assertEmpty(File::glob(config('timesheet_import.directory').'/*.upload'));
    }

    public function test_corrupt_expired_preview_does_not_prevent_cleanup_of_other_files(): void
    {
        $this->scenario();
        $directory = app(HistoricalTimesheetImportStore::class)->directory();
        File::put($directory.'/corrupt.batch', 'invalid encrypted data');
        File::put($directory.'/abandoned.upload', 'abandoned data');
        foreach (['corrupt.batch', 'abandoned.upload'] as $file) {
            touch($directory.'/'.$file, now()->subHours(2)->timestamp);
        }
        $this->artisan('timesheets:prune-imports')->assertSuccessful();
        $this->assertFileDoesNotExist($directory.'/corrupt.batch');
        $this->assertFileDoesNotExist($directory.'/abandoned.upload');
    }

    public function test_zip_member_limit_and_oversized_cell_are_rejected(): void
    {
        $s = $this->scenario();
        $file = $this->workbook($s, 'xlsx', fn ($sheet) => $sheet->setCellValue('X17', str_repeat('x', 2001)));
        try {
            app(HistoricalTimesheetWorkbookReader::class)->read($file->getPathname());
            $this->fail('An oversized cell must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('2,000 character limit', $exception->getMessage());
        }
        $path = tempnam(sys_get_temp_dir(), 'timesheet-zip-test-');
        $this->temporaryFiles[] = $path;
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        for ($i = 0; $i < 2001; $i++) {
            $zip->addFromString('member-'.$i, 'x');
        }
        $zip->close();
        $this->expectExceptionMessage('too many embedded files');
        app(HistoricalTimesheetWorkbookReader::class)->read($path);
    }

    public function test_missing_allocation_is_blocked_and_later_conflict_is_rechecked(): void
    {
        $s = $this->scenario();
        $item = $this->item($s);
        $id = $this->stage([$item]);
        $this->get(route('admin.timesheets.import.show', $id))->assertOk();
        $original = $this->submittedTimesheet($s['worker'], $s['period'], $s['project']);
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('timesheets', 1);
        $this->assertDatabaseCount('timesheet_status_histories', 0);
        $original->delete();
        $s['allocation']->delete();
        $this->assertSame('blocked', app(HistoricalTimesheetImportService::class)->preview([$item])[0]['state']);
    }

    public function test_historical_allocation_validation_never_falls_back_to_an_unlimited_project(): void
    {
        $s = $this->scenario();
        $s['allocation']->delete();
        $entries = [['project_id' => $s['project']->id, 'department_id' => $s['department']->id, 'regular_hours' => 8, 'overtime_hours' => 0]];
        $allocations = app(TimesheetAllocationService::class);
        $this->assertSame([['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null]], $allocations->validateSubmission($s['worker'], $entries));
        $this->expectException(ValidationException::class);
        $allocations->validateHistoricalImport($s['worker'], $entries);
    }

    public function test_batches_are_owner_scoped_expire_and_cancellation_removes_rows(): void
    {
        $s = $this->scenario();
        $id = $this->stage([$this->item($s)]);
        $other = $this->userWithRole('super_admin');
        $this->actingAs($other)->get(route('admin.timesheets.import.show', $id))->assertForbidden();
        $this->actingAs($s['admin']);
        $this->delete(route('admin.timesheets.import.cancel', $id))->assertRedirect();
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('timesheets', 0);
        $expired = $this->stage([$this->item($s)]);
        $this->travel(61)->minutes();
        $this->get(route('admin.timesheets.import.show', $expired))->assertStatus(410);
        $this->assertFileDoesNotExist(config('timesheet_import.directory').'/'.$expired.'.batch');
    }

    public function test_stale_incomplete_or_forged_preview_edits_are_rejected(): void
    {
        $s = $this->scenario();
        $item = $this->item($s);
        $id = $this->stage([$item]);
        $payload = ['revision' => 1, 'entry_count' => 2, 'entries' => $item['entries']];
        $this->postJson(route('admin.timesheets.import.update', [$id, 0]), $payload)->assertUnprocessable();
        $payload['entry_count'] = 1;
        $payload['entries'][0]['regular_hours'] = '8';
        $payload['entries'][0]['overtime_hours'] = '0';
        $payload['entries'][0]['project_id'] = 123;
        $this->postJson(route('admin.timesheets.import.update', [$id, 0]), $payload)->assertUnprocessable();
        unset($payload['entries'][0]['project_id']);
        $payload['revision'] = 99;
        $this->postJson(route('admin.timesheets.import.update', [$id, 0]), $payload)->assertConflict();
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('timesheets', 0);
    }

    public function test_invalid_form_input_can_be_redisplayed_and_optional_entry_fields_are_normalised(): void
    {
        $s = $this->scenario();
        $id = $this->stage([$this->item($s)]);
        $url = route('admin.timesheets.import.show', $id);
        $this->from($url)->post(route('admin.timesheets.import.update', [$id, 0]), [
            'revision' => 1, 'entry_count' => 1, 'entries' => [['regular_hours' => ['invalid'], 'remarks' => ['invalid']]],
        ])->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('The preview could not be saved.');
        $this->flushSession();
        $this->postJson(route('admin.timesheets.import.update', [$id, 0]), [
            'revision' => 1, 'entry_count' => 1, 'entries' => [['work_date' => '2026-05-11', 'attendance_code' => 'L100', 'department_id' => $s['department']->id, 'regular_hours' => 8, 'overtime_hours' => 0]],
        ])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('1 of 1 timesheets are ready');
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 2])->assertRedirect();
        $this->assertDatabaseCount('timesheets', 1);
    }

    public function test_pruning_removes_expired_previews_and_abandoned_uploads_only(): void
    {
        $s = $this->scenario();
        $id = $this->stage([$this->item($s)]);
        $directory = config('timesheet_import.directory');
        File::put($directory.'/abandoned.upload', 'temporary workbook');
        touch($directory.'/abandoned.upload', now()->subHours(2)->timestamp);
        File::put($directory.'/recent.upload', 'in progress');
        $this->artisan('timesheets:prune-imports')->assertSuccessful();
        $this->assertFileDoesNotExist($directory.'/abandoned.upload');
        $this->assertFileExists($directory.'/recent.upload');
        $this->assertFileExists($directory.'/'.$id.'.batch');
        $this->travel(61)->minutes();
        $this->artisan('timesheets:prune-imports')->assertSuccessful();
        $this->assertFileDoesNotExist($directory.'/'.$id.'.batch');
    }

    public function test_process_lock_blocks_concurrent_confirmation_and_pruning(): void
    {
        $s = $this->scenario();
        $id = $this->stage([$this->item($s)]);
        $path = config('timesheet_import.directory').'/'.$id.'.batch';
        $handle = fopen($path, 'r+');
        flock($handle, LOCK_EX);
        try {
            $this->get(route('admin.timesheets.import.show', $id))->assertConflict();
            $this->travel(61)->minutes();
            $this->artisan('timesheets:prune-imports')->assertSuccessful();
            $this->assertFileExists($path);
        } finally {
            fclose($handle);
        }
        $this->artisan('timesheets:prune-imports')->assertSuccessful();
        $this->assertFileDoesNotExist($path);
    }

    public function test_changed_preview_eligibility_invalidates_confirmation_in_an_older_tab(): void
    {
        $s = $this->scenario();
        $id = $this->stage([$this->item($s)]);
        $url = route('admin.timesheets.import.show', $id);
        $this->get($url)->assertOk()->assertSee('1 of 1 timesheets are ready');
        $s['allocation']->update(['allocated_hours' => 0]);
        $this->get($url)->assertOk()->assertSee('0 of 1 timesheets are ready');
        $s['allocation']->update(['allocated_hours' => 100]);
        $this->get($url)->assertOk()->assertSee('1 of 1 timesheets are ready');
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('timesheets', 0);
        $this->post(route('admin.timesheets.import.confirm', $id), ['revision' => 3])->assertRedirect();
        $this->assertDatabaseCount('timesheets', 1);
    }

    public function test_workbook_reader_handles_weekend_hours_and_variable_total_row(): void
    {
        $s = $this->scenario();
        $file = $this->workbook($s, 'xlsm', function ($sheet) {
            $sheet->setCellValue('C18', 'O100')->setCellValue('B18', 'P-EXTRA')->setCellValue('O18', 4)->setCellValue('P18', 3)->setCellValue('C19', 'TOTAL');
        });
        $read = app(HistoricalTimesheetWorkbookReader::class)->read($file->getPathname());
        $this->assertCount(3, $read['entries']);
        $this->assertSame('2026-05-16', $read['entries'][1]['work_date']);
        $this->assertSame('2026-05-17', $read['entries'][2]['work_date']);
        $this->assertEquals(0, $read['entries'][2]['overtime_hours']);
    }

    public function test_workbook_reader_rejects_wrong_template_missing_identity_and_missing_saved_formula_values(): void
    {
        $s = $this->scenario();
        $changes = [
            fn ($sheet) => $sheet->setCellValue('B15', 'wrong template'),
            fn ($sheet) => $sheet->setCellValue('Q8', ''),
            fn ($sheet) => $sheet->setCellValue('C5', Date::PHPToExcel(Carbon::parse('2026-05-12'))),
            fn ($sheet) => $sheet->setCellValueExplicit('Q8', '=MISSING_EXTERNAL_VALUE()', DataType::TYPE_FORMULA),
        ];
        foreach ($changes as $change) {
            $file = $this->workbook($s, 'xlsx', $change);
            try {
                app(HistoricalTimesheetWorkbookReader::class)->read($file->getPathname());
                $this->fail('The malformed workbook should not be read.');
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }
}
