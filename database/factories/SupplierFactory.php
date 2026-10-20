<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => 'supplier',
            'contact_person' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+252 61 #######'),
            'status' => 'active',
        ];
    }
}
