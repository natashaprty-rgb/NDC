<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'expected_deliverable' => fake()->sentence(),
            'assigned_to' => User::factory(),
            'priority' => 'medium',
            'status' => 'pending',
            'progress' => 0,
            'start_date' => now()->subDays(3),
            'due_date' => now()->addWeeks(2),
            'requires_review' => true,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'in_progress', 'progress' => 40]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted', 'progress' => 100, 'submitted_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'progress' => 100, 'completed_at' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['status' => 'in_progress', 'start_date' => now()->subMonth(), 'due_date' => now()->subDays(2)]);
    }
}
