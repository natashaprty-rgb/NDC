<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '+1 month');

        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('#####'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'objectives' => fake()->paragraph(),
            'location' => fake()->city(),
            'status' => 'in_progress',
            'priority' => 'medium',
            'start_date' => $start,
            'expected_end_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'planned_budget' => fake()->randomFloat(2, 1000, 500000),
            'currency' => 'USD',
            'progress' => fake()->numberBetween(0, 90),
        ];
    }

    public function delayed(): static
    {
        return $this->state(fn () => [
            'status' => 'in_progress',
            'start_date' => now()->subMonths(6),
            'expected_end_date' => now()->subWeek(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
