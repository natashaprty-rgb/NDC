<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Department> */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('D??#')),
            'name' => 'Test Department '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
