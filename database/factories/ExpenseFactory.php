<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory()->approved(),
            'category_id' => fn () => Category::query()->firstOrCreate(['type' => 'expense', 'name' => 'Equipment'], ['is_active' => true])->id,
            'reference' => 'INV-'.fake()->numerify('#####'),
            'description' => fake()->sentence(4),
            'payee' => fake()->company(),
            'amount' => 500,
            'expense_date' => now()->toDateString(),
            'status' => 'pending',
            'recorded_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }
}
