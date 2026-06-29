<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'holiday_date' => '2026-05-12',
            'region' => Holiday::REGION_GLOBAL,
            'is_active' => true,
        ];
    }
}
