<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class AuthenticatedThrottleTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    public function test_authenticated_write_routes_are_throttled_per_user_and_ip(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->actingAs($superAdmin)
                ->withServerVariables(['REMOTE_ADDR' => '10.30.40.10'])
                ->post(route('manage.users.store'), [])
                ->assertSessionHasErrors('name');
        }

        $this->actingAs($superAdmin)
            ->from(route('manage.users.create'))
            ->withServerVariables(['REMOTE_ADDR' => '10.30.40.10'])
            ->post(route('manage.users.store'), [])
            ->assertRedirect(route('manage.users.create'))
            ->assertSessionHasErrors('throttle');
    }

    public function test_manual_reminders_have_an_independent_throttle_bucket(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->actingAs($superAdmin)
                ->withServerVariables(['REMOTE_ADDR' => '10.30.40.20'])
                ->post(route('manage.users.store'), [])
                ->assertSessionHasErrors('name');
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($superAdmin)
                ->withServerVariables(['REMOTE_ADDR' => '10.30.40.20'])
                ->post(route('admin.hod-tracker.reminders'), [])
                ->assertSessionHasErrors('period_id');
        }

        $this->actingAs($superAdmin)
            ->from(route('admin.hod-tracker'))
            ->withServerVariables(['REMOTE_ADDR' => '10.30.40.20'])
            ->post(route('admin.hod-tracker.reminders'), [])
            ->assertRedirect(route('admin.hod-tracker'))
            ->assertSessionHasErrors('throttle');
    }

    public function test_workflow_actions_are_throttled_per_user_and_ip(): void
    {
        $department = $this->department();
        $employee = $this->userWithRole('employee', ['department_id' => $department->id]);
        $timesheet = $this->submittedTimesheet($employee, $this->openPeriod(), $this->project());
        $firstAdmin = $this->userWithRole('admin');
        $secondAdmin = $this->userWithRole('admin');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($firstAdmin)
                ->withServerVariables(['REMOTE_ADDR' => '10.30.40.30'])
                ->post(route('admin.timesheets.reject', $timesheet), [])
                ->assertSessionHasErrors('rejection_comment');
        }

        $this->actingAs($firstAdmin)
            ->from(route('admin.timesheets.show', $timesheet))
            ->withServerVariables(['REMOTE_ADDR' => '10.30.40.30'])
            ->post(route('admin.timesheets.reject', $timesheet), [])
            ->assertRedirect(route('admin.timesheets.show', $timesheet))
            ->assertSessionHasErrors('throttle');

        $this->actingAs($secondAdmin)
            ->withServerVariables(['REMOTE_ADDR' => '10.30.40.30'])
            ->post(route('admin.timesheets.reject', $timesheet), [])
            ->assertSessionHasErrors('rejection_comment');

        $this->actingAs($firstAdmin)
            ->withServerVariables(['REMOTE_ADDR' => '10.30.40.31'])
            ->post(route('admin.timesheets.reject', $timesheet), [])
            ->assertSessionHasErrors('rejection_comment');
    }
}
