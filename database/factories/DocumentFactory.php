<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Document> */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TST-DOC-'.fake()->unique()->numerify('######'),
            'title' => 'Test document '.fake()->unique()->numberBetween(1, 999999),
            'registration_date' => today(),
            'confidentiality' => 'internal',
            'status' => 'draft',
            'current_version' => 0,
        ];
    }
}
