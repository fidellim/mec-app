<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_code' => fake()->unique()->bothify('JOB-####'),
            'project_name' => fake()->catchPhrase(),
            'client_name' => fake()->company(),
            'is_active' => true,
        ];
    }
}
