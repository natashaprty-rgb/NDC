<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRequest> */
class PurchaseRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TPR-'.fake()->unique()->numerify('#####'),
            'title' => 'Test purchase request',
            'requested_by' => User::factory(),
            'currency' => 'USD',
            'estimated_total' => '0.00',
            'status' => 'draft',
        ];
    }

    /** Adds one line item: quantity x unit cost. */
    public function withItem(string $quantity = '10', string $unitCost = '5.00'): static
    {
        return $this->afterCreating(function (PurchaseRequest $pr) use ($quantity, $unitCost) {
            $total = \App\Support\Money::multiply($quantity, $unitCost);
            $pr->items()->create(['description' => 'Test item', 'quantity' => $quantity, 'unit' => 'unit', 'estimated_unit_cost' => $unitCost, 'line_total' => $total]);
            $pr->update(['estimated_total' => $total]);
        });
    }
}
