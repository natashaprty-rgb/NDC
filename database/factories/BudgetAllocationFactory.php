<?php

namespace Database\Factories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\BudgetAllocation> */
class BudgetAllocationFactory extends Factory
{
    protected $model = \App\Models\BudgetAllocation::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'expense_category_id' => ExpenseCategoryFactory::new(),
            'amount' => '1000.00',
        ];
    }
}
