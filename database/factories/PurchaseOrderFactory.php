<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TPO-'.fake()->unique()->numerify('#####'),
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id' => Supplier::factory(),
            'order_date' => today(),
            'currency' => 'USD',
            'total_amount' => '0.00',
            'status' => 'issued',
        ];
    }
}
