<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\SupplierContract> */
class SupplierContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TC-'.fake()->unique()->numerify('#####'),
            'supplier_id' => Supplier::factory(),
            'title' => 'Test contract',
            'start_date' => today()->subMonth(),
            'end_date' => today()->addYear(),
            'contract_value' => '1000.00',
            'currency' => 'USD',
            'status' => 'active',
        ];
    }
}
