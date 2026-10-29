<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class ProjectAnalyticsTest extends TestCase
{
    use CreatesTimesheetData, RefreshDatabase;

    public function test_default_period_runs_from_project_start_to_today_and_preserves_lifetime_hours(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15));
        $project = $this->project(['start_date' => '2026-02-10']);
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        foreach ([['2026-01-20', 'approved', 5], ['2026-01-21', 'submitted', 3], ['2026-03-01', 'approved', 8], ['2026-06-16', 'submitted', 20]] as [$date, $status, $hours]) {
            $sheet = $this->submittedTimesheet($employee, $period, $project, ['status' => $status]);
            $sheet->entries()->first()->update(['work_date' => $date, 'regular_hours' => $hours]);
        }
        $url = route('projects.utilization', ['project' => $project, 'tab' => 'analytics']);
        $this->actingAs($this->userWithRole('super_admin'))->get($url)->assertOk()
            ->assertSee('value="2026-02-10"', false)->assertSee('value="2026-06-15"', false)
            ->assertViewHas('analytics', function ($data) {
                $this->assertSame('2026-02-10', $data['from']);
                $this->assertSame('2026-06-15', $data['to']);
                $this->assertSame(8.0, $data['openingHours']);
                $this->assertSame(13.0, $data['approved']);
                $this->assertSame(3.0, $data['pending']);
                $this->assertSame(8.0, $data['periodApproved']);
                $this->assertSame(0.0, $data['periodPending']);
                $this->assertSame(['Feb 2026', 'Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026'], $data['months']->pluck('label')->all());
                $this->assertSame(0.0, $data['months']->last()['regular']);
                $this->assertCount(1, $data['costBasis']);
                $this->assertSame('Not categorized', $data['costBasis'][0]['category']);

                return true;
            });

        $this->get($url.'&date_from=2026-01-01&date_to=2026-01-31')->assertOk()
            ->assertSee('data-analytics-reset')->assertSee('href="'.$url.'"', false)
            ->assertViewHas('analytics', fn ($data) => $data['from'] === '2026-01-01' && $data['to'] === '2026-01-31' && $data['regular'] === 8.0);
        $this->get($url)->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['from'] === '2026-02-10' && $data['to'] === '2026-06-15');
    }

    public function test_missing_start_date_falls_back_to_first_eligible_entry_then_today(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15));
        $project = $this->project();
        $url = route('projects.utilization', ['project' => $project, 'tab' => 'analytics']);
        $this->actingAs($this->userWithRole('super_admin'))->get($url)->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['from'] === '2026-06-15' && $data['to'] === '2026-06-15');

        $project->update(['start_date' => '2026-02-10']);
        $this->get($url.'&date_from=&date_to=')->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['from'] === '2026-02-10' && $data['to'] === '2026-06-15' && ! $data['hasEntries']);
        $project->update(['start_date' => null]);

        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        foreach ([['2026-01-01', 'draft'], ['2026-02-10', 'submitted'], ['2026-07-01', 'approved']] as [$date, $status]) {
            $sheet = $this->submittedTimesheet($employee, $period, $project, ['status' => $status]);
            $sheet->entries()->first()->update(['work_date' => $date]);
        }
        $this->get($url)->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['from'] === '2026-02-10' && $data['to'] === '2026-06-15');
    }

    public function test_future_project_and_one_sided_filters_keep_valid_ranges(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15));
        $project = $this->project(['start_date' => '2026-07-01']);
        $url = route('projects.utilization', ['project' => $project, 'tab' => 'analytics']);
        $this->actingAs($this->userWithRole('super_admin'))->get($url)->assertOk()
            ->assertSee('Project has not started.')->assertSee('Jul 1, 2026')
            ->assertViewHas('analytics', fn ($data) => $data['from'] === '2026-06-15' && $data['to'] === '2026-06-15' && ! $data['hasEntries']);

        foreach ([
            ['date_to=2026-05-31', '2026-05-31', '2026-05-31'],
            ['date_from=2026-08-01', '2026-08-01', '2026-08-01'],
            ['date_to=2026-08-31', '2026-07-01', '2026-08-31'],
            ['date_from=2026-07-01&date_to=2026-07-31', '2026-07-01', '2026-07-31'],
        ] as [$query, $from, $to]) {
            $this->get($url.'&'.$query)->assertOk()
                ->assertViewHas('analytics', fn ($data) => $data['from'] === $from && $data['to'] === $to);
        }
    }

    public function test_confidential_cost_basis_is_only_rendered_for_super_admins(): void
    {
        $manager = $this->userWithRole('hod');
        $project = $this->project(['project_manager_id' => $manager->id]);
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $this->submittedTimesheet($employee, $this->openPeriod(), $project);
        $url = route('projects.utilization', ['project' => $project, 'tab' => 'analytics']);

        $this->actingAs($manager)->get($url)->assertOk()
            ->assertDontSee('project-cost-basis')->assertDontSee('Load confidential rates')
            ->assertDontSee('data-finance-panel')->assertDontSee('project-management-cost-basis')
            ->assertViewHas('analytics', fn ($data) => $data['costBasis'] === null && $data['managementCostBasis'] === null);
        $this->actingAs($this->userWithRole('super_admin'))->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Load confidential rates')->assertSee('project-cost-basis')
            ->assertViewHas('analytics', fn ($data) => $data['costBasis'][0]['employeeCode'] === $employee->employee_code);
    }

    public function test_cost_basis_preserves_employee_month_charged_department_category_and_status_without_financial_fields(): void
    {
        $project = $this->project();
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $chargedDepartment = $this->department();
        $period = $this->openPeriod();
        $category = array_key_first(config('manpower_categories.labels'));
        foreach ([['2026-01-01', 'approved', 99], ['2026-02-10', 'approved', 2.25], ['2026-02-10', 'submitted', 3.5], ['2026-03-01', 'approved', 80], ['2026-02-10', 'draft', 90]] as [$date, $status, $hours]) {
            $sheet = $this->submittedTimesheet($employee, $period, $project, ['status' => $status]);
            $sheet->entries()->first()->update(['work_date' => $date, 'regular_hours' => $hours, 'overtime_hours' => 1.25, 'department_id' => $chargedDepartment->id, 'manpower_category_snapshot' => $category]);
        }
        $other = $this->submittedTimesheet($employee, $period, $this->project());
        $other->entries()->first()->update(['work_date' => '2026-02-10']);

        $this->actingAs($this->userWithRole('super_admin'))->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics', 'date_from' => '2026-02-01', 'date_to' => '2026-02-28']))
            ->assertOk()->assertViewHas('analytics', function ($data) use ($employee, $chargedDepartment, $category) {
                $this->assertCount(2, $data['costBasis']);
                foreach ($data['costBasis'] as $row) {
                    $this->assertSame($employee->employee_code, $row['employeeCode']);
                    $this->assertSame($chargedDepartment->name, $row['department']);
                    $this->assertSame(config('manpower_categories.labels.'.$category), $row['category']);
                    $this->assertSame('2026-02', $row['month']);
                    $this->assertArrayNotHasKey('rate', $row);
                    $this->assertArrayNotHasKey('cost', $row);
                }
                $this->assertSame(3.5, $data['periodApproved']);
                $this->assertSame(4.75, $data['periodPending']);
                $this->assertSame(8.25, $data['departments']->sum(fn ($row) => $row['regular'] + $row['overtime']));

                return true;
            });
    }

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

    public function test_management_labour_basis_uses_only_this_projects_approved_hours_through_to_date_independent_of_from_date(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15));
        $project = $this->project();
        $employee = $this->userWithRole('employee', ['department_id' => $this->department()->id]);
        $period = $this->openPeriod();
        foreach ([['2026-01-01', 'approved', 10, $project], ['2026-06-01', 'approved', 20, $project], ['2026-06-16', 'approved', 90, $project], ['2026-06-01', 'submitted', 80, $project], ['2026-06-01', 'approved', 70, $this->project()]] as [$date, $status, $hours, $entryProject]) {
            $sheet = $this->submittedTimesheet($employee, $period, $entryProject, ['status' => $status]);
            $sheet->entries()->first()->update(['work_date' => $date, 'regular_hours' => $hours, 'overtime_hours' => 1]);
        }
        $url = route('projects.utilization', ['project' => $project, 'tab' => 'analytics']);
        $this->actingAs($this->userWithRole('super_admin'))->get($url.'&date_from=2026-06-01&date_to=2026-06-15')
            ->assertOk()->assertViewHas('analytics', function ($data) {
                $this->assertCount(2, $data['managementCostBasis']);
                $this->assertSame(30.0, collect($data['managementCostBasis'])->sum(fn ($row) => (float) $row['regular']));
                $this->assertSame(2.0, collect($data['managementCostBasis'])->sum(fn ($row) => (float) $row['overtime']));
                $this->assertSame(['approved'], collect($data['managementCostBasis'])->pluck('status')->unique()->values()->all());

                return true;
            });
        $this->get($url.'&date_from=2026-01-01&date_to=2026-01-31')->assertOk()
            ->assertViewHas('analytics', fn ($data) => count($data['managementCostBasis']) === 1 && $data['managementCostBasis'][0]['regular'] === '10.00');
        $this->get($url.'&date_from=2026-06-01&date_to=2026-06-30')->assertOk()
            ->assertViewHas('analytics', fn ($data) => collect($data['managementCostBasis'])->sum(fn ($row) => (float) $row['regular']) === 120.0);
    }

    public function test_inactive_employees_work_dates_remain_separate_across_the_april_may_rate_boundary(): void
    {
        $project = $this->project();
        $employee = $this->userWithRole('employee', ['is_active' => false, 'department_id' => $this->department()->id]);
        $sheet = $this->submittedTimesheet($employee, $this->openPeriod(), $project, ['status' => 'approved']);
        $entry = $sheet->entries()->first();
        $entry->update(['work_date' => '2026-04-30', 'regular_hours' => 8, 'overtime_hours' => 2]);
        $copy = $entry->replicate();
        $copy->fill(['work_date' => '2026-05-01', 'regular_hours' => 8, 'overtime_hours' => 2])->save();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('projects.utilization', ['project' => $project, 'tab' => 'analytics', 'date_from' => '2026-04-30', 'date_to' => '2026-05-01']))
            ->assertOk()->assertViewHas('analytics', function ($data) use ($employee) {
                foreach (['costBasis', 'managementCostBasis'] as $key) {
                    $this->assertSame(['2026-04', '2026-05'], collect($data[$key])->pluck('month')->sort()->values()->all());
                    foreach ($data[$key] as $row) {
                        $this->assertSame($employee->employee_code, $row['employeeCode']);
                        $this->assertSame('8.00', $row['regular']);
                        $this->assertSame('2.00', $row['overtime']);
                    }
                }

                return true;
            });
    }

    public function test_partial_month_leap_day_and_year_boundaries_include_pending_opening_hours(): void
    {
        $this->travelTo(now()->setDate(2024, 3, 1));
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
