<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'remember_token' => Str::random(10),
            'job_title' => null,
            'gender' => null,
            'joining_date' => null,
            'marital_status' => null,
            'eligible_for_parental_leave' => false,
            'eligible_for_maternity_leave' => false,
            'eligible_for_paternity_leave' => false,
            'eligible_for_vawc_leave' => false,
            'eligible_for_special_women_leave' => false,
            'is_solo_parent' => false,
            'role' => 'employee',
            'is_active' => true,
        ];
    }
}
