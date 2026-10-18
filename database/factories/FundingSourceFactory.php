<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\FundingSource> */
class FundingSourceFactory extends Factory
{
    protected $model = \App\Models\FundingSource::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('FS??##')),
            'name' => 'Test Funding Source '.fake()->unique()->numberBetween(1, 99999),
            'type' => 'government',
            'is_active' => true,
        ];
    }
}
