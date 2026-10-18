<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TEST-PRJ-'.fake()->unique()->numerify('#######'),
            'name' => 'Test project '.fake()->unique()->numberBetween(1, 999999),
            'description' => 'Test description',
            'status' => 'in_progress',
            'priority' => 'medium',
            'start_date' => today()->subMonth(),
            'expected_end_date' => today()->addMonths(3),
        ];
    }
}
