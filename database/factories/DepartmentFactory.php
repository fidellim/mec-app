<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Department',
            'code' => fake()->unique()->lexify('???'),
            'is_active' => true,
        ];
    }
}
