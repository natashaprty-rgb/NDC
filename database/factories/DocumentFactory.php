<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => 'NDC/DOC/'.now()->year.'/'.fake()->unique()->numerify('9####'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'owner_id' => User::factory(),
            'confidentiality' => 'internal',
            'status' => 'draft',
            'document_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'current_version' => 0,
            'created_by' => fn (array $attributes) => $attributes['owner_id'],
        ];
    }

    public function confidentiality(string $level): static
    {
        return $this->state(fn () => ['confidentiality' => $level]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived', 'archived_at' => now()]);
    }
}
