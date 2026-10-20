<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementRequestItem>
 */
class ProcurementRequestItemFactory extends Factory
{
    protected $model = ProcurementRequestItem::class;

    public function definition(): array
    {
        return [
            'procurement_request_id' => ProcurementRequest::factory(),
            'description' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 10),
            'unit' => 'unit',
            'estimated_unit_cost' => fake()->randomFloat(2, 10, 1000),
            'quantity_received' => 0,
        ];
    }
}
