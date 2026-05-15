<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), ['status' => 'approved']);
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.timesheets.export', [
            'week_number' => 20,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }
}
