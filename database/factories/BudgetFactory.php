<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now()->format('Y');

        return [
            'code' => 'BUD-'.fake()->unique()->numerify('######'),
            'name' => fake()->randomElement(['Operations', 'ICT Infrastructure', 'Training', 'Data Centre Readiness']).' '.$year,
            'fiscal_year' => (string) $year,
            'department_id' => fn () => Department::query()->create([
                'name' => 'Department '.fake()->unique()->numerify('####'),
                'code' => 'D'.fake()->unique()->numerify('#####'),
                'is_active' => true,
            ])->id,
            'amount' => 10000,
            'currency' => 'USD',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted']);
    }

    /**
     * An approved budget. The approval record itself is not created; use the
     * ApprovalService in tests that exercise the workflow.
     */
    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}
