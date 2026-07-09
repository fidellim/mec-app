<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\LeaveEntitlement;
use App\Models\LeaveSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class DashboardWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_renders_for_each_role(): void
    {
        $department = $this->department();
        foreach (['employee', 'hod'] as $role) {
            $user = $this->userWithRole($role, [
                'department_id' => $department->id,
                'eligible_for_bereavement_spouse_leave' => true,
            ]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Dashboard')
                ->assertSee($role === 'employee' ? 'Leave balances' : 'My leave balances')
                ->assertSee('Annual leave')
                ->assertDontSee('Sick leave')
                ->assertDontSee('Bereavement leave - Spouse')
                ->assertDontSee('Maternity leave');
        }

        foreach (['admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role, [
                'eligible_for_bereavement_spouse_leave' => true,
            ]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Dashboard')
                ->assertSee('My leave balances')
                ->assertSee('Annual leave')
                ->assertSee('Sick leave')
                ->assertSee('Bereavement leave - Spouse')
                ->assertDontSee('Maternity leave');
        }
    }

    public function test_employee_dashboard_shows_current_week_actions_by_status(): void
    {
        $department = $this->department();
        $period = $this->openPeriod();
        $project = $this->project();

        $employeeWithoutTimesheet = $this->userWithRole('employee', ['department_id' => $department->id]);

        $this->actingAs($employeeWithoutTimesheet)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Timesheet not submitted yet')
            ->assertSee('Create Weekly Timesheet');

        foreach ([
            'draft' => 'Continue Draft',
            'rejected' => 'Fix and Resubmit',
            'submitted' => 'View Timesheet',
            'approved' => 'View Timesheet',
        ] as $status => $actionLabel) {
            $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
            $this->submittedTimesheet($employee, $period, $project, ['status' => $status]);

            $this->actingAs($employee)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Your timesheet is '.$status)
                ->assertSee($actionLabel);
        }
    }

    public function test_philippines_dashboard_hides_zero_day_non_statutory_leave_balances(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'employee_code' => 'MEC-PHIL-HR-2026-131',
            'joining_date' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Leave balances')
            ->assertSee('Service incentive leave')
            ->assertDontSee('Annual leave')
            ->assertDontSee('Sick leave')
            ->assertDontSee('Bereavement leave')
            ->assertViewHas('leaveBalances', fn (array $leaveBalances) => array_key_exists('L190', $leaveBalances)
                && ! array_key_exists('L100', $leaveBalances)
                && ! array_key_exists('L110', $leaveBalances)
                && ! array_key_exists('L180', $leaveBalances)
            );
    }

    public function test_dashboard_access_on_january_first_creates_new_year_leave_entitlements(): void
    {
        LeaveSetting::where('key', LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE)->firstOrFail()->update(['decimal_value' => 5]);

        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'annual_leave_allowance_days' => 12,
        ]);

        LeaveEntitlement::create([
            'user_id' => $employee->id,
            'year' => 2026,
            'attendance_code' => 'L100',
            'allowance_days' => 3,
            'claimable_allowance_days' => 3,
            'source' => LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
            'region' => 'uae',
            'setting_key' => LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE,
        ]);

        Carbon::setTestNow('2027-01-01 00:00:00');

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Leave balances')
            ->assertSee('Annual leave')
            ->assertViewHas('leaveBalances', fn (array $leaveBalances) => ($leaveBalances['L100']['year'] ?? null) === 2027
                && ($leaveBalances['L100']['formatted']['allowance'] ?? null) === '12'
            );

        $this->assertDatabaseHas('leave_entitlements', [
            'user_id' => $employee->id,
            'year' => 2026,
            'attendance_code' => 'L100',
            'source' => LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
            'claimable_allowance_days' => '3.00',
        ]);

        $this->assertDatabaseHas('leave_entitlements', [
            'user_id' => $employee->id,
            'year' => 2027,
            'attendance_code' => 'L100',
            'source' => LeaveEntitlement::SOURCE_USER_OVERRIDE,
            'claimable_allowance_days' => '12.00',
        ]);
    }

    public function test_admin_dashboard_reports_latest_completed_period_before_current_period(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project();
        $lastWeek = $this->openPeriod();
        $currentWeek = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);

        $this->submittedTimesheet($employee, $lastWeek, $project, ['status' => 'submitted']);
        $this->submittedTimesheet($employee, $currentWeek, $project, ['status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('period', fn ($period) => $period->is($lastWeek))
            ->assertViewHas('summary', [
                'submitted' => 1,
                'approved' => 0,
                'rejected' => 0,
            ])
            ->assertViewHas('missing', 0)
            ->assertSee('Reporting period:')
            ->assertSee('Week 20, 2026');
    }

    public function test_admin_dashboard_shows_department_health_counts(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department(['name' => 'Operations']);
        $admin = $this->userWithRole('admin');
        $period = $this->openPeriod();
        $project = $this->project();
        $submittedEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $approvedEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $rejectedEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $this->userWithRole('employee', ['department_id' => $department->id]);

        $this->submittedTimesheet($submittedEmployee, $period, $project, ['status' => 'submitted']);
        $this->submittedTimesheet($approvedEmployee, $period, $project, ['status' => 'approved']);
        $this->submittedTimesheet($rejectedEmployee, $period, $project, ['status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Department health')
            ->assertSee('Operations')
            ->assertViewHas('departments', function ($departments) use ($department) {
                $row = $departments->firstWhere('id', $department->id);

                return $row
                    && $row->timesheets_count === 3
                    && $row->submitted_count === 1
                    && $row->approved_count === 1
                    && $row->rejected_count === 1
                    && $row->missing_count === 2;
            });
    }

    public function test_admin_dashboard_summary_refreshes_after_cache_expiry(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $timesheet = $this->submittedTimesheet($employee, $period, $this->project(), ['status' => 'submitted']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('summary', [
                'submitted' => 1,
                'approved' => 0,
                'rejected' => 0,
            ]);

        DB::table('timesheets')
            ->where('id', $timesheet->id)
            ->update(['status' => 'approved']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('summary', [
                'submitted' => 1,
                'approved' => 0,
                'rejected' => 0,
            ]);

        Carbon::setTestNow('2026-05-20 12:01:01');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('summary', [
                'submitted' => 0,
                'approved' => 1,
                'rejected' => 0,
            ]);
    }

    public function test_hod_dashboard_summary_is_invalidated_after_approval(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), ['status' => 'submitted']);

        $this->actingAs($hod)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pending', 1)
            ->assertViewHas('approved', 0);

        $this->actingAs($hod)
            ->post(route('hod.timesheets.approve', $timesheet))
            ->assertRedirect();

        $this->actingAs($hod)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pending', 0)
            ->assertViewHas('approved', 1);

        Mail::assertQueued(TimesheetWorkflowMail::class);
    }

    public function test_regional_submission_summary_is_invalidated_after_resubmission(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-130',
        ]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), ['status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('regionalSubmissionSummary', function (array $summary) {
                return $summary['submitted'] === 0
                    && $summary['not_submitted'] === 1
                    && $summary['regions']['ph']['not_submitted'] === 1;
            });

        $timesheet->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'rejection_comment' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('regionalSubmissionSummary', function (array $summary) {
                return $summary['submitted'] === 1
                    && $summary['not_submitted'] === 0
                    && $summary['regions']['ph']['submitted'] === 1;
            });
    }

    public function test_hod_dashboard_reports_latest_completed_period_before_current_period(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $submittedEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $missingEmployee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $otherDepartment = $this->department();
        $otherEmployee = $this->userWithRole('employee', ['department_id' => $otherDepartment->id]);
        $project = $this->project();
        $lastWeek = $this->openPeriod();
        $currentWeek = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);

        $this->submittedTimesheet($submittedEmployee, $lastWeek, $project, ['status' => 'approved']);
        $this->submittedTimesheet($submittedEmployee, $currentWeek, $project, ['status' => 'submitted']);
        $this->submittedTimesheet($otherEmployee, $lastWeek, $project, ['status' => 'submitted']);

        $this->actingAs($hod)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('period', fn ($period) => $period->is($lastWeek))
            ->assertViewHas('pending', 0)
            ->assertViewHas('approved', 1)
            ->assertViewHas('rejected', 0)
            ->assertViewHas('missing', 1)
            ->assertSee('Reporting period:')
            ->assertSee('Week 20, 2026');
    }

    public function test_admin_dashboard_falls_back_to_latest_open_period_when_none_completed(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $project = $this->project();
        $currentWeek = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);

        $this->submittedTimesheet($employee, $currentWeek, $project, ['status' => 'submitted']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('period', fn ($period) => $period->is($currentWeek))
            ->assertViewHas('summary', [
                'submitted' => 1,
                'approved' => 0,
                'rejected' => 0,
            ])
            ->assertViewHas('missing', 0);
    }

    public function test_admin_dashboard_returns_zero_counts_without_any_period(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('period', null)
            ->assertViewHas('summary', [
                'submitted' => 0,
                'approved' => 0,
                'rejected' => 0,
            ])
            ->assertViewHas('missing', 0)
            ->assertSee('No weekly period available')
            ->assertSee('No employees to track')
            ->assertSee('There are no active employees in this reporting scope yet.');
    }

    public function test_admin_dashboard_groups_regional_submission_status_for_reporting_period(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $admin = $this->userWithRole('admin');
        $project = $this->project();
        $lastWeek = $this->openPeriod();
        $currentWeek = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $uaeSubmitted = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-101',
        ]);
        $uaeRejected = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MCE-HR-2026-102',
        ]);
        $phApproved = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-103',
        ]);
        $phMissing = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-104',
        ]);
        $unknownSubmitted = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => null,
        ]);

        $this->submittedTimesheet($uaeSubmitted, $lastWeek, $project, ['status' => 'submitted']);
        $this->submittedTimesheet($uaeRejected, $lastWeek, $project, ['status' => 'rejected']);
        $this->submittedTimesheet($phApproved, $lastWeek, $project, ['status' => 'approved']);
        $this->submittedTimesheet($unknownSubmitted, $lastWeek, $project, ['status' => 'submitted']);
        $this->submittedTimesheet($phMissing, $currentWeek, $project, ['status' => 'submitted']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('regionalSubmissionSummary', function (array $summary) {
                return $summary['total'] === 5
                    && $summary['submitted'] === 3
                    && $summary['not_submitted'] === 2
                    && $summary['regions']['uae']['submitted'] === 1
                    && $summary['regions']['uae']['not_submitted'] === 1
                    && $summary['regions']['ph']['submitted'] === 1
                    && $summary['regions']['ph']['not_submitted'] === 1
                    && $summary['regions']['unknown']['submitted'] === 1
                    && $summary['regions']['unknown']['not_submitted'] === 0;
            })
            ->assertSee('Regional submission status')
            ->assertSee('60%')
            ->assertSee('3 of 5 active employees submitted.')
            ->assertSee('1 need follow-up')
            ->assertSee('status=not_submitted', false)
            ->assertSee('week_from=20', false)
            ->assertSee('year=2026', false)
            ->assertSee('United Arab Emirates')
            ->assertSee('Philippines')
            ->assertSee('Unknown includes active employees without a recognized employee number prefix.');
    }

    public function test_hod_dashboard_regional_submission_status_is_limited_to_department(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $otherDepartment = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $project = $this->project();
        $lastWeek = $this->openPeriod();
        $departmentUaeEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-111',
        ]);
        $departmentPhEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-112',
        ]);
        $otherDepartmentEmployee = $this->userWithRole('employee', [
            'department_id' => $otherDepartment->id,
            'employee_code' => 'MEC-PHIL-HR-2026-113',
        ]);

        $this->submittedTimesheet($departmentUaeEmployee, $lastWeek, $project, ['status' => 'submitted']);
        $this->submittedTimesheet($otherDepartmentEmployee, $lastWeek, $project, ['status' => 'submitted']);

        $this->actingAs($hod)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('regionalSubmissionSummary', function (array $summary) {
                return $summary['total'] === 2
                    && $summary['submitted'] === 1
                    && $summary['not_submitted'] === 1
                    && $summary['regions']['uae']['submitted'] === 1
                    && $summary['regions']['uae']['not_submitted'] === 0
                    && $summary['regions']['ph']['submitted'] === 0
                    && $summary['regions']['ph']['not_submitted'] === 1;
            })
            ->assertSee('50%')
            ->assertSee('1 of 2 active employees submitted.')
            ->assertSee('Open department tracker')
            ->assertSee(route('hod.tracker'), false)
            ->assertDontSee('Unknown includes active employees without a recognized employee number prefix.');
    }

    public function test_hod_dashboard_shows_only_managed_departments(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department(['name' => 'Managed Department']);
        $otherDepartment = $this->department(['name' => 'Other Department']);
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->hods()->attach($hod);
        $this->openPeriod();

        $this->actingAs($hod)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Managed departments')
            ->assertSee('Managed Department')
            ->assertDontSee('Other Department');
    }

    public function test_super_admin_dashboard_has_regional_submission_status_for_reporting_period(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $department = $this->department();
        $superAdmin = $this->userWithRole('super_admin');
        $project = $this->project();
        $lastWeek = $this->openPeriod();
        $currentWeek = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $lastWeekEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-121',
        ]);
        $currentWeekEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-122',
        ]);

        $this->submittedTimesheet($lastWeekEmployee, $lastWeek, $project, ['status' => 'approved']);
        $this->submittedTimesheet($currentWeekEmployee, $currentWeek, $project, ['status' => 'submitted']);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('submissionPeriod', fn ($period) => $period->is($lastWeek))
            ->assertViewHas('regionalSubmissionSummary', function (array $summary) {
                return $summary['total'] === 2
                    && $summary['submitted'] === 1
                    && $summary['not_submitted'] === 1
                    && $summary['regions']['ph']['submitted'] === 1
                    && $summary['regions']['uae']['not_submitted'] === 1;
            })
            ->assertSee('50%')
            ->assertSee('1 of 2 active employees submitted.')
            ->assertSee('status=not_submitted', false)
            ->assertSee('week_from=20', false)
            ->assertSee('year=2026', false)
            ->assertDontSee('Unknown includes active employees without a recognized employee number prefix.');
    }

    public function test_super_admin_dashboard_shows_management_shortcuts_only_for_super_admin(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('System management')
            ->assertSee('Users')
            ->assertSee('Audit logs')
            ->assertSee('System settings');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('System management')
            ->assertDontSee('Audit logs');
    }
}
