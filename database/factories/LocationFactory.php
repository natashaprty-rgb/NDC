<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'L'.fake()->unique()->numerify('####'),
            'name' => 'Test location '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
