<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Services\TimesheetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class SuperAdminProjectReviewTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_legacy_filter_matches_recorded_categories_and_scopes_packet_entries_and_totals(): void
    {
        $manager = $this->userWithRole('employee');
        $project = $this->project(['project_manager_id' => $manager->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project);
        $original = $sheet->entries()->first();
        $ids = collect([$original->id]);
        foreach ([['', 2, 1], ['old_category', 3, 2], ['engineer', 4, 0], ['designer', 5, 0]] as [$category, $regular, $ot]) {
            $entry = $original->replicate();
            $entry->fill(['manpower_category_snapshot' => $category, 'regular_hours' => $regular, 'overtime_hours' => $ot])->save();
            if (! in_array($category, ['engineer', 'designer'], true)) {
                $ids->push($entry->id);
            }
        }
        // Neither other-project nor other-department legacy entries may leak into packets.
        foreach ([['project_id' => $this->project()->id], ['department_id' => $this->department()->id]] as $attributes) {
            $entry = $original->replicate();
            $entry->fill($attributes + ['regular_hours' => 20])->save();
        }
        // The person's current assignment does not classify historical entries.
        $project->assignedUsers()->attach($worker, ['manpower_category' => 'engineer']);
        $filters = [
            'project' => $project, 'review_category' => 'legacy', 'review_department_id' => $worker->department_id,
            'review_employee_id' => $worker->id, 'review_week' => 20, 'review_year' => 2026,
            'review_status' => 'submitted', 'date_from' => '2026-05-11', 'date_to' => '2026-05-17',
        ];
        foreach ([$this->userWithRole('super_admin'), $manager] as $viewer) {
            $response = $this->actingAs($viewer)->get(route('projects.utilization', $filters))->assertOk()
                ->assertSee('Legacy / Unclassified only')->assertSee('Showing only Legacy / Unclassified entries.');
            $packet = $response->viewData('reviewTimesheets')->getCollection()->sole();
            $this->assertSame($ids->all(), $packet->entries->pluck('id')->all());
            $this->assertEquals(3, $packet->project_entries_count);
            $this->assertEquals(13, $packet->project_regular_hours);
            $this->assertEquals(3, $packet->project_overtime_hours);
            // Docket filtering must not hide consumption from the department ledger.
            $this->assertEquals(25, $response->viewData('allocations')->firstWhere('department_id', $worker->department_id)->pending_hours);
        }
        $original->update(['manpower_category_snapshot' => 'senior_engineer']);
        $sheet->entries()->whereIn('id', $ids->skip(1))->update(['manpower_category_snapshot' => 'designer']);
        $this->get(route('projects.utilization', $filters))->assertOk()
            ->assertViewHas('reviewTimesheets', fn ($packets) => $packets->total() === 0);
        $this->get(route('projects.utilization', array_diff_key($filters, ['review_category' => true])))
            ->assertOk()->assertViewHas('reviewTimesheets', fn ($packets) => $packets->total() === 1);
    }

    public function test_each_manpower_category_filters_recorded_entries_and_totals_independently_of_current_assignment(): void
    {
        $project = $this->project();
        $worker = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project);
        $original = $sheet->entries()->first();
        $project->assignedUsers()->attach($worker, ['manpower_category' => 'engineer']);
        $categories = config('manpower_categories.labels');
        $entries = [];
        foreach ($categories as $value => $label) {
            $entry = $original->replicate();
            $entry->fill(['manpower_category_snapshot' => $value, 'regular_hours' => 2, 'overtime_hours' => 1])->save();
            $entries[$value] = $entry;
        }
        $this->actingAs($this->userWithRole('super_admin'));
        foreach ($categories as $value => $label) {
            $response = $this->get(route('projects.utilization', [
                'project' => $project, 'review_category' => $value,
                'review_department_id' => $worker->department_id, 'review_employee_id' => $worker->id,
                'review_status' => 'submitted', 'date_from' => '2026-05-11', 'date_to' => '2026-05-17',
            ]))->assertOk()->assertSee('Showing only '.$label.' entries.')
                ->assertSee('value="'.$value.'" selected', false);
            foreach ($categories as $optionLabel) {
                $response->assertSee($optionLabel);
            }
            $packet = $response->viewData('reviewTimesheets')->getCollection()->sole();
            $this->assertSame([$entries[$value]->id], $packet->entries->pluck('id')->all());
            $this->assertEquals(1, $packet->project_entries_count);
            $this->assertEquals(2, $packet->project_regular_hours);
            $this->assertEquals(1, $packet->project_overtime_hours);
        }
        $this->get(route('projects.utilization', ['project' => $project, 'review_category' => '']))
            ->assertOk()->assertViewHas('reviewTimesheets', fn ($packets) => $packets->getCollection()->sole()->entries->count() === 5);
        $entries['designer']->update(['manpower_category_snapshot' => 'engineer']);
        $this->get(route('projects.utilization', ['project' => $project, 'review_category' => 'designer']))
            ->assertOk()->assertViewHas('reviewTimesheets', fn ($packets) => $packets->total() === 0);
    }

    public function test_legacy_filter_rejects_invalid_values_and_preserves_project_access_rules(): void
    {
        $project = $this->project();
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('projects.utilization', ['project' => $project, 'review_category' => 'unknown']))
            ->assertSessionHasErrors('review_category');
        $this->actingAs($this->userWithRole('hod'))
            ->get(route('projects.utilization', ['project' => $project, 'review_category' => 'legacy']))->assertForbidden();
    }

    public function test_super_admin_reviews_any_project_with_project_scoped_entries_and_filters(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();
        $period = $this->openPeriod();
        $otherProject = $this->project();

        foreach ([true, false] as $active) {
            $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
            $otherWorker = $this->userWithRole('employee', ['department_id' => $department->id]);
            $project = $this->project(['is_active' => $active, 'project_manager_id' => $this->userWithRole('employee')->id]);
            $sheet = $this->submittedTimesheet($worker, $period, $project);
            $sheet->entries()->first()->update(['description' => 'Visible selected project work']);
            $sheet->entries()->create([
                'project_id' => $otherProject->id, 'department_id' => $department->id,
                'work_date' => $period->start_date, 'day_name' => 'Monday', 'attendance_code' => 'O100',
                'regular_hours' => 2, 'overtime_hours' => 0, 'description' => 'Private other project entry',
            ]);
            $otherSheet = $this->submittedTimesheet($otherWorker, $period, $project, ['status' => Timesheet::STATUS_APPROVED]);
            $otherSheet->entries()->first()->update(['description' => 'Another employee approved work']);

            $this->actingAs($superAdmin)->get(route('projects.utilization', $project))
                ->assertOk()->assertSee('Entry review docket')->assertSee('Visible selected project work')
                ->assertSee('Another employee approved work')->assertDontSee('Private other project entry')
                ->assertSee('Send correction request')->assertSee('entire weekly timesheet');

            $this->get(route('projects.utilization', [
                'project' => $project, 'review_employee_id' => $worker->id,
                'review_status' => 'submitted', 'review_week' => 20, 'review_year' => 2026,
                'date_from' => '2026-05-11', 'date_to' => '2026-05-17',
            ]))->assertOk()->assertSee('Visible selected project work')->assertDontSee('Another employee approved work');
            $this->get(route('projects.utilization', ['project' => $project, 'date_from' => '2026-06-01']))
                ->assertOk()->assertDontSee('Visible selected project work');
            $this->get(route('projects.utilization', ['project' => $project, 'review_status' => 'approved']))
                ->assertOk()->assertSee('Another employee approved work')->assertDontSee('Visible selected project work');
            $this->get(route('projects.utilization', ['project' => $project, 'date_from' => '2026-06-01', 'date_to' => '2026-05-01']))
                ->assertSessionHasErrors('date_to');
            $this->actingAs($this->userWithRole('admin'))->get(route('projects.utilization', $project))
                ->assertOk()->assertDontSee('Entry review docket')->assertDontSee('Send correction request');
            $this->actingAs($this->userWithRole('employee'))->get(route('projects.utilization', $project))->assertForbidden();
        }
    }

    public function test_super_admin_and_manager_can_page_through_all_matching_timesheets(): void
    {
        $manager = $this->userWithRole('employee');
        $superAdmin = $this->userWithRole('super_admin');
        $worker = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $project = $this->project(['project_manager_id' => $manager->id]);
        $sheets = collect();
        for ($week = 20; $week < 32; $week++) {
            $start = Carbon::parse('2026-05-11')->addWeeks($week - 20);
            $period = $this->openPeriod([
                'week_number' => $week, 'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(6)->toDateString(),
            ]);
            $sheet = $this->submittedTimesheet($worker, $period, $project, ['submitted_at' => $start]);
            $sheets->push($sheet);
        }
        $filters = [
            'project' => $project, 'review_employee_id' => $worker->id,
            'review_status' => 'submitted', 'review_year' => 2026, 'review_category' => 'legacy',
            'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
            'list' => ['search' => 'kept project context'],
        ];
        foreach ([$superAdmin, $manager] as $viewer) {
            $response = $this->actingAs($viewer)->get(route('projects.utilization', $filters));
            $response->assertOk()->assertSee('Showing 1 to 10 of 12 timesheets')
                ->assertSee('Timesheets pagination')->assertSee('Page 1 of 2')
                ->assertSee('id="review-packet-'.$sheets->last()->id.'"', false)
                ->assertDontSee('id="review-packet-'.$sheets->first()->id.'"', false);
            $this->assertSame(2, substr_count($response->getContent(), 'aria-label="Timesheets pagination"'));
            $paginator = $response->viewData('reviewTimesheets');
            $nextUrl = $paginator->nextPageUrl();
            parse_str(parse_url($nextUrl, PHP_URL_QUERY), $query);
            $this->assertSame('2', $query['review_page']);
            $this->assertSame((string) $worker->id, $query['review_employee_id']);
            $this->assertSame('submitted', $query['review_status']);
            $this->assertSame('legacy', $query['review_category']);
            $this->assertSame('2026', $query['review_year']);
            $this->assertSame('2026-01-01', $query['date_from']);
            $this->assertSame('2026-12-31', $query['date_to']);
            $this->assertSame('kept project context', $query['list']['search']);
            $this->assertSame('entry-review-docket', parse_url($nextUrl, PHP_URL_FRAGMENT));
            $this->get($nextUrl)->assertOk()->assertSee('Showing 11 to 12 of 12 timesheets')
                ->assertSee('Page 2 of 2')->assertSee('id="review-packet-'.$sheets->first()->id.'"', false)
                ->assertDontSee('id="review-packet-'.$sheets->last()->id.'"', false);
            $this->get(route('projects.utilization', $filters + ['review_week' => 20]))
                ->assertOk()->assertSee('Showing 1 to 1 of 1 timesheet')->assertDontSee('Timesheets pagination');
            $this->get(route('projects.utilization', array_replace($filters, ['review_year' => 2025])))
                ->assertOk()->assertSee('Showing 0 to 0 of 0 timesheets')->assertDontSee('Timesheets pagination');
        }
    }

    public function test_docket_orders_by_latest_year_and_week_then_employee_name_with_and_without_filters(): void
    {
        $viewer = $this->userWithRole('super_admin');
        $department = $this->department();
        $project = $this->project();
        $workers = collect(['Zara Adams', 'michael Brown', 'Alice Smith'])->map(
            fn ($name) => $this->userWithRole('employee', ['name' => $name, 'department_id' => $department->id])
        );
        $sheets = collect();
        foreach ([
            [2027, 1, '2027-01-04'],
            [2026, 53, '2026-12-28'],
            [2026, 52, '2026-12-21'],
            [2026, 51, '2026-12-14'],
        ] as [$year, $week, $start]) {
            $period = $this->openPeriod([
                'year' => $year, 'week_number' => $week, 'start_date' => $start,
                'end_date' => Carbon::parse($start)->addDays(6)->toDateString(),
            ]);
            foreach ($workers as $index => $worker) {
                $sheets->push($this->submittedTimesheet($worker, $period, $project, [
                    'status' => $index === 1 ? Timesheet::STATUS_APPROVED : Timesheet::STATUS_SUBMITTED,
                    // Older weeks were submitted more recently.
                    'submitted_at' => Carbon::parse('2027-02-01')->addDays($sheets->count()),
                ]));
            }
        }
        $expected = $sheets->chunk(3)->flatMap(fn ($group) => $group->reverse()->values())->values();
        foreach ([
            [[], $expected],
            [['review_status' => 'submitted'], $expected->where('status', Timesheet::STATUS_SUBMITTED)],
            [['review_year' => 2026], $expected->filter(fn ($sheet) => $sheet->period->year === 2026)],
            [['review_week' => 53, 'review_year' => 2026], $expected->slice(3, 3)],
            [['review_employee_id' => $workers->first()->id], $expected->where('user_id', $workers->first()->id)],
            [['date_from' => '2026-12-21', 'date_to' => '2027-01-10'], $expected->take(9)],
        ] as [$filters, $matching]) {
            $ids = $matching->pluck('id')->values();
            foreach ($ids->chunk(10) as $page => $pageIds) {
                $response = $this->actingAs($viewer)->get(route('projects.utilization', [
                    'project' => $project, 'review_page' => $page + 1,
                ] + $filters))->assertOk();
                $paginator = $response->viewData('reviewTimesheets');
                $this->assertSame($ids->count(), $paginator->total());
                $this->assertSame($pageIds->values()->all(), $paginator->getCollection()->pluck('id')->all());
            }
        }
    }

    public function test_super_admin_request_notifies_hods_and_acceptance_notifies_employee_and_requester(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $secondHod = $this->userWithRole('hod');
        $inactiveHod = $this->userWithRole('hod', ['is_active' => false]);
        $hiddenHod = $this->userWithRole('hod');
        $unrelatedHod = $this->userWithRole('hod');
        $department->update(['hod_id' => $hod->id]);
        $department->hods()->attach([$hod->id, $secondHod->id, $inactiveHod->id, $hiddenHod->id]);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $hiddenHod->hodVisibilityExcludedSubmitters()->attach($worker);
        $superAdmin = $this->userWithRole('super_admin');
        $project = $this->project(['project_manager_id' => $this->userWithRole('employee')->id]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project);

        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these project hours.',
        ])->assertRedirect()->assertSessionHas('success');
        $request = TimesheetCorrectionRequest::sole();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        $this->assertSame($superAdmin->id, $request->requested_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_correction_requested', 'user_id' => $superAdmin->id, 'auditable_id' => $request->id]);
        foreach ([$hod, $secondHod] as $recipient) {
            Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($recipient->email)
                && $mail->headline === 'Timesheet correction request needs review'
                && $mail->actionUrl === route('hod.timesheets.show', $sheet)
                && $mail->comment === 'Please verify these project hours.');
        }
        Mail::assertQueuedCount(2);
        foreach ([$inactiveHod, $hiddenHod, $unrelatedHod, $worker] as $recipient) {
            Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($recipient->email));
        }
        $this->actingAs($hod)->get(route('hod.timesheets.show', $sheet))->assertOk()->assertSee($request->comment);
        $this->post(route('hod.timesheets.approve', $sheet))->assertStatus(422);
        $this->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$request->id => 'accepted']])->assertRedirect();
        $this->assertSame(Timesheet::STATUS_REJECTED, $sheet->fresh()->status);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($worker->email) && $mail->headline === 'Timesheet rejected');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email)
            && $mail->headline === 'Correction request accepted' && $mail->actionUrl === route('projects.utilization', $project));
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_correction_accepted', 'user_id' => $hod->id, 'auditable_id' => $request->id]);
        $this->actingAs($superAdmin)->get(route('projects.utilization', $project))->assertOk()->assertSee('Accepted');
    }

    public function test_approved_hours_stay_reserved_until_hod_accepts_then_employee_can_resubmit(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $otherWorker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $superAdmin = $this->userWithRole('super_admin');
        $project = $this->project(['start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 8]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $project, ['status' => Timesheet::STATUS_APPROVED]);
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these approved hours.',
        ])->assertRedirect();
        $request = TimesheetCorrectionRequest::sole();
        $this->assertSame(Timesheet::STATUS_APPROVED, $sheet->fresh()->status);
        try {
            DB::transaction(fn () => app(TimesheetAllocationService::class)->validateSubmission($otherWorker, $this->validEntries($project)));
            $this->fail('Open correction requests must not release reserved hours.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('allocated hours', implode(' ', $exception->validator->errors()->all()));
        }
        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$request->id => 'accepted']])->assertRedirect();
        $this->assertSame(Timesheet::STATUS_RECALLED, $sheet->fresh()->status);
        $snapshots = DB::transaction(fn () => app(TimesheetAllocationService::class)->validateSubmission($otherWorker, $this->validEntries($project)));
        $this->assertNotEmpty($snapshots);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($worker->email) && $mail->headline === 'Approved timesheet recalled');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email) && $mail->headline === 'Correction request accepted');
        $this->actingAs($worker)->put(route('employee.timesheets.update', $sheet), [
            'timesheet_period_id' => $sheet->timesheet_period_id, 'submit' => '1',
            'entries' => $this->validEntries($project, ['2026-05-11' => ['regular_hours' => 4]]),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        $this->assertEquals(4, $sheet->fresh()->total_hours);
    }

    public function test_super_admin_hod_timesheet_request_follows_admin_notification_and_resolution_rules(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->hods()->attach($hod);
        $admin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $optedOut = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => false]);
        $excluded = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $excluded->adminNotificationExcludedHods()->attach($hod);
        $superAdmin = $this->userWithRole('super_admin', ['receives_hod_timesheet_submission_emails' => true]);
        $sheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), [
            'entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please check the HOD project hours.',
        ])->assertRedirect();
        $request = TimesheetCorrectionRequest::sole();
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->actionUrl === route('admin.timesheets.show', $sheet));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email) && $mail->headline === 'Timesheet correction request needs review');
        foreach ([$hod, $optedOut, $excluded] as $recipient) {
            Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($recipient->email));
        }
        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$request->id => 'accepted']])->assertForbidden();
        $this->actingAs($admin)->post(route('timesheet-corrections.resolve', $sheet), [
            'decisions' => [$request->id => 'dismissed'], 'dismissal_comments' => [$request->id => 'These hours are supported by the project records.'],
        ])->assertRedirect();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email) && $mail->headline === 'Correction request dismissed');
    }

    public function test_invalid_or_duplicate_requests_do_not_create_requests_or_send_extra_mail(): void
    {
        Mail::fake();
        $department = $this->department();
        $department->hods()->attach($this->userWithRole('hod'));
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $sheet = $this->submittedTimesheet($worker, $period, $project);
        $otherSheet = $this->submittedTimesheet($this->userWithRole('employee', ['department_id' => $department->id]), $period, $project);
        $entryId = $sheet->entries()->first()->id;
        $otherId = $otherSheet->entries()->first()->id;
        $this->actingAs($this->userWithRole('super_admin'));
        foreach ([
            ['entry_ids' => [], 'comment' => 'Please check.'],
            ['entry_ids' => [$entryId], 'comment' => ''],
            ['entry_ids' => [$entryId, $entryId], 'comment' => 'Please check.'],
            ['entry_ids' => [999999], 'comment' => 'Please check.'],
            ['entry_ids' => [$entryId, $otherId], 'comment' => 'Please check.'],
        ] as $payload) {
            $this->post(route('timesheet-corrections.store'), $payload)->assertSessionHasErrors();
        }
        foreach ([Timesheet::STATUS_DRAFT, Timesheet::STATUS_REJECTED, Timesheet::STATUS_RECALLED, Timesheet::STATUS_VOIDED] as $status) {
            $sheet->update(['status' => $status]);
            $this->post(route('timesheet-corrections.store'), ['entry_ids' => [$entryId], 'comment' => 'Please check.'])->assertSessionHasErrors('entry_ids');
        }
        $this->assertDatabaseCount('timesheet_correction_requests', 0);
        Mail::assertNothingQueued();
        $sheet->update(['status' => Timesheet::STATUS_SUBMITTED]);
        $payload = ['entry_ids' => [$entryId], 'comment' => 'Please verify these hours.'];
        $this->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $this->post(route('timesheet-corrections.store'), $payload)->assertSessionHasErrors('entry_ids');
        $this->assertDatabaseCount('timesheet_correction_requests', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_only_requester_can_withdraw_and_resolved_or_changed_timesheets_reject_stale_actions(): void
    {
        Mail::fake();
        $department = $this->department();
        $hod = $this->userWithRole('hod');
        $department->hods()->attach($hod);
        $worker = $this->userWithRole('employee', ['department_id' => $department->id]);
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $this->project());
        $superAdmin = $this->userWithRole('super_admin');
        $payload = ['entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these hours.'];
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $request = TimesheetCorrectionRequest::sole();
        $this->actingAs($this->userWithRole('super_admin'))->post(route('timesheet-corrections.withdraw', $request))->assertForbidden();
        $this->actingAs($superAdmin)->post(route('timesheet-corrections.withdraw', $request))->assertRedirect();
        $this->assertSame(TimesheetCorrectionRequest::STATUS_WITHDRAWN, $request->fresh()->status);
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $sheet->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timesheet_correction_withdrawn', 'user_id' => $superAdmin->id, 'auditable_id' => $request->id]);
        $this->post(route('timesheet-corrections.withdraw', $request))->assertStatus(422);
        $this->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $newRequest = TimesheetCorrectionRequest::latest('id')->first();
        $sheet->update(['status' => Timesheet::STATUS_RECALLED]);
        $this->actingAs($hod)->post(route('timesheet-corrections.resolve', $sheet), ['decisions' => [$newRequest->id => 'accepted']])->assertStatus(422);
        $this->assertSame(TimesheetCorrectionRequest::STATUS_SUPERSEDED, $newRequest->fresh()->status);
    }

    public function test_admins_and_unrelated_managers_cannot_raise_or_withdraw_project_requests(): void
    {
        Mail::fake();
        $worker = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $manager = $this->userWithRole('employee');
        $sheet = $this->submittedTimesheet($worker, $this->openPeriod(), $this->project(['project_manager_id' => $manager->id]));
        $payload = ['entry_ids' => [$sheet->entries()->first()->id], 'comment' => 'Please verify these hours.'];
        foreach (['admin', 'employee', 'hod'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('timesheet-corrections.store'), $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('timesheet_correction_requests', 0);
        Mail::assertNothingQueued();
        $this->actingAs($manager)->post(route('timesheet-corrections.store'), $payload)->assertRedirect();
        $this->actingAs($this->userWithRole('admin'))->post(route('timesheet-corrections.withdraw', TimesheetCorrectionRequest::sole()))->assertForbidden();
    }
}
