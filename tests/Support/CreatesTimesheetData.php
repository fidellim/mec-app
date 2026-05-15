<?php

namespace Tests\Support;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

trait CreatesTimesheetData
{
    private static int $employeeCodeSequence = 100;

    protected function department(array $attributes = []): Department
    {
        return Department::factory()->create($attributes);
    }

    protected function project(array $attributes = []): Project
    {
        return Project::factory()->create($attributes);
    }

    protected function userWithRole(string $role = 'employee', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'password' => Hash::make('password123'),
            'employee_code' => in_array($role, ['employee', 'hod'], true) ? 'MEC-HR-2026-'.self::$employeeCodeSequence++ : null,
            'is_active' => true,
        ], $attributes));
    }

    protected function openPeriod(array $attributes = []): TimesheetPeriod
    {
        return TimesheetPeriod::create(array_merge([
            'week_number' => 20,
            'year' => 2026,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-17',
            'status' => 'open',
        ], $attributes));
    }

    protected function submittedTimesheet(User $user, TimesheetPeriod $period, Project $project, array $attributes = []): Timesheet
    {
        $timesheet = Timesheet::create(array_merge([
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'timesheet_period_id' => $period->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'total_regular_hours' => 8,
            'total_overtime_hours' => 0,
            'total_hours' => 8,
        ], $attributes));

        TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => $period->start_date,
            'day_name' => Carbon::parse($period->start_date)->format('l'),
            'attendance_code' => 'O100',
            'project_id' => $project->id,
            'regular_hours' => 8,
            'overtime_hours' => 0,
            'description' => null,
            'remarks' => null,
        ]);

        return $timesheet;
    }

    protected function validEntries(Project $project, array $overrides = []): array
    {
        $entries = [];

        foreach (Carbon::parse('2026-05-11')->daysUntil('2026-05-17') as $date) {
            $isMonday = $date->isMonday();
            $entries[] = array_merge([
                'work_date' => $date->toDateString(),
                'attendance_code' => $date->isWeekend() ? '' : 'O100',
                'project_id' => $isMonday ? $project->id : '',
                'regular_hours' => $isMonday ? 8 : 0,
                'overtime_hours' => 0,
                'remarks' => '',
            ], $overrides[$date->toDateString()] ?? []);
        }

        return $entries;
    }
}
