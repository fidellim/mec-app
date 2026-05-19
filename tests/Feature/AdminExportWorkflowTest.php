<?php

namespace Tests\Feature;

use App\Models\TimesheetEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AdminExportWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_admin_can_view_all_timesheets_and_filter_by_status(): void
    {
        $department = $this->department(['name' => 'Operations']);
        $employee = $this->userWithRole('employee', [
            'name' => 'Ben Carter',
            'department_id' => $department->id,
        ]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.timesheets.index', ['status' => $timesheet->status]))
            ->assertOk()
            ->assertSee('Ben Carter')
            ->assertSee('Operations');
    }

    public function test_admin_cannot_use_super_admin_approval_actions(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.timesheets.approve', $timesheet))->assertForbidden();
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

    public function test_admin_can_download_excel_export(): void
    {
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'initials' => 'ZX',
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
        $this->assertSame('ZX', $spreadsheet->getSheet(1)->getCell('B4')->getValue());
    }

    public function test_excel_export_includes_project_summary_sheet_with_combined_hours(): void
    {
        $department = $this->department();
        $period = $this->openPeriod();
        $projectA = $this->project([
            'project_code' => 'P100',
            'project_name' => 'Pipeline Upgrade',
            'client_name' => 'ADNOC',
        ]);
        $projectB = $this->project([
            'project_code' => 'P200',
            'project_name' => 'Control Room Fit Out',
            'client_name' => 'ADNOC',
        ]);
        $employeeA = $this->userWithRole('employee', ['department_id' => $department->id]);
        $employeeB = $this->userWithRole('employee', ['department_id' => $department->id]);
        $timesheetA = $this->submittedTimesheet($employeeA, $period, $projectA, ['status' => 'approved']);
        $timesheetB = $this->submittedTimesheet($employeeB, $period, $projectA, ['status' => 'approved']);

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

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
        ]));

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $summary = $spreadsheet->getSheet(0);

        $this->assertSame('Project Summary', $summary->getTitle());
        $this->assertSame('P100', $summary->getCell('A4')->getValue());
        $this->assertSame('Pipeline Upgrade', $summary->getCell('B4')->getValue());
        $this->assertEquals(16, $summary->getCell('D4')->getCalculatedValue());
        $this->assertEquals(2, $summary->getCell('E4')->getCalculatedValue());
        $this->assertEquals(18, $summary->getCell('F4')->getCalculatedValue());
        $this->assertSame('P200', $summary->getCell('A5')->getValue());
        $this->assertEquals(3, $summary->getCell('D5')->getCalculatedValue());
        $this->assertEquals(4, $summary->getCell('E5')->getCalculatedValue());
        $this->assertEquals(7, $summary->getCell('F5')->getCalculatedValue());
        $this->assertSame('Totals', $summary->getCell('A6')->getValue());
        $this->assertEquals(19, $summary->getCell('D6')->getCalculatedValue());
        $this->assertEquals(6, $summary->getCell('E6')->getCalculatedValue());
        $this->assertEquals(25, $summary->getCell('F6')->getCalculatedValue());
    }
}
