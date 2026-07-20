<?php

namespace Tests\Feature;

use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Services\AdminHodCorrectionRequestQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AdminHodCorrectionQueueTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_admin_queue_only_shows_eligible_open_hod_requests_while_super_admin_sees_all(): void
    {
        [$admin, $superAdmin, $eligibleHod, $excludedHod, $employee, $eligibleRequest] = $this->queueScenario();

        $query = app(AdminHodCorrectionRequestQuery::class);
        $this->assertSame(1, $query->countFor($admin));
        $this->assertSame(2, $query->countFor($superAdmin));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()->assertSee('Review correction requests')->assertSee('HOD correction requests');
        $adminQueue = $this->actingAs($admin)->get(route('admin.timesheets.index', ['corrections' => 'open']))->assertOk();
        $this->assertSame([$eligibleHod->id], $adminQueue->viewData('timesheets')->pluck('user_id')->all());
        $adminQueue->assertSee('Needs review · 1');

        $superAdminQueue = $this->actingAs($superAdmin)->get(route('admin.timesheets.index', ['corrections' => 'open']))->assertOk();
        $this->assertEqualsCanonicalizing([$eligibleHod->id, $excludedHod->id], $superAdminQueue->viewData('timesheets')->pluck('user_id')->all());

        $eligibleRequest->update(['status' => TimesheetCorrectionRequest::STATUS_DISMISSED, 'resolved_at' => now()]);
        $this->assertSame(0, $query->countFor($admin));
        $closedQueue = $this->actingAs($admin)->get(route('admin.timesheets.index', ['corrections' => 'open']))->assertOk();
        $this->assertTrue($closedQueue->viewData('timesheets')->isEmpty());
    }

    public function test_dashboard_count_uses_one_bounded_aggregate_query(): void
    {
        [$admin] = $this->queueScenario();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $count = app(AdminHodCorrectionRequestQuery::class)->countFor($admin);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(1, $count);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('timesheet_entries', strtolower($queries[0]['query']));
    }

    private function queueScenario(): array
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super_admin');
        $eligibleHod = $this->userWithRole('hod', ['name' => 'Eligible HOD Queue User', 'department_id' => $department->id]);
        $excludedHod = $this->userWithRole('hod', ['name' => 'Excluded HOD Queue User', 'department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['name' => 'Employee Queue User', 'department_id' => $department->id]);
        $admin->adminApprovalExcludedHods()->attach($excludedHod);
        $manager = $this->userWithRole('employee');
        $project = $this->project(['project_manager_id' => $manager->id]);
        $period = $this->openPeriod();

        $eligibleTimesheet = $this->submittedTimesheet($eligibleHod, $period, $project);
        $excludedTimesheet = $this->submittedTimesheet($excludedHod, $period, $project);
        $employeeTimesheet = $this->submittedTimesheet($employee, $period, $project);
        $eligibleRequest = $this->correction($eligibleTimesheet, $manager->id);
        $this->correction($excludedTimesheet, $manager->id);
        $this->correction($employeeTimesheet, $manager->id);

        return [$admin, $superAdmin, $eligibleHod, $excludedHod, $employee, $eligibleRequest];
    }

    private function correction(Timesheet $timesheet, int $requesterId): TimesheetCorrectionRequest
    {
        $entry = $timesheet->entries()->firstOrFail();
        $request = TimesheetCorrectionRequest::create([
            'timesheet_id' => $timesheet->id,
            'requested_by' => $requesterId,
            'department_id' => $timesheet->department_id,
            'status' => TimesheetCorrectionRequest::STATUS_OPEN,
            'comment' => 'Review the recorded project hours.',
        ]);
        $request->entries()->create([
            'timesheet_entry_id' => $entry->id,
            'project_id' => $entry->project_id,
            'work_date' => $entry->work_date,
            'project_code' => $entry->project->project_code,
            'regular_hours' => $entry->regular_hours,
            'overtime_hours' => $entry->overtime_hours,
            'description' => $entry->description,
        ]);

        return $request;
    }
}
