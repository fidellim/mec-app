<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ProjectAnalyticsTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_only_own_project_managers_and_super_admins_can_access_analytics(): void
    {
        $manager = $this->userWithRole('hod');
        $project = $this->project(['project_manager_id' => $manager->id]);
        foreach ([$manager, $this->userWithRole('super_admin')] as $viewer) {
            $this->actingAs($viewer)->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics']))
                ->assertOk()->assertSee('Monthly trends')->assertSee('Cumulative hours consumed')->assertSee('Employee hours');
        }
        foreach (['employee', 'hod', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics']))->assertForbidden();
        }
        $other = $this->project();
        $this->actingAs($manager)->get(route('projects.utilization', ['project' => $other, 'tab' => 'analytics']))->assertForbidden();
    }

    public function test_monthly_and_cumulative_totals_preserve_opening_hours_and_isolate_project_and_status(): void
    {
        $manager = $this->userWithRole('employee');
        $project = $this->project(['project_manager_id' => $manager->id]);
        $project->departmentAllocations()->create(['department_id' => $this->department()->id, 'allocated_hours' => 10]);
        $period = $this->openPeriod();
        foreach ([
            ['2026-01-31', 'approved', 10, 2, $project],
            ['2026-03-01', 'approved', 4.5, 1.25, $project],
            ['2026-03-31', 'submitted', 3, 2, $project],
            ['2026-04-01', 'approved', 80, 20, $project],
            ['2026-03-05', 'draft', 80, 20, $project],
            ['2026-03-05', 'rejected', 80, 20, $project],
            ['2026-03-05', 'voided', 80, 20, $project],
            ['2026-03-05', 'withdrawn', 80, 20, $project],
            ['2026-03-05', 'recalled', 80, 20, $project],
            ['2026-03-05', 'approved', 80, 20, $this->project()],
        ] as [$date, $status, $regular, $overtime, $entryProject]) {
            $sheet = $this->submittedTimesheet($this->userWithRole('employee', ['department_id' => $this->department()->id]), $period, $entryProject, ['status' => $status]);
            $sheet->entries()->first()->update(['work_date' => $date, 'regular_hours' => $regular, 'overtime_hours' => $overtime]);
        }
        $this->actingAs($manager)->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics', 'date_from' => '2026-02-01', 'date_to' => '2026-03-31']))
            ->assertOk()->assertSee('Review entries')->assertSee('-12.75')
            ->assertViewHas('analytics', function ($data) {
                $this->assertSame(12.0, $data['openingHours']);
                $this->assertSame(17.75, $data['approved']);
                $this->assertSame(5.0, $data['pending']);
                $this->assertSame(7.5, $data['regular']);
                $this->assertSame(3.25, $data['overtime']);
                $this->assertCount(2, $data['employees']);
                $this->assertSame(['Feb 2026', 'Mar 2026'], $data['months']->pluck('label')->all());
                $this->assertSame(0.0, $data['months'][0]['regular']);
                $this->assertSame(12.0, $data['months'][0]['approved']);

                return true;
            });
    }

    public function test_employee_rankings_use_selected_metric_with_deterministic_ties(): void
    {
        $manager = $this->userWithRole();
        $project = $this->project(['project_manager_id' => $manager->id]);
        $period = $this->openPeriod();
        foreach ([['Alex', 4, 6], ['Blair', 20, 2], ['Casey', 4, 6], ['Drew', 5, 6]] as [$name, $regular, $overtime]) {
            $sheet = $this->submittedTimesheet($this->userWithRole('employee', ['name' => $name, 'department_id' => $this->department()->id]), $period, $project);
            $sheet->entries()->first()->update(['regular_hours' => $regular, 'overtime_hours' => $overtime]);
        }
        foreach (['overtime' => ['Drew', 'Alex', 'Casey', 'Blair'], 'total' => ['Blair', 'Drew', 'Alex', 'Casey']] as $rank => $expected) {
            $this->actingAs($manager)->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics', 'rank_by' => $rank]))
                ->assertOk()->assertViewHas('analytics', fn ($data) => $data['employees']->pluck('name')->all() === $expected);
        }
    }

    public function test_empty_projects_zero_hours_and_one_sided_date_filters_render_safely(): void
    {
        $manager = $this->userWithRole();
        $project = $this->project(['project_manager_id' => $manager->id]);
        $url = route('projects.utilization', ['project' => $project, 'tab' => 'analytics']);
        $this->actingAs($manager)->get($url)->assertOk()->assertSee('Not set')->assertSee('No approved or submitted entries');
        $sheet = $this->submittedTimesheet($this->userWithRole('employee', ['department_id' => $this->department()->id]), $this->openPeriod(), $project);
        $sheet->entries()->first()->update(['regular_hours' => 0, 'overtime_hours' => 0]);
        foreach ([[], ['date_to' => '2026-05-11'], ['date_from' => '2026-05-11'], ['date_from' => '2026-06-01']] as $filters) {
            $this->get($url.'&'.http_build_query($filters))->assertOk()
                ->assertViewHas('analytics', fn ($data) => $data['regular'] === 0.0 && $data['overtime'] === 0.0);
        }
        $project->departmentAllocations()->create(['department_id' => $this->department()->id, 'allocated_hours' => 0]);
        $this->get($url)->assertOk()->assertViewHas('analytics', fn ($data) => $data['hasAllocation'] && $data['allocated'] === 0.0);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $manager = $this->userWithRole();
        $project = $this->project(['project_manager_id' => $manager->id]);
        foreach ([
            ['date_from' => '2026-03-01', 'date_to' => '2026-02-28'],
            ['date_to' => 'not-a-date'],
            ['date_from' => '1800-01-01'],
            ['rank_by' => 'name'],
        ] as $filters) {
            $this->actingAs($manager)->getJson(route('projects.utilization', ['project' => $project, 'tab' => 'analytics'] + $filters))->assertUnprocessable();
        }
    }

    public function test_partial_month_leap_day_and_year_boundaries_include_pending_opening_hours(): void
    {
        $manager = $this->userWithRole();
        $project = $this->project(['project_manager_id' => $manager->id]);
        $sheet = $this->submittedTimesheet($this->userWithRole('employee', ['department_id' => $this->department()->id]), $this->openPeriod(), $project);
        $entry = $sheet->entries()->first();
        $entry->update(['work_date' => '2023-12-31', 'regular_hours' => 2.5, 'overtime_hours' => 0.5]);
        foreach ([['2024-02-28', 10, 0], ['2024-02-29', 1.25, 0.75], ['2024-03-01', 20, 0]] as [$date, $regular, $overtime]) {
            $copy = $entry->replicate();
            $copy->fill(['work_date' => $date, 'regular_hours' => $regular, 'overtime_hours' => $overtime, 'department_id' => null])->save();
        }
        $this->actingAs($manager)->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics', 'date_from' => '2024-02-29', 'date_to' => '2024-02-29']))
            ->assertOk()->assertViewHas('analytics', function ($data) {
                $this->assertSame(13.0, $data['openingHours']);
                $this->assertSame(15.0, $data['pending']);
                $this->assertSame(0.0, $data['approved']);
                $this->assertSame(1.25, $data['regular']);
                $this->assertSame(0.75, $data['overtime']);
                $this->assertCount(1, $data['months']);
                $this->assertEquals(2.0, $data['employees']->first()->total);

                return true;
            });
        $this->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics']))
            ->assertOk()->assertViewHas('analytics', function ($data) {
                $this->assertSame(['Dec 2023', 'Jan 2024', 'Feb 2024', 'Mar 2024'], $data['months']->pluck('label')->all());
                $this->assertCount(1, $data['employees']);
                $this->assertEquals(35.0, $data['employees']->first()->total);

                return true;
            });
    }
}
