<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Expense> */
class ExpenseFactory extends Factory
{
    protected $model = \App\Models\Expense::class;

    /** Use with ->for($budget)->state(['expense_category_id' => ...]). */
    public function definition(): array
    {
        return [
            'reference' => 'TEST-EXP-'.fake()->unique()->numerify('######'),
            'description' => 'Test expense',
            'amount' => '100.00',
            'currency' => 'USD',
            'expense_date' => today(),
            'status' => 'recorded',
            'recorded_by' => User::factory(),
        ];
    }
}
