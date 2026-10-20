<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'procurement_request_id' => ProcurementRequest::factory()->status('approved'),
            'supplier_id' => Supplier::factory(),
            'reference' => strtoupper(fake()->bothify('Q-####')),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'USD',
            'received_on' => today()->subDays(3)->toDateString(),
            'is_selected' => false,
        ];
    }
}
