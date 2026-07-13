<?php

namespace Tests\Feature;

use App\Mail\MissingTimesheetReminderMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AdminHodTimesheetWorkflowTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_admin_can_view_and_filter_hod_timesheets(): void
    {
        $department = $this->department(['name' => 'HOD Review Department']);
        $otherDepartment = $this->department(['name' => 'Other Review Department']);
        $period = $this->openPeriod();
        $project = $this->project();
        $hod = $this->userWithRole('hod', [
            'name' => 'Review HOD',
            'department_id' => $department->id,
        ]);
        $otherHod = $this->userWithRole('hod', [
            'name' => 'Other Review HOD',
            'department_id' => $otherDepartment->id,
        ]);
        $employee = $this->userWithRole('employee', [
            'name' => 'Review Employee',
            'department_id' => $department->id,
        ]);
        $this->submittedTimesheet($hod, $period, $project);
        $this->submittedTimesheet($otherHod, $period, $project);
        $this->submittedTimesheet($employee, $period, $project);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.hod-timesheets.index', [
                'department_id' => $department->id,
                'status' => 'submitted',
            ]))
            ->assertOk()
            ->assertSee('HOD Timesheets')
            ->assertSee('Review HOD')
            ->assertSee('HOD Review Department')
            ->assertDontSee('<td class="fw-semibold">Other Review HOD</td>', false)
            ->assertDontSee('<td class="fw-semibold">Review Employee</td>', false)
            ->assertSee(route('admin.timesheets.show', $hod->timesheets()->first()), false);
    }

    public function test_admin_hod_tracker_shows_only_active_department_assigned_hods(): void
    {
        $department = $this->department(['name' => 'Tracked Department']);
        $otherDepartment = $this->department(['name' => 'Untracked Department']);
        $period = $this->openPeriod();
        $project = $this->project();
        $submittedHod = $this->userWithRole('hod', [
            'name' => 'Submitted HOD',
            'department_id' => $department->id,
        ]);
        $missingHod = $this->userWithRole('hod', [
            'name' => 'Missing HOD Tracker',
            'department_id' => $department->id,
        ]);
        $inactiveHod = $this->userWithRole('hod', [
            'name' => 'Inactive HOD Tracker',
            'department_id' => $department->id,
            'is_active' => false,
        ]);
        $unassignedHod = $this->userWithRole('hod', [
            'name' => 'Unassigned HOD Tracker',
            'department_id' => null,
        ]);
        $otherHod = $this->userWithRole('hod', [
            'name' => 'Other Department HOD Tracker',
            'department_id' => $otherDepartment->id,
        ]);
        $employee = $this->userWithRole('employee', [
            'name' => 'Tracker Employee',
            'department_id' => $department->id,
        ]);
        $this->submittedTimesheet($submittedHod, $period, $project);
        $this->submittedTimesheet($employee, $period, $project);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.hod-tracker', [
                'period_id' => $period->id,
                'department_id' => $department->id,
            ]))
            ->assertOk()
            ->assertSee('HOD Submission Tracker')
            ->assertSee('Submitted HOD')
            ->assertSee('Missing HOD Tracker')
            ->assertSee('Need reminder')
            ->assertDontSee('Inactive HOD Tracker')
            ->assertDontSee('Unassigned HOD Tracker')
            ->assertDontSee('Other Department HOD Tracker')
            ->assertDontSee('Tracker Employee');

        $this->assertSame(0, $missingHod->timesheets()->count());
        $this->assertSame(0, $inactiveHod->timesheets()->count());
        $this->assertSame(0, $unassignedHod->timesheets()->count());
        $this->assertSame(0, $otherHod->timesheets()->count());
    }

    public function test_admin_can_send_missing_hod_timesheet_reminders(): void
    {
        Mail::fake();

        $department = $this->department();
        $period = $this->openPeriod();
        $project = $this->project();
        $missingHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $submittedHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $this->submittedTimesheet($submittedHod, $period, $project);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.hod-tracker.reminders'), [
                'period_id' => $period->id,
                'department_id' => $department->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sent 1 missing HOD timesheet reminder(s).');

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingHod->email)
            && $mail->period->is($period)
            && $mail->sourceLabel === 'your Admin team');
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($submittedHod->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($employee->email));
    }

    public function test_admin_can_send_one_missing_hod_reminder_and_cooldown_is_enforced(): void
    {
        Mail::fake();

        $department = $this->department();
        $period = $this->openPeriod();
        $missingHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $otherMissingHod = $this->userWithRole('hod', ['department_id' => $department->id]);
        $admin = $this->userWithRole('admin');
        $payload = [
            'period_id' => $period->id,
            'department_id' => $department->id,
            'hod_id' => $missingHod->id,
        ];

        $this->actingAs($admin)
            ->post(route('admin.hod-tracker.reminders'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->get(route('admin.hod-tracker', ['period_id' => $period->id, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Available again in')
            ->assertSee('disabled', false);

        $this->actingAs($admin)
            ->post(route('admin.hod-tracker.reminders'), $payload)
            ->assertRedirect()
            ->assertSessionHas('warning', 'No reminder was sent. The selected HOD(s) were already reminded recently.');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($missingHod->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($otherMissingHod->email));
    }

    public function test_non_admin_cannot_access_admin_hod_pages(): void
    {
        $hod = $this->userWithRole('hod', ['department_id' => $this->department()->id]);

        $this->actingAs($hod)->get(route('admin.hod-timesheets.index'))->assertForbidden();
        $this->actingAs($hod)->get(route('admin.hod-tracker'))->assertForbidden();
        $this->actingAs($hod)->post(route('admin.hod-tracker.reminders'), ['period_id' => 1])->assertForbidden();
    }

    public function test_admin_hod_tracker_validates_selected_period(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.hod-tracker', ['period_id' => 999]))
            ->assertSessionHasErrors('period_id');
    }

    public function test_approval_excluded_hods_are_hidden_from_admin_hod_pages_and_reminders(): void
    {
        Mail::fake();

        $department = $this->department();
        $period = $this->openPeriod();
        $project = $this->project();
        $excludedSubmittedHod = $this->userWithRole('hod', [
            'name' => 'Excluded Submitted HOD',
            'department_id' => $department->id,
        ]);
        $visibleSubmittedHod = $this->userWithRole('hod', [
            'name' => 'Visible Submitted HOD',
            'department_id' => $department->id,
        ]);
        $excludedMissingHod = $this->userWithRole('hod', [
            'name' => 'Excluded Missing HOD',
            'department_id' => $department->id,
        ]);
        $visibleMissingHod = $this->userWithRole('hod', [
            'name' => 'Visible Missing HOD',
            'department_id' => $department->id,
        ]);
        $this->submittedTimesheet($excludedSubmittedHod, $period, $project);
        $this->submittedTimesheet($visibleSubmittedHod, $period, $project);
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super_admin');
        $admin->adminApprovalExcludedHods()->attach([$excludedSubmittedHod->id, $excludedMissingHod->id]);

        $this->actingAs($admin)
            ->get(route('admin.hod-timesheets.index'))
            ->assertOk()
            ->assertDontSee('Excluded Submitted HOD')
            ->assertSee('Visible Submitted HOD');

        $this->actingAs($admin)
            ->get(route('admin.hod-tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertDontSee('Excluded Submitted HOD')
            ->assertDontSee('Excluded Missing HOD')
            ->assertSee('Visible Submitted HOD')
            ->assertSee('Visible Missing HOD');

        $this->actingAs($admin)
            ->post(route('admin.hod-tracker.reminders'), ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sent 1 missing HOD timesheet reminder(s).');

        Mail::assertQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($visibleMissingHod->email));
        Mail::assertNotQueued(MissingTimesheetReminderMail::class, fn ($mail) => $mail->hasTo($excludedMissingHod->email));

        $this->actingAs($admin)
            ->post(route('admin.hod-tracker.reminders'), [
                'period_id' => $period->id,
                'hod_id' => $excludedMissingHod->id,
            ])
            ->assertNotFound();

        $this->actingAs($superAdmin)
            ->get(route('admin.hod-timesheets.index'))
            ->assertOk()
            ->assertSee('Excluded Submitted HOD');
        $this->actingAs($superAdmin)
            ->get(route('admin.hod-tracker', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Excluded Missing HOD');
    }
}
