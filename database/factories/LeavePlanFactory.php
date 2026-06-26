<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeavePlanFactory extends Factory
{
    public function definition(): array
    {
        $department = Department::factory();

        return [
            'user_id' => User::factory(),
            'department_id' => $department,
            'attendance_code' => 'L100',
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-11',
            'duration_type' => 'full_day',
            'half_day_period' => null,
            'reason' => fake()->sentence(),
            'status' => 'draft',
        ];
    }
}
