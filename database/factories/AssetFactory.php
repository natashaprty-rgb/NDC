<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only; the system never seeds equipment records.
 *
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'asset_tag' => 'TEST-'.fake()->unique()->numerify('######'),
            'name' => fake()->randomElement(['Laptop', 'Desktop computer', 'Printer', 'Network switch', 'Office desk']),
            'manufacturer' => fake()->company(),
            'model' => strtoupper(fake()->bothify('??-###')),
            'serial_number' => strtoupper(fake()->bothify('SN########')),
            'purchase_date' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'purchase_cost' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'USD',
            'condition' => 'good',
            'status' => 'available',
        ];
    }

    public function underWarrantyUntil(string $date): static
    {
        return $this->state(fn () => ['warranty_expiry' => $date]);
    }
}
