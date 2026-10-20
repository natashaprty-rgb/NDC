<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetAllocation>
 */
class BudgetAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'category_id' => fn () => Category::query()->firstOrCreate(['type' => 'expense', 'name' => 'Equipment'], ['is_active' => true])->id,
            'description' => fake()->sentence(4),
            'planned_amount' => 1000,
        ];
    }
}
