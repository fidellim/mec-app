<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class SuperAdminTimesheetVoidWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_super_admin_can_void_approved_timesheet_with_reason_and_employee_can_create_replacement(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $superAdmin = $this->userWithRole('super_admin', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.timesheets.void', $timesheet), [
                'void_reason' => 'Approved hours were charged to the wrong project.',
            ])
            ->assertRedirect(route('admin.timesheets.show', $timesheet))
            ->assertSessionHas('success');

        $timesheet->refresh();
        $this->assertSame('voided', $timesheet->status);
        $this->assertSame($superAdmin->id, $timesheet->voided_by);
        $this->assertNotNull($timesheet->voided_at);
        $this->assertSame('Approved hours were charged to the wrong project.', $timesheet->void_reason);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'action' => 'timesheet_voided',
            'auditable_type' => Timesheet::class,
            'auditable_id' => $timesheet->id,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('2026-05-11');

        $this->actingAs($employee)
            ->post(route('employee.timesheets.store'), [
                'timesheet_period_id' => $period->id,
                'submit' => '1',
                'entries' => $this->validEntries($project, [
                    '2026-05-11' => ['regular_hours' => 7, 'overtime_hours' => 1],
                ]),
            ])
            ->assertRedirect();

        $this->assertSame(2, Timesheet::where('user_id', $employee->id)->where('timesheet_period_id', $period->id)->count());
        $this->assertDatabaseHas('timesheets', [
            'user_id' => $employee->id,
            'timesheet_period_id' => $period->id,
            'status' => 'submitted',
        ]);
    }

    public function test_admin_cannot_void_timesheet(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $admin = $this->userWithRole('admin', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), [
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.timesheets.void', $timesheet), [
                'void_reason' => 'Correction needed.',
            ])
            ->assertForbidden();

        $this->assertSame('approved', $timesheet->refresh()->status);
    }

    public function test_voiding_requires_reason_and_approved_status(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $superAdmin = $this->userWithRole('super_admin', ['department_id' => $department->id]);
        $approved = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project(), [
            'status' => 'approved',
        ]);

        $this->actingAs($superAdmin)
            ->from(route('admin.timesheets.show', $approved))
            ->post(route('admin.timesheets.void', $approved), [
                'void_reason' => '',
            ])
            ->assertRedirect(route('admin.timesheets.show', $approved))
            ->assertSessionHasErrors('void_reason');

        $this->assertSame('approved', $approved->refresh()->status);

        $submitted = $this->submittedTimesheet(
            $employee,
            $this->openPeriod([
                'week_number' => 21,
                'start_date' => '2026-05-18',
                'end_date' => '2026-05-24',
            ]),
            $this->project()
        );

        $this->actingAs($superAdmin)
            ->post(route('admin.timesheets.void', $submitted), [
                'void_reason' => 'Trying to void submitted record.',
            ])
            ->assertStatus(422);

        $this->assertSame('submitted', $submitted->refresh()->status);
    }

    public function test_super_admin_cannot_void_own_timesheet(): void
    {
        $department = $this->department();
        $superAdmin = $this->userWithRole('super_admin', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($superAdmin, $this->openPeriod(), $this->project(), [
            'status' => 'approved',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.timesheets.void', $timesheet), [
                'void_reason' => 'Own correction.',
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame('approved', $timesheet->refresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'timesheet_voided')->count());
    }
}
