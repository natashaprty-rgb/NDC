<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number' => 'TEST/PO/'.fake()->unique()->numerify('######'),
            'procurement_request_id' => ProcurementRequest::factory()->status('ordered'),
            'supplier_id' => Supplier::factory(),
            'order_date' => today()->toDateString(),
            'total_amount' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'USD',
            'status' => 'issued',
        ];
    }
}
