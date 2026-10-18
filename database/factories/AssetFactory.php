<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_tag' => 'T-AST-'.fake()->unique()->numerify('#####'),
            'name' => 'Test laptop '.fake()->unique()->numberBetween(1, 99999),
            'category_id' => fn () => AssetCategory::query()->firstOrCreate(['code' => 'TST'], ['name' => 'Test category', 'asset_class' => 'ict'])->id,
            'condition' => 'good',
            'status' => 'in_stock',
            'currency' => 'USD',
        ];
    }
}
