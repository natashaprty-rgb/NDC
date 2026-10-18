<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Staff> */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_number' => 'T-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'job_title' => 'Officer',
            'official_email' => fake()->unique()->safeEmail(),
            'employment_status' => 'active',
            'employment_type' => 'permanent',
        ];
    }
}
