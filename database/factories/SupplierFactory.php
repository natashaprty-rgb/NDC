<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TS-'.fake()->unique()->numerify('#####'),
            'name' => 'Test Supplier '.fake()->unique()->numberBetween(1, 99999),
            'email' => fake()->safeEmail(),
            'status' => 'active',
        ];
    }
}
