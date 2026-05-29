<?php

namespace Tests\Feature;

use App\Models\AutomationSetting;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ManagementWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_super_admin_can_create_user_with_valid_employee_number(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'New Employee',
            'email' => 'new.employee@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-095',
            'initials' => 'NE',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.employee@example.com',
            'employee_code' => 'MEC-HR-2026-095',
            'initials' => 'NE',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_created']);
    }

    public function test_super_admin_can_create_user_with_phil_employee_number(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'Phil Employee',
            'email' => 'phil.employee@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-PHIL-HR-2026-095',
            'initials' => 'PE',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'phil.employee@example.com',
            'employee_code' => 'MEC-PHIL-HR-2026-095',
        ]);
    }

    public function test_user_initials_are_optional_but_limited(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'No Initials Employee',
            'email' => 'no.initials@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-096',
            'initials' => '',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'no.initials@example.com',
            'initials' => null,
        ]);

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'Long Initials Employee',
            'email' => 'long.initials@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-097',
            'initials' => str_repeat('A', 21),
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertSessionHasErrors('initials');
    }

    public function test_database_seeder_can_be_rerun_without_duplicate_errors(): void
    {
        $this->seed(DatabaseSeeder::class);

        Department::where('name', 'Operations')->firstOrFail()->update(['code' => 'CUSTOM-OPS']);
        User::where('email', 'aisha@example.com')->firstOrFail()->update(['name' => 'Production Aisha']);
        AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->firstOrFail()->update(['is_enabled' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Department::where('name', 'Operations')->count());
        $this->assertSame(1, Department::where('name', 'Engineering')->count());
        $this->assertSame(1, User::where('email', 'superadmin@example.com')->count());
        $this->assertSame(1, User::where('email', 'aisha@example.com')->count());
        $this->assertSame(1, AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->count());
        $this->assertSame('CUSTOM-OPS', Department::where('name', 'Operations')->firstOrFail()->code);
        $this->assertSame('Production Aisha', User::where('email', 'aisha@example.com')->firstOrFail()->name);
        $this->assertFalse(AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->firstOrFail()->is_enabled);
    }

    public function test_invalid_employee_number_is_rejected(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'Bad Employee',
            'email' => 'bad.employee@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-9',
            'role' => 'employee',
            'is_active' => '1',
        ])->assertSessionHasErrors('employee_code');
    }

    public function test_super_admin_cannot_delete_own_account(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->delete(route('manage.users.destroy', $superAdmin))
            ->assertForbidden();
    }

    public function test_deleting_hod_requires_and_applies_replacement_hod(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();
        $oldHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $newHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $oldHod->id]);

        $this->actingAs($superAdmin)
            ->delete(route('manage.users.destroy', $oldHod))
            ->assertSessionHasErrors('replacement_hod_id');

        $this->actingAs($superAdmin)
            ->delete(route('manage.users.destroy', $oldHod), ['replacement_hod_id' => $newHod->id])
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $oldHod->id]);
        $this->assertSame($newHod->id, $department->refresh()->hod_id);
    }

    public function test_deleting_user_removes_related_timesheets_and_entries(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $entryId = $timesheet->entries()->firstOrFail()->id;

        $this->actingAs($superAdmin)
            ->delete(route('manage.users.destroy', $employee))
            ->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseMissing('timesheets', ['id' => $timesheet->id]);
        $this->assertDatabaseMissing('timesheet_entries', ['id' => $entryId]);
    }

    public function test_department_can_be_deactivated_but_used_department_cannot_be_deleted(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department(['is_active' => true]);
        $this->userWithRole('employee', ['department_id' => $department->id]);

        $this->actingAs($superAdmin)
            ->patch(route('manage.departments.status', $department))
            ->assertRedirect(route('manage.departments.index'));

        $this->assertFalse($department->refresh()->is_active);

        $this->actingAs($superAdmin)
            ->delete(route('manage.departments.destroy', $department))
            ->assertRedirect(route('manage.departments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_unused_department_can_be_deleted(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->actingAs($superAdmin)
            ->delete(route('manage.departments.destroy', $department))
            ->assertRedirect(route('manage.departments.index'));

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_project_can_be_deactivated_but_used_project_cannot_be_deleted(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project(['is_active' => true]);
        $this->submittedTimesheet($employee, $period, $project);

        $this->actingAs($superAdmin)
            ->patch(route('manage.projects.status', $project))
            ->assertRedirect(route('manage.projects.index'));

        $this->assertFalse($project->refresh()->is_active);

        $this->actingAs($superAdmin)
            ->delete(route('manage.projects.destroy', $project))
            ->assertRedirect(route('manage.projects.index'))
            ->assertSessionHas('error');
    }

    public function test_unused_project_can_be_deleted(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $project = $this->project();

        $this->actingAs($superAdmin)
            ->delete(route('manage.projects.destroy', $project))
            ->assertRedirect(route('manage.projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_period_validation_requires_monday_to_sunday_week(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)->post(route('manage.periods.store'), [
            'week_number' => 21,
            'year' => 2026,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-18',
            'status' => 'open',
        ])->assertSessionHasErrors(['start_date', 'end_date']);

        $this->actingAs($superAdmin)->post(route('manage.periods.store'), [
            'week_number' => 20,
            'year' => 2026,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-17',
            'status' => 'open',
        ])->assertRedirect(route('manage.periods.index'));

        $this->assertDatabaseHas('timesheet_periods', [
            'week_number' => 20,
            'year' => 2026,
        ]);
    }

    public function test_period_form_guides_super_admin_with_auto_calculated_fields(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('manage.periods.create'))
            ->assertOk()
            ->assertSee('Select the Monday start date')
            ->assertSee('data-period-start', false)
            ->assertSee('data-period-end', false)
            ->assertSee('data-period-week', false)
            ->assertSee('data-period-year', false);
    }

    public function test_super_admin_can_toggle_automation_settings(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $automation = AutomationSetting::where('key', AutomationSetting::TIMESHEET_MISSING_REMINDERS)->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(route('manage.automations.index'))
            ->assertOk()
            ->assertSee('Automation Controls')
            ->assertSee('Missing Timesheet Reminders');

        $this->actingAs($superAdmin)
            ->patch(route('manage.automations.toggle', $automation))
            ->assertRedirect(route('manage.automations.index'))
            ->assertSessionHas('success');

        $this->assertFalse($automation->refresh()->is_enabled);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'automation_disabled',
            'auditable_type' => AutomationSetting::class,
            'auditable_id' => $automation->id,
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('manage.automations.toggle', $automation))
            ->assertRedirect(route('manage.automations.index'));

        $this->assertTrue($automation->refresh()->is_enabled);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'automation_enabled',
            'auditable_type' => AutomationSetting::class,
            'auditable_id' => $automation->id,
        ]);
    }

    public function test_management_index_pages_render_for_super_admin(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        Department::factory()->create();
        Project::factory()->create();
        TimesheetPeriod::create([
            'week_number' => 20,
            'year' => 2026,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-17',
            'status' => 'open',
        ]);

        $this->actingAs($superAdmin)->get(route('manage.departments.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('manage.projects.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('manage.periods.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('manage.automations.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('manage.audit-logs.index'))->assertOk();
    }

    public function test_super_admin_can_download_filtered_audit_logs_excel_export(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $user = $this->userWithRole('employee', [
            'name' => '=Formula Guard',
            'email' => 'formula.guard@example.com',
        ]);

        $matchingLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'user_updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => '=Formula Guard', 'notes' => str_repeat('A', 40000)],
            'ip_address' => '127.0.0.1',
        ]);
        $matchingLog->forceFill([
            'created_at' => '2026-05-15 10:30:00',
            'updated_at' => '2026-05-15 10:30:00',
        ])->save();

        $otherLog = AuditLog::create([
            'user_id' => null,
            'action' => 'project_created',
            'auditable_type' => Project::class,
            'auditable_id' => 999,
            'old_values' => null,
            'new_values' => ['project_code' => 'P999'],
            'ip_address' => null,
        ]);
        $otherLog->forceFill([
            'created_at' => '2026-05-16 09:00:00',
            'updated_at' => '2026-05-16 09:00:00',
        ])->save();

        $response = $this->actingAs($superAdmin)->get(route('manage.audit-logs.export', [
            'action' => 'user_updated',
            'user_id' => $user->id,
            'date_from' => '2026-05-15',
            'date_to' => '2026-05-15',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Date', $sheet->getCell('A1')->getValue());
        $this->assertSame("'=Formula Guard", $sheet->getCell('B2')->getValue());
        $this->assertSame('formula.guard@example.com', $sheet->getCell('C2')->getValue());
        $this->assertSame('user_updated', $sheet->getCell('D2')->getValue());
        $this->assertSame('User', $sheet->getCell('E2')->getValue());
        $this->assertStringContainsString('[truncated]', $sheet->getCell('H2')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue());
    }

    public function test_audit_log_export_rejects_invalid_date_ranges(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->from(route('manage.audit-logs.index'))
            ->get(route('manage.audit-logs.export', [
                'date_from' => '2026-05-20',
                'date_to' => '2026-05-10',
            ]))
            ->assertRedirect(route('manage.audit-logs.index'))
            ->assertSessionHasErrors('date_to');
    }
}
