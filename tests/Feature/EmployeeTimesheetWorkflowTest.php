<?php

namespace Tests\Feature;

use App\Mail\TimesheetWorkflowMail;
use App\Models\Timesheet;
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
            ->assertSee('2026-05-11')
            ->assertSee('2026-05-17')
            ->assertDontSee('2026-05-18')
            ->assertSee("addButton.classList.toggle('d-none', ! isFirstForDate)", false);

        $this->actingAs($employee)
            ->get(route('employee.timesheets.create', ['period_id' => $currentWeek->id]))
            ->assertOk()
            ->assertSee('2026-05-18')
            ->assertSee('2026-05-24')
            ->assertDontSee('2026-05-11');
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

    public function test_employee_can_recall_own_submitted_timesheet(): void
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
            ->post(route('employee.timesheets.recall', $timesheet))
            ->assertRedirect(route('employee.timesheets.edit', $timesheet));

        $this->assertSame('draft', $timesheet->refresh()->status);
        Mail::assertQueued(TimesheetWorkflowMail::class, fn ($mail) => $mail->hasTo($hod->email)
            && $mail->headline === 'Timesheet recalled by employee');
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
