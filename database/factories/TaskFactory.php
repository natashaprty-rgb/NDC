<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TEST-TSK-'.fake()->unique()->numerify('#######'),
            'title' => 'Test task '.fake()->unique()->numberBetween(1, 999999),
            'priority' => 'medium',
            'status' => 'todo',
            'progress' => 0,
            'due_date' => today()->addWeek(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['status' => 'in_progress', 'due_date' => today()->subDays(3)]);
    }
}
