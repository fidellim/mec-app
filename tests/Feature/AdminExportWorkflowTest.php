<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\LeavePlan;
use App\Models\TimesheetEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AdminExportWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_admin_can_view_all_timesheets_and_filter_by_status(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $project = $this->project(['project_code' => 'P-FILTER', 'project_name' => 'Filter Project']);
        $employee = $this->userWithRole('employee', [
            'name' => 'Ben Carter',
            'department_id' => $department->id,
        ]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['status' => $timesheet->status]))
            ->assertOk()
            ->assertSee('From Week')
            ->assertSee('To Week')
            ->assertSee('href="'.route('admin.timesheets.index').'"', false)
            ->assertSee('Clear')
            ->assertSee('Include individual employee timesheet sheets')
            ->assertSee('Filters are active. Add a valid week and year to see the configured date range.')
            ->assertSee('filter-summary-badge')
            ->assertSee('Export started. Your Excel file will download when ready.')
            ->assertSee('Starting export...')
            ->assertSee('app-toast-success')
            ->assertSee('bottom-0')
            ->assertDontSee('Preparing export...')
            ->assertSee('Status: Submitted')
            ->assertSee('P-FILTER - Filter Project')
            ->assertSee('Ben Carter')
            ->assertSee('Operations');
    }

    public function test_admin_can_filter_all_timesheets_by_role(): void
    {
        $department = $this->department(['name' => 'Role Filter Department']);
        $project = $this->project();
        $employee = $this->userWithRole('employee', [
            'name' => 'Role Filter Employee',
            'department_id' => $department->id,
        ]);
        $hod = $this->userWithRole('hod', [
            'name' => 'Role Filter HOD',
            'department_id' => $department->id,
        ]);
        $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $this->submittedTimesheet($hod, $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]), $project);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['role' => 'hod']))
            ->assertOk()
            ->assertSee('Role: Head of Department')
            ->assertSee('Role Filter HOD')
            ->assertDontSee('<td class="fw-semibold">Role Filter Employee</td>', false);
    }

    public function test_admin_can_view_not_submitted_users_by_week_and_role(): void
    {
        $department = $this->department(['name' => 'Missing Department']);
        $otherDepartment = $this->department(['name' => 'Other Missing Department']);
        $period = $this->openPeriod();
        $project = $this->project();
        $submittedEmployee = $this->userWithRole('employee', [
            'name' => 'Submitted Employee',
            'department_id' => $department->id,
        ]);
        $missingHod = $this->userWithRole('hod', [
            'name' => 'Missing HOD',
            'department_id' => $department->id,
        ]);
        $missingAdmin = $this->userWithRole('admin', [
            'name' => 'Missing Admin',
            'department_id' => $department->id,
        ]);
        $missingSuperAdmin = $this->userWithRole('super_admin', [
            'name' => 'Missing Super Admin',
            'department_id' => $department->id,
        ]);
        $this->userWithRole('admin', [
            'name' => 'Inactive Missing Admin',
            'department_id' => $department->id,
            'is_active' => false,
        ]);
        $this->userWithRole('hod', [
            'name' => 'Other Department HOD',
            'department_id' => $otherDepartment->id,
        ]);
        $this->submittedTimesheet($submittedEmployee, $period, $project);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', [
                'status' => 'not_submitted',
                'week_from' => 20,
                'year' => 2026,
                'department_id' => $department->id,
            ]))
            ->assertOk()
            ->assertSee('Status: Not submitted')
            ->assertSee('Missing HOD')
            ->assertSee('Missing Admin')
            ->assertSee('Missing Super Admin')
            ->assertDontSee('<td class="fw-semibold">Submitted Employee</td>', false)
            ->assertDontSee('<td class="fw-semibold">Inactive Missing Admin</td>', false)
            ->assertDontSee('<td class="fw-semibold">Other Department HOD</td>', false)
            ->assertDontSee('id="timesheetExportButton"', false);

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', [
                'status' => 'not_submitted',
                'week_from' => 20,
                'year' => 2026,
                'department_id' => $department->id,
                'role' => 'hod',
            ]))
            ->assertOk()
            ->assertSee('Missing HOD')
            ->assertDontSee('<td class="fw-semibold">Missing Admin</td>', false)
            ->assertDontSee('<td class="fw-semibold">Missing Super Admin</td>', false);
    }

    public function test_not_submitted_filter_requires_week_and_year(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['status' => 'not_submitted']))
            ->assertSessionHasErrors(['week_from', 'year']);
    }

    public function test_admin_can_approve_employee_timesheet(): void
    {
        Mail::fake();

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.timesheets.approve', $timesheet))
            ->assertRedirect();

        $this->assertSame('approved', $timesheet->refresh()->status);
        $this->assertSame($admin->id, $timesheet->approved_by);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Timesheet approved');
    }

    public function test_admin_can_reject_employee_timesheet(): void
    {
        Mail::fake();

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.timesheets.reject', $timesheet), ['rejection_comment' => 'Please correct Tuesday overtime.'])
            ->assertRedirect();

        $timesheet->refresh();
        $this->assertSame('rejected', $timesheet->status);
        $this->assertSame($admin->id, $timesheet->rejected_by);
        $this->assertSame('Please correct Tuesday overtime.', $timesheet->rejection_comment);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($employee->email)
            && $mail->headline === 'Timesheet rejected');
    }

    public function test_admin_can_approve_hod_timesheet(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.timesheets.approve', $timesheet))
            ->assertRedirect();

        $this->assertSame('approved', $timesheet->refresh()->status);
        $this->assertSame($admin->id, $timesheet->approved_by);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet approved');
    }

    public function test_admin_can_reject_hod_timesheet(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.timesheets.reject', $timesheet), ['rejection_comment' => 'Please update the project.'])
            ->assertRedirect();

        $timesheet->refresh();
        $this->assertSame('rejected', $timesheet->status);
        $this->assertSame($admin->id, $timesheet->rejected_by);
        $this->assertSame('Please update the project.', $timesheet->rejection_comment);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet rejected');
    }

    public function test_admin_cannot_approve_own_timesheet(): void
    {
        $admin = $this->userWithRole('admin', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($admin, $this->openPeriod(), $this->project());

        $this->actingAs($admin)
            ->from(route('admin.timesheets.show', $timesheet))
            ->post(route('admin.timesheets.approve', $timesheet))
            ->assertRedirect(route('admin.timesheets.show', $timesheet))
            ->assertSessionHas('warning');

        $this->actingAs($admin)
            ->from(route('admin.timesheets.show', $timesheet))
            ->post(route('admin.timesheets.reject', $timesheet), ['rejection_comment' => 'Please revise.'])
            ->assertRedirect(route('admin.timesheets.show', $timesheet))
            ->assertSessionHas('warning');
    }

    public function test_super_admin_can_approve_from_admin_area(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)->post(route('admin.timesheets.approve', $timesheet))->assertRedirect();

        $this->assertSame('approved', $timesheet->refresh()->status);
        $this->assertSame($superAdmin->id, $timesheet->approved_by);
    }

    public function test_super_admin_approval_and_rejection_notify_hod_timesheet_owner(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $nextPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $project = $this->project();
        $approvedTimesheet = $this->submittedTimesheet($hod, $period, $project);
        $rejectedTimesheet = $this->submittedTimesheet($hod, $nextPeriod, $project);
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->post(route('admin.timesheets.approve', $approvedTimesheet))
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->post(route('admin.timesheets.reject', $rejectedTimesheet), ['rejection_comment' => 'Please revise.'])
            ->assertRedirect();

        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet approved');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet rejected'
            && $mail->comment === 'Please revise.');
    }

    public function test_super_admin_cannot_approve_own_timesheet(): void
    {
        $superAdmin = $this->userWithRole('super_admin', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($superAdmin, $this->openPeriod(), $this->project());

        $this->actingAs($superAdmin)
            ->get(route('admin.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('You cannot approve or reject your own timesheet')
            ->assertDontSee('Approve this timesheet?')
            ->assertDontSee('Rejection comment');

        $this->actingAs($superAdmin)
            ->from(route('admin.timesheets.show', $timesheet))
            ->post(route('admin.timesheets.approve', $timesheet))
            ->assertRedirect(route('admin.timesheets.show', $timesheet))
            ->assertSessionHas('warning');
    }

    public function test_admin_can_see_approval_actions_for_hod_timesheet(): void
    {
        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($hod, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('Week 20, 2026')
            ->assertSee('2026-05-11 to 2026-05-17')
            ->assertSee('Approve this timesheet?')
            ->assertSee('Rejection comment');
    }

    public function test_admin_can_see_approval_actions_for_employee_timesheet(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('Approve this timesheet?')
            ->assertSee('Rejection comment');
    }

    public function test_admin_can_download_excel_export_summary_only_by_default(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'initials' => 'ZX',
            'job_title' => 'Project Engineer',
        ]);
        $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), ['status' => 'approved']);
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Project Weekly Summary', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('Attendance Code Summary', $spreadsheet->getSheet(1)->getTitle());
        $this->assertSame('G', $spreadsheet->getSheet(0)->getHighestColumn());
        $this->assertStringContainsString('Week 20, 2026', $spreadsheet->getSheet(0)->getCell('E4')->getValue());
        $this->assertSame('', (string) $spreadsheet->getSheet(0)->getCell('H4')->getValue());
    }

    public function test_admin_timesheet_export_is_throttled(): void
    {
        $admin = $this->userWithRole('admin');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($admin)
                ->withServerVariables(['REMOTE_ADDR' => '10.20.30.10'])
                ->get(route('admin.timesheets.export'))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->from(route('admin.timesheets.index'))
            ->withServerVariables(['REMOTE_ADDR' => '10.20.30.10'])
            ->get(route('admin.timesheets.export'))
            ->assertRedirect(route('admin.timesheets.index'))
            ->assertSessionHas('warning');
    }

    public function test_admin_timesheet_export_warns_when_export_is_already_running(): void
    {
        $admin = $this->userWithRole('admin');
        $lock = Cache::lock('exports:user:'.$admin->id, 120);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($admin)
                ->from(route('admin.timesheets.index'))
                ->get(route('admin.timesheets.export'))
                ->assertRedirect(route('admin.timesheets.index'))
                ->assertSessionHas('warning', 'An export is already running. Please wait for it to finish before starting another export.');
        } finally {
            $lock->release();
        }
    }

    public function test_admin_timesheet_index_shows_export_problem_toast_from_backend_warning(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['warning' => 'An export is already running. Please wait for it to finish before starting another export.'])
            ->get(route('admin.timesheets.index'))
            ->assertOk()
            ->assertSee('data-app-toast', false)
            ->assertSee('app-toast-warning')
            ->assertSee('Notice')
            ->assertSee('An export is already running. Please wait for it to finish before starting another export.');
    }

    public function test_admin_can_include_individual_employee_sheets_in_excel_export(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'initials' => 'ZX',
            'job_title' => 'Project Engineer',
        ]);
        $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), ['status' => 'approved']);
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
            'include_employee_sheets' => 1,
        ]));

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $this->assertSame(3, $spreadsheet->getSheetCount());
        $this->assertSame('ZX', $spreadsheet->getSheet(2)->getCell('B4')->getValue());
        $this->assertSame('Project Engineer', $spreadsheet->getSheet(2)->getCell('K5')->getValue());
    }

    public function test_individual_excel_sheet_does_not_count_training_code_as_leave_hours(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'initials' => 'TR',
            'job_title' => 'Training Coordinator',
        ]);
        $period = $this->openPeriod();
        $timesheet = $this->submittedTimesheet($employee, $period, $this->project(), [
            'status' => 'approved',
            'total_regular_hours' => 14,
            'total_overtime_hours' => 2,
            'total_hours' => 16,
        ]);
        $timesheet->entries()->delete();

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-11',
            'day_name' => 'Monday',
            'attendance_code' => 'L200',
            'project_id' => null,
            'regular_hours' => 6,
            'overtime_hours' => 2,
            'remarks' => 'Training seminar',
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-12',
            'day_name' => 'Tuesday',
            'attendance_code' => 'L100',
            'project_id' => null,
            'regular_hours' => 8,
            'overtime_hours' => 0,
            'remarks' => 'Annual leave',
        ]);

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
            'include_employee_sheets' => 1,
        ]));

        $response->assertOk();

        $employeeSheet = IOFactory::load($response->getFile()->getPathname())->getSheet(2);

        $this->assertSame('L200', $employeeSheet->getCell('B11')->getValue());
        $this->assertEquals(6, $employeeSheet->getCell('P11')->getCalculatedValue());
        $this->assertEquals(2, $employeeSheet->getCell('Q11')->getCalculatedValue());
        $this->assertSame('-', $employeeSheet->getCell('R11')->getValue());
        $this->assertEquals(8, $employeeSheet->getCell('T11')->getCalculatedValue());

        $this->assertSame('L100', $employeeSheet->getCell('B12')->getValue());
        $this->assertEquals(8, $employeeSheet->getCell('R12')->getCalculatedValue());
        $this->assertEquals(8, $employeeSheet->getCell('T12')->getCalculatedValue());

        $this->assertEquals(8, $employeeSheet->getCell('R17')->getCalculatedValue());
        $this->assertEquals(16, $employeeSheet->getCell('T17')->getCalculatedValue());
    }

    public function test_excel_export_includes_grouped_project_weekly_summary_sheet(): void
    {
        $department = $this->department();
        $period = $this->openPeriod();
        $nextPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $projectA = $this->project([
            'project_code' => 'P100',
            'project_name' => 'Detailed Engineering Services  for Pipeline Upgrade and Facility Modification Works',
            'client_name' => 'ADNOC',
        ]);
        $projectB = $this->project([
            'project_code' => 'P200',
            'project_name' => 'Control Room Fit Out',
            'client_name' => 'ADNOC',
        ]);
        $employeeA = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Ben Carter',
            'employee_code' => 'EMP-001',
            'initials' => 'BC',
            'job_title' => 'Senior Engineer',
        ]);
        $employeeB = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Alice Santos',
            'employee_code' => 'EMP-002',
            'initials' => null,
            'job_title' => null,
        ]);
        $timesheetA = $this->submittedTimesheet($employeeA, $period, $projectA, ['status' => 'approved']);
        $timesheetB = $this->submittedTimesheet($employeeB, $period, $projectA, ['status' => 'approved']);
        $timesheetC = $this->submittedTimesheet($employeeA, $nextPeriod, $projectA, ['status' => 'approved']);

        TimesheetEntry::create([
            'timesheet_id' => $timesheetA->id,
            'work_date' => '2026-05-12',
            'day_name' => 'Tuesday',
            'attendance_code' => 'O100',
            'project_id' => $projectA->id,
            'regular_hours' => 0,
            'overtime_hours' => 2,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheetB->id,
            'work_date' => '2026-05-13',
            'day_name' => 'Wednesday',
            'attendance_code' => 'O100',
            'project_id' => $projectB->id,
            'regular_hours' => 3,
            'overtime_hours' => 4,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheetB->id,
            'work_date' => '2026-05-14',
            'day_name' => 'Thursday',
            'attendance_code' => null,
            'project_id' => null,
            'regular_hours' => 6,
            'overtime_hours' => 0,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheetC->id,
            'work_date' => '2026-05-19',
            'day_name' => 'Tuesday',
            'attendance_code' => 'O100',
            'project_id' => $projectA->id,
            'regular_hours' => 1,
            'overtime_hours' => 3,
        ]);

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', ['year' => 2026]));

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $weekly = $spreadsheet->getSheet(0);

        $this->assertSame('Project Weekly Summary', $weekly->getTitle());
        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertTrue($weekly->getStyle('A3')->getAlignment()->getWrapText());
        $this->assertGreaterThan(20, $weekly->getRowDimension(3)->getRowHeight());
        $this->assertSame(34.0, $weekly->getColumnDimension('C')->getWidth());

        $this->assertStringContainsString('P100 - Detailed Engineering Services', $weekly->getCell('A3')->getValue());
        $this->assertStringContainsString("\n", $weekly->getCell('A3')->getValue());
        $this->assertStringContainsString('Client: ADNOC', $weekly->getCell('A3')->getValue());
        $this->assertStringNotContainsString('Services  for', $weekly->getCell('A3')->getValue());
        $this->assertStringContainsString('Week 20, 2026', $weekly->getCell('E4')->getValue());
        $this->assertStringContainsString('11-May-26 to 17-May-26', $weekly->getCell('E4')->getValue());
        $this->assertStringContainsString('Week 21, 2026', $weekly->getCell('H4')->getValue());
        $this->assertGreaterThanOrEqual(36, $weekly->getRowDimension(4)->getRowHeight());
        $this->assertContains('E4:G4', $weekly->getMergeCells());
        $this->assertContains('H4:J4', $weekly->getMergeCells());
        $this->assertContains('K4:M4', $weekly->getMergeCells());
        $this->assertSame('Selected Period Total', $weekly->getCell('K4')->getValue());
        $this->assertSame('Employee ID', $weekly->getCell('A5')->getValue());
        $this->assertSame('Job Title', $weekly->getCell('D5')->getValue());
        $this->assertSame('FFF8CBAD', $weekly->getStyle('K4')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFFCE4D6', $weekly->getStyle('K6')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFF8DFD0', $weekly->getStyle('K8')->getFill()->getStartColor()->getARGB());
        $this->assertSame(Border::BORDER_THICK, $weekly->getStyle('K6')->getBorders()->getLeft()->getBorderStyle());
        $this->assertSame('EMP-001', $weekly->getCell('A6')->getValue());
        $this->assertSame('BC', $weekly->getCell('B6')->getValue());
        $this->assertSame('Ben Carter', $weekly->getCell('C6')->getValue());
        $this->assertSame('Senior Engineer', $weekly->getCell('D6')->getValue());
        $this->assertEquals(8, $weekly->getCell('E6')->getCalculatedValue());
        $this->assertEquals(2, $weekly->getCell('F6')->getCalculatedValue());
        $this->assertEquals(10, $weekly->getCell('G6')->getCalculatedValue());
        $this->assertEquals(9, $weekly->getCell('H6')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('I6')->getCalculatedValue());
        $this->assertEquals(12, $weekly->getCell('J6')->getCalculatedValue());
        $this->assertEquals(17, $weekly->getCell('K6')->getCalculatedValue());
        $this->assertEquals(5, $weekly->getCell('L6')->getCalculatedValue());
        $this->assertEquals(22, $weekly->getCell('M6')->getCalculatedValue());
        $this->assertSame('Alice Santos', $weekly->getCell('C7')->getValue());
        $this->assertSame('-', $weekly->getCell('D7')->getValue());
        $this->assertSame('AS', $weekly->getCell('B7')->getValue());
        $this->assertEquals(0, $weekly->getCell('H7')->getCalculatedValue());
        $this->assertEquals(0, $weekly->getCell('I7')->getCalculatedValue());
        $this->assertEquals(0, $weekly->getCell('J7')->getCalculatedValue());
        $this->assertEquals(8, $weekly->getCell('K7')->getCalculatedValue());
        $this->assertEquals(0, $weekly->getCell('L7')->getCalculatedValue());
        $this->assertEquals(8, $weekly->getCell('M7')->getCalculatedValue());
        $this->assertSame('Project Total', $weekly->getCell('A8')->getValue());
        $this->assertEquals(16, $weekly->getCell('E8')->getCalculatedValue());
        $this->assertEquals(2, $weekly->getCell('F8')->getCalculatedValue());
        $this->assertEquals(18, $weekly->getCell('G8')->getCalculatedValue());
        $this->assertEquals(9, $weekly->getCell('H8')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('I8')->getCalculatedValue());
        $this->assertEquals(12, $weekly->getCell('J8')->getCalculatedValue());
        $this->assertEquals(25, $weekly->getCell('K8')->getCalculatedValue());
        $this->assertEquals(5, $weekly->getCell('L8')->getCalculatedValue());
        $this->assertEquals(30, $weekly->getCell('M8')->getCalculatedValue());
        $this->assertStringContainsString('P200 - Control Room Fit Out', $weekly->getCell('A10')->getValue());
        $this->assertSame('Grand Total', $weekly->getCell('A16')->getValue());
        $this->assertEquals(19, $weekly->getCell('E16')->getCalculatedValue());
        $this->assertEquals(6, $weekly->getCell('F16')->getCalculatedValue());
        $this->assertEquals(25, $weekly->getCell('G16')->getCalculatedValue());
        $this->assertEquals(9, $weekly->getCell('H16')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('I16')->getCalculatedValue());
        $this->assertEquals(12, $weekly->getCell('J16')->getCalculatedValue());
        $this->assertEquals(28, $weekly->getCell('K16')->getCalculatedValue());
        $this->assertEquals(9, $weekly->getCell('L16')->getCalculatedValue());
        $this->assertEquals(37, $weekly->getCell('M16')->getCalculatedValue());
    }

    public function test_excel_export_includes_attendance_summary_for_leave_and_non_project_hours(): void
    {
        $department = $this->department(['name' => 'Engineering']);
        $period = $this->openPeriod();
        $project = $this->project(['project_code' => 'P400']);
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Leave User',
            'employee_code' => 'EMP-004',
            'initials' => 'LU',
            'job_title' => 'Designer',
        ]);
        $timesheet = $this->submittedTimesheet($employee, $period, $project, ['status' => 'approved']);

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-12',
            'day_name' => 'Tuesday',
            'attendance_code' => 'L100',
            'project_id' => null,
            'regular_hours' => 8,
            'overtime_hours' => 0,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-13',
            'day_name' => 'Wednesday',
            'attendance_code' => 'L140',
            'project_id' => null,
            'regular_hours' => 4,
            'overtime_hours' => 0,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-14',
            'day_name' => 'Thursday',
            'attendance_code' => 'O100',
            'project_id' => null,
            'regular_hours' => 2,
            'overtime_hours' => 1,
        ]);

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
        ]));

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $projectSummary = $spreadsheet->getSheet(0);
        $attendanceSummary = $spreadsheet->getSheet(1);

        $this->assertSame('Attendance Code Summary', $attendanceSummary->getTitle());
        $this->assertEquals(8, $projectSummary->getCell('E6')->getCalculatedValue());
        $this->assertEquals(8, $projectSummary->getCell('G6')->getCalculatedValue());

        $this->assertSame('L100 - Annual Leave', $attendanceSummary->getCell('A3')->getValue());
        $this->assertStringContainsString('Week 20, 2026', $attendanceSummary->getCell('H4')->getValue());
        $this->assertSame('Employee ID', $attendanceSummary->getCell('A5')->getValue());
        $this->assertSame('EMP-004', $attendanceSummary->getCell('A6')->getValue());
        $this->assertSame('LU', $attendanceSummary->getCell('B6')->getValue());
        $this->assertSame('Leave User', $attendanceSummary->getCell('C6')->getValue());
        $this->assertSame('Engineering', $attendanceSummary->getCell('D6')->getValue());
        $this->assertSame('Designer', $attendanceSummary->getCell('E6')->getValue());
        $this->assertSame('Non-project', $attendanceSummary->getCell('F6')->getValue());
        $this->assertEquals(8, $attendanceSummary->getCell('H6')->getCalculatedValue());
        $this->assertEquals(0, $attendanceSummary->getCell('I6')->getCalculatedValue());
        $this->assertEquals(8, $attendanceSummary->getCell('J6')->getCalculatedValue());

        $this->assertSame('Attendance Code Total', $attendanceSummary->getCell('A7')->getValue());
        $this->assertEquals(8, $attendanceSummary->getCell('H7')->getCalculatedValue());
        $this->assertSame('L140 - Paid Holiday Leave', $attendanceSummary->getCell('A9')->getValue());
        $this->assertEquals(4, $attendanceSummary->getCell('H12')->getCalculatedValue());
        $this->assertSame('O100 - Office', $attendanceSummary->getCell('A15')->getValue());
        $this->assertEquals(2, $attendanceSummary->getCell('H18')->getCalculatedValue());
        $this->assertEquals(1, $attendanceSummary->getCell('I18')->getCalculatedValue());
        $this->assertSame('Grand Total', $attendanceSummary->getCell('A21')->getValue());
        $this->assertEquals(14, $attendanceSummary->getCell('H21')->getCalculatedValue());
        $this->assertEquals(1, $attendanceSummary->getCell('I21')->getCalculatedValue());
        $this->assertEquals(15, $attendanceSummary->getCell('J21')->getCalculatedValue());
    }

    public function test_attendance_summary_places_multiple_weeks_side_by_side_with_totals(): void
    {
        $department = $this->department(['name' => 'Engineering']);
        $week20 = $this->openPeriod();
        $week21 = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $project = $this->project(['project_code' => 'P401']);
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Leave Range User',
            'employee_code' => 'EMP-005',
            'initials' => 'LRU',
            'job_title' => 'Engineer',
        ]);
        $week20Timesheet = $this->submittedTimesheet($employee, $week20, $project, ['status' => 'approved']);
        $week21Timesheet = $this->submittedTimesheet($employee, $week21, $project, ['status' => 'approved']);

        TimesheetEntry::create([
            'timesheet_id' => $week20Timesheet->id,
            'work_date' => '2026-05-12',
            'day_name' => 'Tuesday',
            'attendance_code' => 'L100',
            'project_id' => null,
            'regular_hours' => 8,
            'overtime_hours' => 0,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $week21Timesheet->id,
            'work_date' => '2026-05-19',
            'day_name' => 'Tuesday',
            'attendance_code' => 'L100',
            'project_id' => null,
            'regular_hours' => 4,
            'overtime_hours' => 2,
        ]);

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_from' => 20,
            'week_to' => 21,
            'year' => 2026,
        ]));

        $response->assertOk();

        $attendanceSummary = IOFactory::load($response->getFile()->getPathname())->getSheet(1);

        $this->assertSame('L100 - Annual Leave', $attendanceSummary->getCell('A3')->getValue());
        $this->assertStringContainsString('Week 20, 2026', $attendanceSummary->getCell('H4')->getValue());
        $this->assertStringContainsString('Week 21, 2026', $attendanceSummary->getCell('K4')->getValue());
        $this->assertSame('Selected Period Total', $attendanceSummary->getCell('N4')->getValue());
        $this->assertContains('H4:J4', $attendanceSummary->getMergeCells());
        $this->assertContains('K4:M4', $attendanceSummary->getMergeCells());
        $this->assertContains('N4:P4', $attendanceSummary->getMergeCells());
        $this->assertSame('EMP-005', $attendanceSummary->getCell('A6')->getValue());
        $this->assertEquals(8, $attendanceSummary->getCell('H6')->getCalculatedValue());
        $this->assertEquals(0, $attendanceSummary->getCell('I6')->getCalculatedValue());
        $this->assertEquals(8, $attendanceSummary->getCell('J6')->getCalculatedValue());
        $this->assertEquals(4, $attendanceSummary->getCell('K6')->getCalculatedValue());
        $this->assertEquals(2, $attendanceSummary->getCell('L6')->getCalculatedValue());
        $this->assertEquals(6, $attendanceSummary->getCell('M6')->getCalculatedValue());
        $this->assertEquals(12, $attendanceSummary->getCell('N6')->getCalculatedValue());
        $this->assertEquals(2, $attendanceSummary->getCell('O6')->getCalculatedValue());
        $this->assertEquals(14, $attendanceSummary->getCell('P6')->getCalculatedValue());
        $this->assertSame('Grand Total', $attendanceSummary->getCell('A9')->getValue());
        $this->assertEquals(12, $attendanceSummary->getCell('N9')->getCalculatedValue());
        $this->assertEquals(2, $attendanceSummary->getCell('O9')->getCalculatedValue());
        $this->assertEquals(14, $attendanceSummary->getCell('P9')->getCalculatedValue());
    }

    public function test_excel_project_summaries_ignore_entries_without_project_or_hours(): void
    {
        $department = $this->department();
        $period = $this->openPeriod();
        $project = $this->project(['project_code' => 'P300']);
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'No Hours',
            'employee_code' => 'EMP-003',
        ]);
        $timesheet = $this->submittedTimesheet($employee, $period, $project, ['status' => 'approved']);
        $timesheet->entries()->delete();

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-11',
            'day_name' => 'Monday',
            'attendance_code' => 'O100',
            'project_id' => $project->id,
            'regular_hours' => 0,
            'overtime_hours' => 0,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => '2026-05-12',
            'day_name' => 'Tuesday',
            'attendance_code' => 'O100',
            'project_id' => null,
            'regular_hours' => 8,
            'overtime_hours' => 0,
        ]);

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
            'include_employee_sheets' => 1,
        ]));

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        $this->assertSame('No project hours found for the selected filters.', $spreadsheet->getSheet(0)->getCell('A3')->getValue());
        $this->assertSame('Grand Total', $spreadsheet->getSheet(0)->getCell('A4')->getValue());
        $this->assertEquals(0, $spreadsheet->getSheet(0)->getCell('E4')->getCalculatedValue());
        $this->assertSame('Attendance Code Summary', $spreadsheet->getSheet(1)->getTitle());
        $this->assertSame('O100 - Office', $spreadsheet->getSheet(1)->getCell('A3')->getValue());
        $this->assertSame('Non-project', $spreadsheet->getSheet(1)->getCell('F6')->getValue());
        $this->assertEquals(8, $spreadsheet->getSheet(1)->getCell('H6')->getCalculatedValue());
        $this->assertSame('No Hours W20', $spreadsheet->getSheet(2)->getTitle());
    }

    public function test_admin_can_filter_and_export_project_summary_by_project_and_week_range(): void
    {
        $department = $this->department();
        $projectA = $this->project(['project_code' => 'PX-100', 'project_name' => 'Selected Project']);
        $projectB = $this->project(['project_code' => 'PX-200', 'project_name' => 'Other Project']);
        $week12 = $this->openPeriod(['week_number' => 12, 'start_date' => '2026-03-16', 'end_date' => '2026-03-22']);
        $week13 = $this->openPeriod(['week_number' => 13, 'start_date' => '2026-03-23', 'end_date' => '2026-03-29']);
        $week15 = $this->openPeriod(['week_number' => 15, 'start_date' => '2026-04-06', 'end_date' => '2026-04-12']);
        $employeeA = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Project Worker',
            'employee_code' => 'EMP-P100',
            'initials' => 'PW',
            'job_title' => 'Project Manager',
        ]);
        $employeeB = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Other Worker',
            'employee_code' => 'EMP-P200',
            'initials' => 'OW',
        ]);

        $week12Timesheet = $this->submittedTimesheet($employeeA, $week12, $projectA, ['status' => 'approved']);
        $week15Timesheet = $this->submittedTimesheet($employeeA, $week15, $projectA, ['status' => 'approved']);
        $this->submittedTimesheet($employeeB, $week13, $projectB, ['status' => 'approved']);

        TimesheetEntry::create([
            'timesheet_id' => $week15Timesheet->id,
            'work_date' => '2026-04-07',
            'day_name' => 'Tuesday',
            'attendance_code' => 'O100',
            'project_id' => $projectA->id,
            'regular_hours' => 0,
            'overtime_hours' => 3,
        ]);

        TimesheetEntry::create([
            'timesheet_id' => $week12Timesheet->id,
            'work_date' => '2026-03-17',
            'day_name' => 'Tuesday',
            'attendance_code' => 'O100',
            'project_id' => $projectB->id,
            'regular_hours' => 6,
            'overtime_hours' => 0,
        ]);

        $admin = $this->userWithRole('admin');
        $filters = [
            'week_from' => 12,
            'week_to' => 15,
            'year' => 2026,
            'project_id' => $projectA->id,
        ];

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', $filters))
            ->assertOk()
            ->assertSee('Showing configured dates from')
            ->assertSee('Mar 16, 2026')
            ->assertSee('Apr 12, 2026')
            ->assertSee('Week: 12 to 15')
            ->assertSee('Dates: Mar 16, 2026 to Apr 12, 2026')
            ->assertSee('Project: PX-100')
            ->assertSee('Project Worker')
            ->assertDontSee('<td class="fw-semibold">Other Worker</td>', false);

        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', $filters));

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $weekly = $spreadsheet->getSheet(0);
        $this->assertSame(2, $spreadsheet->getSheetCount());

        $this->assertStringContainsString('PX-100 - Selected Project', $weekly->getCell('A3')->getValue());
        $this->assertStringContainsString('Week 12, 2026', $weekly->getCell('E4')->getValue());
        $this->assertStringContainsString('16-Mar-26 to 22-Mar-26', $weekly->getCell('E4')->getValue());
        $this->assertStringContainsString('Week 15, 2026', $weekly->getCell('H4')->getValue());
        $this->assertStringContainsString('06-Apr-26 to 12-Apr-26', $weekly->getCell('H4')->getValue());
        $this->assertSame('Selected Period Total', $weekly->getCell('K4')->getValue());
        $this->assertContains('K4:M4', $weekly->getMergeCells());
        $this->assertSame('EMP-P100', $weekly->getCell('A6')->getValue());
        $this->assertSame('Project Manager', $weekly->getCell('D6')->getValue());
        $this->assertEquals(8, $weekly->getCell('E6')->getCalculatedValue());
        $this->assertEquals(0, $weekly->getCell('F6')->getCalculatedValue());
        $this->assertEquals(8, $weekly->getCell('G6')->getCalculatedValue());
        $this->assertEquals(8, $weekly->getCell('H6')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('I6')->getCalculatedValue());
        $this->assertEquals(11, $weekly->getCell('J6')->getCalculatedValue());
        $this->assertEquals(16, $weekly->getCell('K6')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('L6')->getCalculatedValue());
        $this->assertEquals(19, $weekly->getCell('M6')->getCalculatedValue());
        $this->assertSame('Project Total', $weekly->getCell('A7')->getValue());
        $this->assertEquals(16, $weekly->getCell('K7')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('L7')->getCalculatedValue());
        $this->assertEquals(19, $weekly->getCell('M7')->getCalculatedValue());
        $this->assertSame('Grand Total', $weekly->getCell('A9')->getValue());
        $this->assertEquals(8, $weekly->getCell('E9')->getCalculatedValue());
        $this->assertEquals(0, $weekly->getCell('F9')->getCalculatedValue());
        $this->assertEquals(8, $weekly->getCell('G9')->getCalculatedValue());
        $this->assertEquals(8, $weekly->getCell('H9')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('I9')->getCalculatedValue());
        $this->assertEquals(11, $weekly->getCell('J9')->getCalculatedValue());
        $this->assertEquals(16, $weekly->getCell('K9')->getCalculatedValue());
        $this->assertEquals(3, $weekly->getCell('L9')->getCalculatedValue());
        $this->assertEquals(19, $weekly->getCell('M9')->getCalculatedValue());

        foreach (range(1, 9) as $row) {
            $this->assertStringNotContainsString('PX-200', (string) $weekly->getCell("A{$row}")->getValue());
            $this->assertStringNotContainsString('Other Worker', (string) $weekly->getCell("C{$row}")->getValue());
        }
    }

    public function test_week_to_requires_from_week_on_admin_timesheet_filters_and_export(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['week_to' => 15, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHasErrors(['week_from']);

        $this->actingAs($admin)
            ->get(route('admin.timesheets.export', ['week_to' => 15, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHasErrors(['week_from']);
    }

    public function test_include_employee_sheets_option_must_be_boolean(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.export', ['include_employee_sheets' => 'sometimes']))
            ->assertRedirect()
            ->assertSessionHasErrors(['include_employee_sheets']);
    }

    public function test_to_week_must_not_be_before_from_week(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['week_from' => 15, 'week_to' => 12, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHasErrors(['week_to']);
    }

    public function test_year_is_required_when_filtering_by_week(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['week_from' => 20]))
            ->assertRedirect()
            ->assertSessionHasErrors(['year']);

        $this->actingAs($admin)
            ->get(route('admin.timesheets.export', ['week_from' => 20]))
            ->assertRedirect()
            ->assertSessionHasErrors(['year']);
    }

    public function test_nonexistent_single_week_period_cannot_be_exported(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.export', ['week_from' => 1, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHasErrors(['week_from']);
    }

    public function test_week_range_requires_at_least_one_existing_period(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['week_from' => 1, 'week_to' => 3, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHasErrors(['week_from']);
    }

    public function test_week_range_can_include_missing_weeks_when_at_least_one_period_exists(): void
    {
        $this->openPeriod(['week_number' => 12, 'start_date' => '2026-03-16', 'end_date' => '2026-03-22']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['week_from' => 12, 'week_to' => 15, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Showing configured dates from')
            ->assertSee('Mar 16, 2026')
            ->assertSee('Some selected weeks do not have configured timesheet periods yet.')
            ->assertSee('Week: 12 to 15');
    }

    public function test_admin_timesheet_filter_summary_handles_no_filters_and_year_without_periods(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index'))
            ->assertOk()
            ->assertSee('Showing all timesheets.')
            ->assertSee('No filters applied')
            ->assertDontSee('Clear');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['year' => 2030]))
            ->assertOk()
            ->assertSee('Filters are active. Add a valid week and year to see the configured date range.')
            ->assertSee('href="'.route('admin.timesheets.index').'"', false)
            ->assertSee('Year: 2030');
    }

    public function test_summary_report_preview_is_available_for_one_to_six_selected_weeks(): void
    {
        $department = $this->department(['name' => 'Preview Department']);
        $project = $this->project(['project_code' => 'PREVIEW-100', 'project_name' => 'Preview Project']);
        $employee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'name' => 'Preview Employee',
            'job_title' => 'Engineer',
        ]);
        $this->submittedTimesheet($employee, $this->openPeriod(), $project, ['status' => 'approved']);
        $admin = $this->userWithRole('admin');
        $filters = [
            'week_from' => 20,
            'week_to' => 25,
            'year' => 2026,
            'status' => 'approved',
        ];

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', $filters))
            ->assertOk()
            ->assertSee('Summary Report Preview')
            ->assertSee('preview=summary', false)
            ->assertDontSee('id="summary-report-preview"', false);

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', array_merge($filters, ['preview' => 'summary'])))
            ->assertOk()
            ->assertSee('id="summary-report-preview"', false)
            ->assertSee('Project Summary')
            ->assertSee('Attendance Summary')
            ->assertSee('PREVIEW-100')
            ->assertSee('Preview Employee')
            ->assertSee('8.00');
    }

    public function test_summary_report_preview_is_hidden_for_more_than_six_selected_weeks(): void
    {
        $this->openPeriod();
        $admin = $this->userWithRole('admin');
        $filters = [
            'week_from' => 20,
            'week_to' => 26,
            'year' => 2026,
        ];

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', $filters))
            ->assertOk()
            ->assertSee('Summary Report Preview is available when you select a Year and 1 to 6 weekly periods.')
            ->assertDontSee('preview=summary', false)
            ->assertDontSee('Please narrow the week range, or use Export Excel for larger reports.')
            ->assertDontSee('id="summary-report-preview"', false);

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', array_merge($filters, ['preview' => 'summary'])))
            ->assertOk()
            ->assertSee('Please narrow the week range, or use Export Excel for larger reports.')
            ->assertDontSee('id="summary-report-preview"', false);
    }

    public function test_admin_can_export_leave_plans_to_excel_with_expected_columns(): void
    {
        $department = $this->department(['name' => 'HR Operations']);
        $employee = $this->userWithRole('employee', [
            'name' => 'Leave Export Employee',
            'employee_code' => 'MEC-HR-2026-777',
            'job_title' => 'HR Coordinator',
            'department_id' => $department->id,
        ]);
        $hod = $this->userWithRole('hod', ['name' => 'HOD Approver']);
        $director = $this->userWithRole('admin', ['name' => 'Director Approver']);
        $hr = $this->userWithRole('admin', ['name' => 'HR Approver']);
        $admin = $this->userWithRole('admin');

        $leavePlan = LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
            'duration_type' => 'full_day',
            'status' => LeavePlan::STATUS_APPROVED,
            'approval_stage' => LeavePlan::APPROVAL_STAGE_HR,
            'submitted_at' => '2026-04-01 08:00:00',
            'hod_approved_by' => $hod->id,
            'hod_approved_at' => '2026-04-02 09:00:00',
            'director_approved_by' => $director->id,
            'director_approved_at' => '2026-04-03 10:00:00',
            'hr_approved_by' => $hr->id,
            'hr_approved_at' => '2026-04-04 11:00:00',
            'approved_by' => $hr->id,
            'approved_at' => '2026-04-04 11:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.leave-plans.export', [
            'year' => 2026,
            'status' => LeavePlan::STATUS_APPROVED,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $sheet = $spreadsheet->getSheet(0);

        $this->assertSame(1, $spreadsheet->getSheetCount());
        $this->assertSame('Leave Plans', $sheet->getTitle());
        $this->assertSame('Employee Name', $sheet->getCell('A1')->getValue());
        $this->assertSame('HR Approved At', $sheet->getCell('T1')->getValue());
        $this->assertNull($sheet->getCell('U1')->getValue());
        $this->assertSame('Leave Export Employee', $sheet->getCell('A2')->getValue());
        $this->assertSame('MEC-HR-2026-777', $sheet->getCell('B2')->getValue());
        $this->assertSame('HR Coordinator', $sheet->getCell('C2')->getValue());
        $this->assertSame('HR Operations', $sheet->getCell('D2')->getValue());
        $this->assertSame('L100', $sheet->getCell('E2')->getValue());
        $this->assertSame('2026-05-11', $sheet->getCell('G2')->getValue());
        $this->assertSame('2026-05-12', $sheet->getCell('H2')->getValue());
        $this->assertEquals(2, $sheet->getCell('K2')->getValue());
        $this->assertSame(LeavePlan::STATUS_APPROVED, $sheet->getCell('L2')->getValue());
        $this->assertSame('Approved by HR', $sheet->getCell('M2')->getValue());
        $this->assertSame('HOD Approver', $sheet->getCell('O2')->getValue());
        $this->assertSame('Director Approver', $sheet->getCell('Q2')->getValue());
        $this->assertSame('HR Approver', $sheet->getCell('S2')->getValue());
        $this->assertSame('2026-04-04 11:00:00', $sheet->getCell('T2')->getValue());
        $removedHeadings = [
            'Leave Plan ID',
            'Final Approved By',
            'Final Approved At',
            'Employee Reason',
            'Updated At',
        ];

        foreach (range('A', 'T') as $column) {
            $this->assertNotContains($sheet->getCell($column.'1')->getValue(), $removedHeadings);
        }

        $this->assertSame('A2', $sheet->getFreezePane());
    }

    public function test_super_admin_can_export_leave_plans_and_filters_include_overlapping_dates(): void
    {
        $department = $this->department(['name' => 'Filtered Department']);
        $otherDepartment = $this->department(['name' => 'Other Department']);
        $employeeA = $this->userWithRole('employee', ['name' => 'Filtered Employee A', 'department_id' => $department->id]);
        $employeeB = $this->userWithRole('employee', ['name' => 'Filtered Employee B', 'department_id' => $department->id]);
        $otherEmployee = $this->userWithRole('employee', ['name' => 'Other Employee', 'department_id' => $otherDepartment->id]);
        $superAdmin = $this->userWithRole('super_admin');

        LeavePlan::factory()->create([
            'user_id' => $employeeA->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'start_date' => '2025-12-30',
            'end_date' => '2026-01-02',
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);
        LeavePlan::factory()->create([
            'user_id' => $employeeB->id,
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'start_date' => '2026-01-15',
            'end_date' => '2026-01-15',
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);
        LeavePlan::factory()->create([
            'user_id' => $otherEmployee->id,
            'department_id' => $otherDepartment->id,
            'attendance_code' => 'L120',
            'start_date' => '2026-01-02',
            'end_date' => '2026-01-02',
            'status' => LeavePlan::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.leave-plans.export', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-10',
            'department_id' => $department->id,
            'attendance_code' => 'L100',
            'status' => LeavePlan::STATUS_SUBMITTED,
            'employee_ids' => [$employeeA->id, $employeeB->id],
        ]));

        $response->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getSheet(0);

        $this->assertSame('Filtered Employee A', $sheet->getCell('A2')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue());
    }

    public function test_leave_plan_export_permissions_empty_results_and_validation(): void
    {
        $employee = $this->userWithRole('employee');
        $hod = $this->userWithRole('hod');
        $admin = $this->userWithRole('admin');

        $this->actingAs($employee)
            ->get(route('admin.leave-plans.export'))
            ->assertForbidden();

        $this->actingAs($hod)
            ->get(route('admin.leave-plans.export'))
            ->assertForbidden();

        $emptyResponse = $this->actingAs($admin)->get(route('admin.leave-plans.export', ['year' => 2026]));
        $emptyResponse->assertOk();

        $emptySheet = IOFactory::load($emptyResponse->getFile()->getPathname())->getSheet(0);
        $this->assertSame('Employee Name', $emptySheet->getCell('A1')->getValue());
        $this->assertSame('HR Approved At', $emptySheet->getCell('T1')->getValue());
        $this->assertNull($emptySheet->getCell('U1')->getValue());
        $this->assertNull($emptySheet->getCell('A2')->getValue());

        $this->actingAs($admin)
            ->from(route('admin.leave-plans.index'))
            ->get(route('admin.leave-plans.export', [
                'date_from' => '2026-05-20',
                'date_to' => '2026-05-10',
            ]))
            ->assertRedirect(route('admin.leave-plans.index'))
            ->assertSessionHasErrors('date_to');

        $this->actingAs($admin)
            ->from(route('admin.leave-plans.index'))
            ->get(route('admin.leave-plans.index', ['year' => 1999]))
            ->assertRedirect(route('admin.leave-plans.index'))
            ->assertSessionHasErrors('year');
    }

    public function test_admin_leave_plan_index_shows_export_controls_and_date_filter_badges(): void
    {
        $department = $this->department(['name' => 'Leave Badge Department']);
        $employee = $this->userWithRole('employee', ['name' => 'Leave Badge Employee', 'department_id' => $department->id]);
        LeavePlan::factory()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'status' => LeavePlan::STATUS_APPROVED,
        ]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.leave-plans.index', [
                'year' => 2026,
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'department_id' => $department->id,
                'employee_ids' => [$employee->id],
            ]))
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('/admin/leave-plans/export?year=2026', false)
            ->assertSee('date_from=2026-05-01', false)
            ->assertSee('date_to=2026-05-31', false)
            ->assertSee('department_id='.$department->id, false)
            ->assertSee('employee_ids%5B0%5D='.$employee->id, false)
            ->assertSee('Export started. Your Excel file will download when ready.')
            ->assertSee('Year: 2026')
            ->assertSee('From: 2026-05-01')
            ->assertSee('To: 2026-05-31')
            ->assertSee('Leave Badge Department')
            ->assertSee('Employee: Leave Badge Employee');
    }

    public function test_admin_leave_plan_export_is_throttled(): void
    {
        $admin = $this->userWithRole('admin');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($admin)
                ->withServerVariables(['REMOTE_ADDR' => '10.20.30.30'])
                ->get(route('admin.leave-plans.export'))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->from(route('admin.leave-plans.index'))
            ->withServerVariables(['REMOTE_ADDR' => '10.20.30.30'])
            ->get(route('admin.leave-plans.export'))
            ->assertRedirect(route('admin.leave-plans.index'))
            ->assertSessionHas('warning');
    }

    public function test_admin_leave_plan_export_warns_when_export_is_already_running(): void
    {
        $admin = $this->userWithRole('admin');
        $lock = Cache::lock('exports:user:'.$admin->id, 120);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($admin)
                ->from(route('admin.leave-plans.index'))
                ->get(route('admin.leave-plans.export'))
                ->assertRedirect(route('admin.leave-plans.index'))
                ->assertSessionHas('warning', 'An export is already running. Please wait for it to finish before starting another export.');
        } finally {
            $lock->release();
        }
    }

    public function test_summary_report_preview_requires_week_year_and_submitted_statuses(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index'))
            ->assertOk()
            ->assertSee('Summary Report Preview is available when you select a Year and 1 to 6 weekly periods.')
            ->assertDontSee('preview=summary', false);

        $this->openPeriod();

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', [
                'status' => 'not_submitted',
                'week_from' => 20,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertDontSee('Summary Report Preview is available when you select a Year and 1 to 6 weekly periods.')
            ->assertDontSee('Summary Report Preview is not available for Not Submitted status.')
            ->assertDontSee('preview=summary', false);
    }
}
