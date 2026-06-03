<?php

namespace Tests\Feature;

use App\Models\AutomationSetting;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'job_title' => 'Project Engineer',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.employee@example.com',
            'employee_code' => 'MEC-HR-2026-095',
            'initials' => 'NE',
            'job_title' => 'Project Engineer',
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

    public function test_user_job_title_is_optional_trimmed_and_limited(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $department = $this->department();

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'Titled Employee',
            'email' => 'titled.employee@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-098',
            'job_title' => '  Senior Project Engineer  ',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'titled.employee@example.com',
            'job_title' => 'Senior Project Engineer',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('manage.users.index'))
            ->assertOk()
            ->assertSee('Senior Project Engineer');

        $user = User::where('email', 'titled.employee@example.com')->firstOrFail();

        $this->actingAs($superAdmin)->put(route('manage.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'job_title' => '',
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'titled.employee@example.com',
            'job_title' => null,
        ]);

        $this->actingAs($superAdmin)->post(route('manage.users.store'), [
            'name' => 'Long Title Employee',
            'email' => 'long.title@example.com',
            'password' => 'password123',
            'employee_code' => 'MEC-HR-2026-099',
            'job_title' => str_repeat('A', 101),
            'department_id' => $department->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertSessionHasErrors('job_title');
    }

    public function test_super_admin_can_filter_users_by_department(): void
    {
        $superAdmin = $this->userWithRole('super_admin', ['name' => 'Platform Admin']);
        $operations = $this->department(['name' => 'Operations']);
        $engineering = $this->department(['name' => 'Engineering']);
        $this->userWithRole('employee', [
            'name' => 'Operations Employee',
            'department_id' => $operations->id,
        ]);
        $this->userWithRole('employee', [
            'name' => 'Engineering Employee',
            'department_id' => $engineering->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('manage.users.index', ['department_id' => $operations->id]))
            ->assertOk()
            ->assertSee('Operations Employee')
            ->assertDontSee('Engineering Employee')
            ->assertSee('value="'.$operations->id.'" selected', false);
    }

    public function test_super_admin_can_filter_users_without_department(): void
    {
        $superAdmin = $this->userWithRole('super_admin', ['name' => 'Unassigned Admin', 'department_id' => null]);
        $department = $this->department(['name' => 'Assigned Department']);
        $this->userWithRole('employee', [
            'name' => 'Assigned Employee',
            'department_id' => $department->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('manage.users.index', ['department_id' => 'unassigned']))
            ->assertOk()
            ->assertSee('Unassigned Admin')
            ->assertDontSee('Assigned Employee')
            ->assertSee('value="unassigned" selected', false);
    }

    public function test_user_department_filter_rejects_invalid_department(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->from(route('manage.users.index'))
            ->get(route('manage.users.index', ['department_id' => '999999']))
            ->assertRedirect(route('manage.users.index'))
            ->assertSessionHasErrors('department_id');
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

    public function test_department_transfer_moves_only_draft_and_rejected_timesheets(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $oldDepartment = $this->department(['name' => 'Old Department']);
        $newDepartment = $this->department(['name' => 'New Department']);
        $employee = $this->userWithRole('employee', [
            'department_id' => $oldDepartment->id,
            'employee_code' => 'MEC-HR-2026-201',
        ]);
        $period = $this->openPeriod();
        $nextPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $thirdPeriod = $this->openPeriod([
            'week_number' => 22,
            'start_date' => '2026-05-25',
            'end_date' => '2026-05-31',
        ]);
        $fourthPeriod = $this->openPeriod([
            'week_number' => 23,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-07',
        ]);

        $draft = Timesheet::create([
            'user_id' => $employee->id,
            'department_id' => $oldDepartment->id,
            'timesheet_period_id' => $period->id,
            'status' => 'draft',
        ]);
        $rejected = Timesheet::create([
            'user_id' => $employee->id,
            'department_id' => $oldDepartment->id,
            'timesheet_period_id' => $nextPeriod->id,
            'status' => 'rejected',
        ]);
        $submitted = Timesheet::create([
            'user_id' => $employee->id,
            'department_id' => $oldDepartment->id,
            'timesheet_period_id' => $thirdPeriod->id,
            'status' => 'submitted',
        ]);
        $approved = Timesheet::create([
            'user_id' => $employee->id,
            'department_id' => $oldDepartment->id,
            'timesheet_period_id' => $fourthPeriod->id,
            'status' => 'approved',
        ]);

        $this->actingAs($superAdmin)->put(route('manage.users.update', $employee), [
            'name' => $employee->name,
            'email' => $employee->email,
            'employee_code' => $employee->employee_code,
            'initials' => $employee->initials,
            'job_title' => $employee->job_title,
            'department_id' => $newDepartment->id,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $this->assertSame($newDepartment->id, $employee->refresh()->department_id);
        $this->assertSame($newDepartment->id, $draft->refresh()->department_id);
        $this->assertSame($newDepartment->id, $rejected->refresh()->department_id);
        $this->assertSame($oldDepartment->id, $submitted->refresh()->department_id);
        $this->assertSame($oldDepartment->id, $approved->refresh()->department_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_pending_timesheets_reassigned',
            'auditable_id' => $employee->id,
        ]);
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
            ->get(route('manage.users.index'))
            ->assertOk()
            ->assertSee('name="replacement_hod_id"', false)
            ->assertSee('data-searchable="false"', false)
            ->assertSee($newHod->name.' - '.$newHod->employee_code)
            ->assertDontSee($oldHod->name.' - '.$oldHod->employee_code);

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
        $this->actingAs($superAdmin)
            ->get(route('manage.audit-logs.index'))
            ->assertOk()
            ->assertDontSee('Reset');
        $this->actingAs($superAdmin)
            ->get(route('manage.audit-logs.index', ['action' => 'user_created']))
            ->assertOk()
            ->assertSee('Reset');
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

    public function test_audit_log_export_is_throttled(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($superAdmin)
                ->withServerVariables(['REMOTE_ADDR' => '10.20.30.20'])
                ->get(route('manage.audit-logs.export'))
                ->assertOk();
        }

        $this->actingAs($superAdmin)
            ->from(route('manage.audit-logs.index'))
            ->withServerVariables(['REMOTE_ADDR' => '10.20.30.20'])
            ->get(route('manage.audit-logs.export'))
            ->assertRedirect(route('manage.audit-logs.index'))
            ->assertSessionHas('warning');
    }

    public function test_audit_log_export_warns_when_export_is_already_running(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $lock = Cache::lock('exports:user:'.$superAdmin->id, 120);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($superAdmin)
                ->from(route('manage.audit-logs.index'))
                ->get(route('manage.audit-logs.export'))
                ->assertRedirect(route('manage.audit-logs.index'))
                ->assertSessionHas('warning', 'An export is already running. Please wait for it to finish before starting another export.');
        } finally {
            $lock->release();
        }
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

    public function test_super_admin_can_delete_one_selected_audit_log(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $selected = $this->auditLog(['action' => 'user_updated']);
        $unselected = $this->auditLog(['action' => 'project_created']);

        $this->actingAs($superAdmin)
            ->from(route('manage.audit-logs.index', ['action' => 'user_updated']))
            ->delete(route('manage.audit-logs.destroy-selected'), [
                'audit_log_ids' => [$selected->id],
                'action' => 'user_updated',
            ])
            ->assertRedirect(route('manage.audit-logs.index', ['action' => 'user_updated']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('audit_logs', ['id' => $selected->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $unselected->id]);
    }

    public function test_super_admin_can_bulk_delete_selected_audit_logs(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $first = $this->auditLog(['action' => 'user_updated']);
        $second = $this->auditLog(['action' => 'department_updated']);
        $unselected = $this->auditLog(['action' => 'project_created']);

        $this->actingAs($superAdmin)
            ->delete(route('manage.audit-logs.destroy-selected'), [
                'audit_log_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('manage.audit-logs.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('audit_logs', ['id' => $first->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $second->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $unselected->id]);
    }

    public function test_deleting_selected_audit_logs_preserves_unselected_logs(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $selected = $this->auditLog(['action' => 'timesheet_submitted']);
        $unselected = $this->auditLog(['action' => 'timesheet_approved']);

        $this->actingAs($superAdmin)
            ->delete(route('manage.audit-logs.destroy-selected'), [
                'audit_log_ids' => [$selected->id],
            ])
            ->assertRedirect(route('manage.audit-logs.index'));

        $this->assertDatabaseMissing('audit_logs', ['id' => $selected->id]);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $unselected->id,
            'action' => 'timesheet_approved',
        ]);
    }

    public function test_invalid_audit_log_ids_are_rejected_without_partial_delete(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $validLog = $this->auditLog(['action' => 'user_updated']);

        $this->actingAs($superAdmin)
            ->from(route('manage.audit-logs.index'))
            ->delete(route('manage.audit-logs.destroy-selected'), [
                'audit_log_ids' => [$validLog->id, 999999],
            ])
            ->assertRedirect(route('manage.audit-logs.index'))
            ->assertSessionHasErrors('audit_log_ids.1');

        $this->assertDatabaseHas('audit_logs', ['id' => $validLog->id]);
    }

    public function test_admin_and_employee_cannot_delete_audit_logs(): void
    {
        $log = $this->auditLog(['action' => 'user_updated']);

        foreach (['admin', 'employee'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->delete(route('manage.audit-logs.destroy-selected'), [
                    'audit_log_ids' => [$log->id],
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('manage.audit-logs.destroy-matching'), [
                    'action' => 'user_updated',
                    'confirm_delete_matching' => '1',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_delete_all_matching_filters_only_deletes_filtered_audit_logs(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $matchingUser = $this->userWithRole('employee');
        $matching = $this->auditLog([
            'user_id' => $matchingUser->id,
            'action' => 'timesheet_missing_reminder_sent',
            'created_at' => '2026-05-10 08:00:00',
            'updated_at' => '2026-05-10 08:00:00',
        ]);
        $differentAction = $this->auditLog([
            'user_id' => $matchingUser->id,
            'action' => 'timesheet_submitted',
            'created_at' => '2026-05-10 09:00:00',
            'updated_at' => '2026-05-10 09:00:00',
        ]);
        $differentUser = $this->auditLog([
            'action' => 'timesheet_missing_reminder_sent',
            'created_at' => '2026-05-10 10:00:00',
            'updated_at' => '2026-05-10 10:00:00',
        ]);
        $differentDate = $this->auditLog([
            'user_id' => $matchingUser->id,
            'action' => 'timesheet_missing_reminder_sent',
            'created_at' => '2026-05-11 08:00:00',
            'updated_at' => '2026-05-11 08:00:00',
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('manage.audit-logs.destroy-matching'), [
                'action' => 'timesheet_missing_reminder_sent',
                'user_id' => $matchingUser->id,
                'date_from' => '2026-05-10',
                'date_to' => '2026-05-10',
                'confirm_delete_matching' => '1',
            ])
            ->assertRedirect(route('manage.audit-logs.index', [
                'action' => 'timesheet_missing_reminder_sent',
                'user_id' => $matchingUser->id,
                'date_from' => '2026-05-10',
                'date_to' => '2026-05-10',
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('audit_logs', ['id' => $matching->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $differentAction->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $differentUser->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $differentDate->id]);
    }

    public function test_empty_audit_log_selection_returns_validation_error(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->from(route('manage.audit-logs.index'))
            ->delete(route('manage.audit-logs.destroy-selected'), [
                'audit_log_ids' => [],
            ])
            ->assertRedirect(route('manage.audit-logs.index'))
            ->assertSessionHasErrors('audit_log_ids');
    }

    public function test_delete_matching_requires_explicit_confirmation(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $log = $this->auditLog(['action' => 'user_updated']);

        $this->actingAs($superAdmin)
            ->from(route('manage.audit-logs.index', ['action' => 'user_updated']))
            ->delete(route('manage.audit-logs.destroy-matching'), [
                'action' => 'user_updated',
            ])
            ->assertRedirect(route('manage.audit-logs.index', ['action' => 'user_updated']))
            ->assertSessionHasErrors('confirm_delete_matching');

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_audit_log_delete_redirect_preserves_filters_and_page(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $log = $this->auditLog(['action' => 'user_updated']);

        $this->actingAs($superAdmin)
            ->delete(route('manage.audit-logs.destroy-selected'), [
                'audit_log_ids' => [$log->id],
                'action' => 'user_updated',
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'page' => 2,
            ])
            ->assertRedirect(route('manage.audit-logs.index', [
                'action' => 'user_updated',
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'page' => 2,
            ]));
    }

    private function auditLog(array $attributes = []): AuditLog
    {
        $attributes = array_merge([
            'user_id' => null,
            'action' => 'user_updated',
            'auditable_type' => User::class,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => ['changed' => true],
            'ip_address' => '127.0.0.1',
        ], $attributes);

        $createdAt = $attributes['created_at'] ?? null;
        $updatedAt = $attributes['updated_at'] ?? $createdAt;
        unset($attributes['created_at'], $attributes['updated_at']);

        $log = AuditLog::create($attributes);

        if ($createdAt || $updatedAt) {
            $log->forceFill([
                'created_at' => $createdAt ?? $log->created_at,
                'updated_at' => $updatedAt ?? $log->updated_at,
            ])->save();
        }

        return $log;
    }
}
