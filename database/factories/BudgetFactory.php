<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Budget> */
class BudgetFactory extends Factory
{
    protected $model = \App\Models\Budget::class;

    public function definition(): array
    {
        return [
            'reference' => 'TEST-BGT-'.fake()->unique()->numerify('#####'),
            'title' => 'Test budget '.fake()->unique()->numberBetween(1, 99999),
            'fiscal_year' => (int) now()->format('Y'),
            'department_id' => Department::factory(),
            'currency' => 'USD',
            'status' => 'draft',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'approved_at' => now()]);
    }
}
