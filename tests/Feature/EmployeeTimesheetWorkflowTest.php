<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\AuditLog;
use App\Models\Timesheet;
use App\Models\TimesheetStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class EmployeeTimesheetWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_employee_can_save_draft_with_blank_weekend_attendance_codes(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();

        $response = $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ]);

        $response->assertRedirect();
        $timesheet = Timesheet::firstOrFail();
        $weekendEntry = $timesheet->entries()
            ->whereDate('work_date', '2026-05-16')
            ->firstOrFail();

        $this->assertSame('draft', $timesheet->status);
        $this->assertSame('8.00', $timesheet->total_regular_hours);
        $this->assertNull($weekendEntry->attendance_code);
        $this->assertSame(0, AuditLog::where('action', 'timesheet_created')->count());
        $this->assertSame(0, TimesheetStatusHistory::where('action', 'timesheet_created')->count());
    }

    public function test_employee_can_choose_from_multiple_open_periods_before_creating_timesheet(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $lastWeek = $this->openPeriod([
            'week_number' => 20,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-17',
        ]);
        $currentWeek = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create'))
            ->assertOk()
            ->assertSee('Week 20')
            ->assertSee('Week 21');

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $lastWeek->id]))
            ->assertOk()
            ->assertSee('May 11, 2026')
            ->assertSee('2026-05-11')
            ->assertSee('2026-05-17')
            ->assertDontSee('2026-05-18')
            ->assertSee('data-duplicate-entry', false)
            ->assertSee('data-copy-day', false)
            ->assertSee('Paste to selected days')
            ->assertSee("addButton.classList.toggle('d-none', ! isFirstForDate)", false);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $currentWeek->id]))
            ->assertOk()
            ->assertSee('2026-05-18')
            ->assertSee('2026-05-24')
            ->assertDontSee('2026-05-11');
    }

    public function test_timesheet_create_and_edit_forms_prevent_enter_key_submission(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $createPeriod = $this->openPeriod();
        $editPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $editPeriod, $project, ['status' => 'draft']);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $createPeriod->id]))
            ->assertOk()
            ->assertSee('data-prevent-enter-submit', false)
            ->assertSee("event.key !== 'Enter'", false)
            ->assertSee('event.preventDefault()', false);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.edit', $timesheet))
            ->assertOk()
            ->assertSee('data-prevent-enter-submit', false)
            ->assertSee("event.key !== 'Enter'", false)
            ->assertSee('event.preventDefault()', false);
    }

    public function test_users_without_department_cannot_open_timesheet_create_flow(): void
    {
        $this->openPeriod();

        foreach (['admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role, ['department_id' => null]);

            $this->actingAs($user)
                ->get(route('employee.timesheets.create'))
                ->assertRedirect(route('employee.timesheets.index'))
                ->assertSessionHas('warning');

            $this->actingAs($user)
                ->get(route('employee.timesheets.create', ['period_id' => 1]))
                ->assertRedirect(route('employee.timesheets.index'))
                ->assertSessionHas('warning');

            $this->actingAs($user)
                ->get(route('employee.timesheets.index'))
                ->assertOk()
                ->assertSee('Department Required')
                ->assertSee('assigned to a department');
        }
    }

    public function test_users_without_department_cannot_store_timesheet_by_direct_request(): void
    {
        $period = $this->openPeriod();
        $project = $this->project();

        foreach (['admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role, ['department_id' => null]);

            $this->actingAs($user)->post(route('employee.timesheets.store'), [
                'timesheet_period_id' => $period->id,
                'submit' => '1',
                'entries' => $this->validEntries($project),
            ])
                ->assertRedirect(route('employee.timesheets.index'))
                ->assertSessionHas('warning');

            $this->assertDatabaseMissing('timesheets', [
                'user_id' => $user->id,
                'timesheet_period_id' => $period->id,
            ]);
        }
    }

    public function test_admin_with_department_can_create_own_timesheet(): void
    {
        $department = $this->department();
        $admin = $this->userWithRole('admin', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($admin)
            ->get(route('employee.timesheets.create'))
            ->assertOk()
            ->assertSee('Week 20');

        $this->actingAs($admin)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $this->assertDatabaseHas('timesheets', [
            'user_id' => $admin->id,
            'department_id' => $department->id,
            'timesheet_period_id' => $period->id,
            'status' => 'draft',
        ]);
    }

    public function test_employee_cannot_create_form_for_closed_period_from_query_string(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $openPeriod = $this->openPeriod();
        $closedPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
            'status' => 'closed',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $closedPeriod->id]))
            ->assertRedirect(route('employee.timesheets.create'))
            ->assertSessionHas('warning');

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create'))
            ->assertSee('Week 20')
            ->assertDontSee('Week 21');
    }

    public function test_employee_can_submit_valid_timesheet_and_it_becomes_locked(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();

        $this->assertSame('submitted', $timesheet->status);
        $this->assertNotNull($timesheet->submitted_at);
        $this->actingAs($employee)->get(route('employee.timesheets.edit', $timesheet))->assertForbidden();
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet submitted for approval');
    }

    public function test_submitted_timesheet_notifies_all_active_hod_approvers_for_department(): void
    {
        Mail::fake();

        $department = $this->department();
        $primaryHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $coveringHod = $this->userWithRole('hod', ['department_id' => $this->department()->id]);
        $inactiveHod = $this->userWithRole('hod', [
            'department_id' => $department->id,
            'is_active' => false,
        ]);
        $staleEmployeeApprover = $this->userWithRole('employee', ['department_id' => $department->id]);
        $department->update(['hod_id' => $primaryHod->id]);
        $department->hods()->attach([
            $primaryHod->id,
            $coveringHod->id,
            $inactiveHod->id,
            $staleEmployeeApprover->id,
        ]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($primaryHod->email)
            && $mail->headline === 'Timesheet submitted for approval');
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($coveringHod->email)
            && $mail->headline === 'Timesheet submitted for approval');
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($inactiveHod->email));
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($staleEmployeeApprover->email));
        Mail::assertQueuedCount(2);
    }

    public function test_hod_submission_notifies_admins_and_confirms_to_submitting_hod(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $otherHodApprover = $this->userWithRole('hod', ['department_id' => $department->id]);
        $admin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => true]);
        $optedOutAdmin = $this->userWithRole('admin', ['receives_hod_timesheet_submission_emails' => false]);
        $superAdmin = $this->userWithRole('super_admin', ['receives_hod_timesheet_submission_emails' => true]);
        $optedOutSuperAdmin = $this->userWithRole('super_admin', ['receives_hod_timesheet_submission_emails' => false]);
        $inactiveAdmin = $this->userWithRole('admin', ['is_active' => false]);
        $department->update(['hod_id' => $otherHodApprover->id]);
        $department->hods()->attach($otherHodApprover->id);
        $period = $this->openPeriod();
        $project = $this->project();

        $this->actingAs($hod)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($admin->email)
            && $mail->headline === 'HOD timesheet submitted for approval'
            && str_contains($mail->actionUrl, '/admin/timesheets/'));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($superAdmin->email)
            && $mail->headline === 'HOD timesheet submitted for approval'
            && str_contains($mail->actionUrl, '/admin/timesheets/'));
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Your timesheet was submitted'
            && str_contains($mail->actionUrl, '/my-timesheets/'));
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($otherHodApprover->email));
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($optedOutAdmin->email));
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($optedOutSuperAdmin->email));
        Mail::assertNotQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($inactiveAdmin->email));
        Mail::assertQueuedCount(3);
    }

    public function test_admin_and_super_admin_submissions_do_not_send_review_notification_emails(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $adminSubmitter = $this->userWithRole('admin', ['department_id' => $department->id]);
        $superAdminSubmitter = $this->userWithRole('super_admin', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $period = $this->openPeriod();
        $nextPeriod = $this->openPeriod([
            'week_number' => 21,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-24',
        ]);
        $project = $this->project();

        $this->actingAs($adminSubmitter)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        $this->actingAs($superAdminSubmitter)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $nextPeriod->id,
            'submit' => '1',
            'entries' => $this->validEntries($project),
        ])->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_employee_cannot_create_second_timesheet_for_same_week(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $existing = $this->submittedTimesheet($employee, $period, $project);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertRedirect(route('employee.timesheets.show', $existing))
            ->assertSessionHas('warning');

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertRedirect(route('employee.timesheets.show', $existing));
    }

    public function test_submit_requires_at_least_one_hour(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = array_map(fn ($entry) => array_merge($entry, [
            'project_id' => '',
            'regular_hours' => 0,
            'overtime_hours' => 0,
        ]), $this->validEntries($project));

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors('entries');
    }

    public function test_hours_require_project_and_attendance_code_but_remarks_are_optional(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => '',
                'project_id' => '',
                'regular_hours' => 0,
                'overtime_hours' => 2,
                'remarks' => '',
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors([
            'entries.0.project_id',
            'entries.0.attendance_code',
        ]);

        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'O100',
                'project_id' => $project->id,
                'regular_hours' => 0,
                'overtime_hours' => 2,
                'remarks' => '',
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $this->assertSame('2.00', Timesheet::firstOrFail()->total_overtime_hours);
    }

    public function test_employee_sees_entry_errors_when_draft_cannot_be_saved(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => '',
                'project_id' => '',
                'regular_hours' => 8,
                'overtime_hours' => 0,
            ],
        ]);

        $this->followingRedirects()
            ->actingAs($employee)
            ->from(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->post(route('employee.timesheets.store'), [
                'timesheet_period_id' => $period->id,
                'submit' => '0',
                'entries' => $entries,
            ])
            ->assertOk()
            ->assertSee('Timesheet could not be saved or submitted')
            ->assertSee('Row for 2026-05-11 needs a project/job number when hours are entered.')
            ->assertSee('Row for 2026-05-11 needs an attendance code when hours are entered.')
            ->assertSee('timesheet-entry-row-invalid', false);

        $this->assertDatabaseMissing('timesheets', [
            'user_id' => $employee->id,
            'timesheet_period_id' => $period->id,
        ]);
    }

    public function test_leave_codes_allow_regular_hours_without_project(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'L100',
                'project_id' => '',
                'regular_hours' => 8,
                'overtime_hours' => 0,
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();
        $leaveEntry = $timesheet->entries()->where('attendance_code', 'L100')->firstOrFail();

        $this->assertSame('8.00', $timesheet->total_regular_hours);
        $this->assertNull($leaveEntry->project_id);
    }

    public function test_service_incentive_leave_is_restricted_on_timesheets(): void
    {
        $department = $this->department();
        $period = $this->openPeriod();
        $project = $this->project();
        $uaeEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-HR-2026-901',
        ]);
        $phEmployee = $this->userWithRole('employee', [
            'department_id' => $department->id,
            'employee_code' => 'MEC-PHIL-HR-2026-902',
            'joining_date' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($uaeEmployee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertDontSee('L190 - Service Incentive Leave');

        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'L190',
                'project_id' => '',
                'regular_hours' => 8,
                'overtime_hours' => 0,
            ],
        ]);

        $this->actingAs($uaeEmployee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors('entries.0.attendance_code');

        $this->actingAs($phEmployee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('L190 - Service Incentive Leave')
            ->assertSee('L160 - Maternity Leave')
            ->assertSee('L170 - Parental Leave')
            ->assertSee('L210 - Paternity Leave')
            ->assertSee('L220 - Leave for VAWC')
            ->assertSee('L230 - Special Leave for Women')
            ->assertDontSee('L100 - Annual Leave')
            ->assertDontSee('L110 - Sick Leave')
            ->assertDontSee('L120 - Emergency Leave')
            ->assertDontSee('L180 - Bereavement Leave');

        $this->actingAs($phEmployee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        foreach (['L100', 'L110', 'L120', 'L180'] as $attendanceCode) {
            $blockedEntries = $this->validEntries($project, [
                '2026-05-11' => [
                    'attendance_code' => $attendanceCode,
                    'project_id' => '',
                    'regular_hours' => 8,
                    'overtime_hours' => 0,
                ],
            ]);

            $this->actingAs($phEmployee)->post(route('employee.timesheets.store'), [
                'timesheet_period_id' => $period->id,
                'submit' => '1',
                'entries' => $blockedEntries,
            ])->assertSessionHasErrors('entries.0.attendance_code');
        }
    }

    public function test_philippines_statutory_leave_codes_are_region_available_on_timesheets(): void
    {
        $period = $this->openPeriod();
        $project = $this->project();
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'employee_code' => 'MEC-PHIL-HR-2026-903',
            'gender' => 'female',
            'joining_date' => now()->subYear()->toDateString(),
        ]);

        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'L220',
                'project_id' => '',
                'regular_hours' => 8,
                'overtime_hours' => 0,
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();
        $leaveEntry = $timesheet->entries()->where('attendance_code', 'L220')->firstOrFail();

        $this->assertNull($leaveEntry->project_id);
    }

    public function test_uae_special_leave_codes_are_region_available_on_timesheets(): void
    {
        $period = $this->openPeriod();
        $project = $this->project();
        $employee = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'employee_code' => 'MEC-HR-2026-904',
            'gender' => 'male',
            'eligible_for_parental_leave' => false,
            'eligible_for_bereavement_spouse_leave' => false,
            'eligible_for_bereavement_immediate_family_leave' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('L160 - Maternity Leave')
            ->assertSee('L170 - Parental Leave')
            ->assertSee('L180 - Bereavement Leave')
            ->assertDontSee('L190 - Service Incentive Leave');

        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'L180',
                'project_id' => '',
                'regular_hours' => 8,
                'overtime_hours' => 0,
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();
        $leaveEntry = $timesheet->entries()->where('attendance_code', 'L180')->firstOrFail();

        $this->assertNull($leaveEntry->project_id);
    }

    public function test_training_code_allows_regular_and_overtime_hours_without_project(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'L200',
                'project_id' => '',
                'regular_hours' => 6,
                'overtime_hours' => 2,
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();
        $trainingEntry = $timesheet->entries()->where('attendance_code', 'L200')->firstOrFail();

        $this->assertSame('6.00', $timesheet->total_regular_hours);
        $this->assertSame('2.00', $timesheet->total_overtime_hours);
        $this->assertSame('8.00', $timesheet->total_hours);
        $this->assertNull($trainingEntry->project_id);
    }

    public function test_timesheet_show_groups_entries_by_day_with_non_project_rows(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project([
            'project_code' => 'P-GROUP',
            'project_name' => 'Grouped Project Display',
        ]);
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'O100',
                'project_id' => $project->id,
                'regular_hours' => 6,
                'overtime_hours' => 1,
            ],
        ]);
        $entries[] = [
            'work_date' => '2026-05-11',
            'attendance_code' => 'L200',
            'project_id' => '',
            'regular_hours' => 2,
            'overtime_hours' => 1,
            'remarks' => 'Training seminar',
        ];

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '0',
            'entries' => $entries,
        ])->assertRedirect();

        $timesheet = Timesheet::firstOrFail();

        $this->actingAs($employee)
            ->get(route('employee.timesheets.show', $timesheet))
            ->assertOk()
            ->assertSee('Week 20, 2026')
            ->assertSee('2026-05-11 to 2026-05-17')
            ->assertSee('Monday')
            ->assertSee('P-GROUP')
            ->assertSee('Grouped Project Display')
            ->assertSee('L200')
            ->assertSee('Training Seminar')
            ->assertSee('Non-project')
            ->assertDontSee('RT 8.00')
            ->assertDontSee('OT 2.00')
            ->assertDontSee('<th>Date</th>', false)
            ->assertDontSee('<th>Day</th>', false);
    }

    public function test_leave_codes_do_not_allow_overtime_hours(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => [
                'attendance_code' => 'L110',
                'project_id' => '',
                'regular_hours' => 0,
                'overtime_hours' => 2,
            ],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors('entries.0.overtime_hours');
    }

    public function test_work_dates_must_be_inside_the_selected_week(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $entries = $this->validEntries($project, [
            '2026-05-11' => ['work_date' => '2026-05-18'],
        ]);

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $entries,
        ])->assertSessionHasErrors('entries.0.work_date');
    }

    public function test_closed_periods_do_not_accept_new_timesheets(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod(['status' => 'closed']);
        $project = $this->project();

        $this->actingAs($employee)->post(route('employee.timesheets.store'), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertStatus(422);
    }

    public function test_rejected_timesheet_can_be_updated_and_resubmitted(): void
    {
        Mail::fake();

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, [
            'status' => 'rejected',
            'rejection_comment' => 'Please correct Monday.',
            'rejected_at' => now(),
        ]);

        $this->actingAs($employee)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'submit' => '1',
            'entries' => $this->validEntries($project, [
                '2026-05-11' => ['regular_hours' => 7, 'overtime_hours' => 1],
            ]),
        ])->assertRedirect();

        $timesheet->refresh();
        $this->assertSame('submitted', $timesheet->status);
        $this->assertNull($timesheet->rejection_comment);
        $this->assertSame('7.00', $timesheet->total_regular_hours);
        $this->assertSame('1.00', $timesheet->total_overtime_hours);
        Mail::assertNothingQueued();
    }

    public function test_draft_only_updates_and_deletes_do_not_create_timesheet_logs(): void
    {
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project, ['status' => 'draft']);

        $this->actingAs($employee)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project, [
                '2026-05-11' => ['regular_hours' => 6],
            ]),
        ])->assertRedirect(route('employee.timesheets.show', $timesheet));

        $this->assertSame('draft', $timesheet->refresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'timesheet_updated')->count());
        $this->assertSame(0, TimesheetStatusHistory::where('action', 'timesheet_updated')->count());

        $this->actingAs($employee)
            ->delete(route('employee.timesheets.destroy', $timesheet))
            ->assertRedirect(route('employee.timesheets.index'));

        $this->assertSame(0, AuditLog::where('action', 'timesheet_deleted')->count());
        $this->assertSame(0, TimesheetStatusHistory::count());
    }

    public function test_employee_can_withdraw_own_submitted_timesheet_without_email(): void
    {
        Mail::fake();

        $department = $this->department();
        $hod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $department->update(['hod_id' => $hod->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($employee, $period, $project);

        $this->actingAs($employee)
            ->withServerVariables(['REMOTE_ADDR' => '10.11.12.13'])
            ->post(route('employee.timesheets.recall', $timesheet), [
                'withdrawal_comment' => 'Need to correct Thursday overtime.',
            ])
            ->assertRedirect(route('employee.timesheets.edit', $timesheet));

        $this->assertSame('withdrawn', $timesheet->refresh()->status);
        $this->assertNull($timesheet->submitted_at);
        $this->assertSame(1, AuditLog::where('action', 'timesheet_withdrawn')->count());
        $log = AuditLog::where('action', 'timesheet_withdrawn')->firstOrFail();
        $this->assertSame($employee->id, $log->user_id);
        $this->assertSame('10.11.12.13', $log->ip_address);
        $this->assertSame('Need to correct Thursday overtime.', $log->new_values['withdrawal_comment']);
        $history = TimesheetStatusHistory::where('action', 'timesheet_withdrawn')->firstOrFail();
        $this->assertSame($timesheet->id, $history->timesheet_id);
        $this->assertSame($employee->id, $history->actor_id);
        $this->assertSame('submitted', $history->old_status);
        $this->assertSame('withdrawn', $history->new_status);
        $this->assertSame('Need to correct Thursday overtime.', $history->comment);
        $this->assertSame('10.11.12.13', $history->ip_address);
        Mail::assertNothingQueued();
    }

    public function test_employee_cannot_view_or_edit_another_employees_timesheet(): void
    {
        $department = $this->department();
        $owner = $this->userWithRole('employee', ['department_id' => $department->id]);
        $other = $this->userWithRole('employee', ['department_id' => $department->id]);
        $period = $this->openPeriod();
        $project = $this->project();
        $timesheet = $this->submittedTimesheet($owner, $period, $project);

        $this->actingAs($other)->get(route('employee.timesheets.show', $timesheet))->assertForbidden();
        $this->actingAs($other)->put(route('employee.timesheets.update', $timesheet), [
            'timesheet_period_id' => $period->id,
            'entries' => $this->validEntries($project),
        ])->assertForbidden();
    }
}
