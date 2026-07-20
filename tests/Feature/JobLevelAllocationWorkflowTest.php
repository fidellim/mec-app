<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Services\TimesheetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class JobLevelAllocationWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_admin_can_assign_a_job_level_on_the_user_profile(): void
    {
        $admin = $this->userWithRole('admin');
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => null]);

        $this->actingAs($admin)->put(route('manage.users.update', $employee), $this->userPayload($employee, [
            'job_level' => 'senior',
        ]))->assertRedirect(route('manage.users.index'));

        $this->assertSame('senior', $employee->fresh()->job_level);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_updated', 'auditable_id' => $employee->id]);
    }

    public function test_new_active_timesheet_user_requires_a_job_level(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'Unclassified New Employee',
            'email' => 'unclassified.new@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-990',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertSessionHasErrors('job_level');

        $this->assertDatabaseMissing('users', ['email' => 'unclassified.new@example.com']);
    }

    public function test_reserved_pending_hours_block_an_atomic_submission_and_draft_does_not_consume(): void
    {
        Mail::fake();
        $department = $this->department();
        $senior = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => 'senior']);
        $project = $this->controlledProject($department->id, 20, ['senior' => 10, 'junior' => null]);
        $firstPeriod = $this->openPeriod();
        $existing = $this->submittedTimesheet($senior, $firstPeriod, $project);
        $existing->entries()->first()->update([
            'regular_hours' => 8,
            'job_level_snapshot' => 'senior',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);

        $draftPeriod = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $draft = $this->submittedTimesheet($senior, $draftPeriod, $project, ['status' => Timesheet::STATUS_DRAFT]);
        $draft->entries()->first()->update(['regular_hours' => 20]);

        $thirdPeriod = $this->openPeriod(['week_number' => 22, 'start_date' => '2026-05-25', 'end_date' => '2026-05-31']);
        $response = $this->actingAs($senior)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $thirdPeriod->id,
            'submit' => '1',
            'entries' => $this->entriesForPeriod($project, $department->id, '2026-05-25', 3),
        ]);

        $response->assertSessionHasErrors('entries.0.department_id');
        $this->assertSame(TimesheetAllocationService::EXCEEDED_MESSAGE, session('errors')->first('entries.0.department_id'));
        $this->assertDatabaseMissing('timesheets', ['user_id' => $senior->id, 'timesheet_period_id' => $thirdPeriod->id]);
    }

    public function test_shared_remainder_is_protected_from_unreserved_levels(): void
    {
        Mail::fake();
        $department = $this->department();
        $junior = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => 'junior']);
        $project = $this->controlledProject($department->id, 20, ['senior' => 10, 'junior' => null]);
        $period = $this->openPeriod();
        $existing = $this->submittedTimesheet($junior, $period, $project);
        $existing->entries()->first()->update([
            'regular_hours' => 9,
            'job_level_snapshot' => 'junior',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_SHARED,
        ]);

        $next = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $this->actingAs($junior)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $next->id,
            'submit' => '1',
            'entries' => $this->entriesForPeriod($project, $department->id, '2026-05-18', 2),
        ])->assertSessionHasErrors('entries.0.department_id');
    }

    public function test_not_allowed_and_unclassified_users_are_blocked_with_the_appropriate_message(): void
    {
        Mail::fake();
        $department = $this->department();
        $project = $this->controlledProject($department->id, 20, ['management' => 0, 'junior' => null]);
        $period = $this->openPeriod();

        $manager = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => 'management']);
        $this->actingAs($manager)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->entriesForPeriod($project, $department->id, '2026-05-11', 1),
        ])->assertSessionHasErrors('entries.0.department_id');

        $unclassified = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => null]);
        $response = $this->actingAs($unclassified)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->entriesForPeriod($project, $department->id, '2026-05-11', 1),
        ]);
        $response->assertSessionHasErrors('entries.0.department_id');
        $this->assertSame(TimesheetAllocationService::MISSING_LEVEL_MESSAGE, session('errors')->first('entries.0.department_id'));
    }

    public function test_rejected_hours_are_released_and_submission_snapshots_the_job_level(): void
    {
        Mail::fake();
        $department = $this->department();
        $senior = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => 'senior']);
        $project = $this->controlledProject($department->id, 10, ['senior' => 8, 'junior' => null]);
        $period = $this->openPeriod();
        $rejected = $this->submittedTimesheet($senior, $period, $project, ['status' => Timesheet::STATUS_REJECTED]);
        $rejected->entries()->first()->update([
            'regular_hours' => 8,
            'job_level_snapshot' => 'senior',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);

        $next = $this->openPeriod(['week_number' => 21, 'start_date' => '2026-05-18', 'end_date' => '2026-05-24']);
        $this->actingAs($senior)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $next->id,
            'submit' => '1',
            'entries' => $this->entriesForPeriod($project, $department->id, '2026-05-18', 8),
        ])->assertRedirect();

        $entry = TimesheetEntry::whereHas('timesheet', fn ($query) => $query->where('timesheet_period_id', $next->id))->firstOrFail();
        $this->assertSame('senior', $entry->job_level_snapshot);
        $this->assertSame(TimesheetAllocationService::BUCKET_RESERVED, $entry->allocation_bucket_snapshot);
    }

    public function test_correction_resubmission_keeps_the_original_job_level_after_a_profile_change(): void
    {
        Mail::fake();
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => 'senior']);
        $project = $this->controlledProject($department->id, 10, ['senior' => 10, 'management' => 0]);
        $period = $this->openPeriod();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, ['status' => Timesheet::STATUS_REJECTED]);
        $entry = $timesheet->entries()->first();
        $entry->update([
            'job_level_snapshot' => 'senior',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);
        $employee->update(['job_level' => 'management']);

        $this->actingAs($employee)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => [[
                'id' => $entry->id,
                'work_date' => $period->start_date->toDateString(),
                'attendance_code' => 'O100',
                'project_id' => $project->id,
                'department_id' => $department->id,
                'regular_hours' => 9,
                'overtime_hours' => 0,
                'remarks' => '',
            ]],
        ])->assertRedirect();

        $corrected = $timesheet->fresh()->entries()->firstOrFail();
        $this->assertSame(Timesheet::STATUS_SUBMITTED, $timesheet->fresh()->status);
        $this->assertSame('senior', $corrected->job_level_snapshot);
        $this->assertSame(TimesheetAllocationService::BUCKET_RESERVED, $corrected->allocation_bucket_snapshot);
    }

    public function test_project_controls_validate_partition_totals_and_require_an_audit_reason(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $project = $this->project(['project_manager_id' => $manager->id, 'start_date' => '2026-01-01']);
        $project->departmentAllocations()->create(['department_id' => $department->id, 'allocated_hours' => 100]);

        $payload = $this->projectPayload($project, $manager->id, $department->id, 100, [
            'senior' => ['mode' => 'reserved', 'hours' => 60],
            'lead_principal' => ['mode' => 'reserved', 'hours' => 50],
        ]);
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors("job_level_allocations.$department->id");

        $payload['job_level_allocations'][$department->id]['lead_principal']['hours'] = 40;
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors('allocation_change_reason');

        $payload['allocation_change_reason'] = 'Protect senior project delivery capacity.';
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)->assertRedirect();
        $this->assertSame(6, $project->departmentAllocations()->first()->jobLevelAllocations()->count());
        $audit = AuditLog::where('action', 'project_allocations_updated')->latest()->firstOrFail();
        $this->assertSame('Protect senior project delivery capacity.', $audit->new_values['reason']);
    }

    public function test_reservation_cannot_be_reduced_or_removed_below_pending_usage(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('hod', ['department_id' => $department->id]);
        $senior = $this->userWithRole('employee', ['department_id' => $department->id, 'job_level' => 'senior']);
        $project = $this->controlledProject($department->id, 100, ['senior' => 40, 'junior' => null], ['project_manager_id' => $manager->id, 'start_date' => '2026-01-01']);
        $timesheet = $this->submittedTimesheet($senior, $this->openPeriod(), $project);
        $timesheet->entries()->first()->update([
            'regular_hours' => 30,
            'job_level_snapshot' => 'senior',
            'allocation_bucket_snapshot' => TimesheetAllocationService::BUCKET_RESERVED,
        ]);

        $payload = $this->projectPayload($project, $manager->id, $department->id, 100, [
            'senior' => ['mode' => 'reserved', 'hours' => 29],
            'junior' => ['mode' => 'shared', 'hours' => null],
        ]);
        $payload['allocation_change_reason'] = 'Reforecast.';
        $this->actingAs($admin)->put(route('manage.projects.update', $project), $payload)
            ->assertSessionHasErrors("job_level_allocations.$department->id.senior.hours");
    }

    private function controlledProject(int $departmentId, float $hours, array $states, array $attributes = []): Project
    {
        $project = $this->project($attributes);
        $allocation = $project->departmentAllocations()->create(['department_id' => $departmentId, 'allocated_hours' => $hours]);
        foreach (array_keys(config('job_levels.labels')) as $level) {
            $allocation->jobLevelAllocations()->create([
                'job_level' => $level,
                'allocated_hours' => array_key_exists($level, $states) ? $states[$level] : 0,
            ]);
        }

        return $project;
    }

    private function entriesForPeriod(Project $project, int $departmentId, string $date, float $hours): array
    {
        return [[
            'work_date' => $date,
            'attendance_code' => 'O100',
            'project_id' => $project->id,
            'department_id' => $departmentId,
            'regular_hours' => $hours,
            'overtime_hours' => 0,
            'remarks' => '',
        ]];
    }

    private function projectPayload(Project $project, int $managerId, int $departmentId, float $hours, array $overrides): array
    {
        $levels = collect(config('job_levels.labels'))->mapWithKeys(fn ($label, $level) => [$level => ['mode' => 'not_allowed', 'hours' => null]])->all();
        foreach ($overrides as $level => $value) {
            $levels[$level] = $value;
        }

        return [
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'start_date' => '2026-01-01',
            'project_manager_id' => $managerId,
            'department_allocations' => [$departmentId => $hours],
            'job_level_controls' => [$departmentId => 1],
            'job_level_allocations' => [$departmentId => $levels],
            'is_active' => '1',
            'timesheet_assignment_mode' => Project::ASSIGNMENT_ALL_USERS,
        ];
    }

    private function userPayload($user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'employee_code' => $user->employee_code,
            'initials' => $user->initials,
            'job_title' => $user->job_title,
            'job_level' => $user->job_level,
            'department_id' => $user->department_id,
            'is_active' => '1',
        ], $overrides);
    }
}
