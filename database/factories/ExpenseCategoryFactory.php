<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ExpenseCategory> */
class ExpenseCategoryFactory extends Factory
{
    protected $model = \App\Models\ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('EC??##')),
            'name' => 'Test Category '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
