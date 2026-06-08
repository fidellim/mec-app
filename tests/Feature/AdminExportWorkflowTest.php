<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
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

    public function test_admin_cannot_approve_non_hod_timesheets(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.timesheets.approve', $timesheet))->assertForbidden();
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
}
