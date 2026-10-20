<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementRequest>
 */
class ProcurementRequestFactory extends Factory
{
    protected $model = ProcurementRequest::class;

    public function definition(): array
    {
        return [
            'reference_number' => 'TEST/PR/'.fake()->unique()->numerify('######'),
            'title' => fake()->sentence(4),
            'justification' => fake()->sentence(),
            'requested_by' => User::factory(),
            'status' => 'draft',
            'estimated_total' => 0,
            'currency' => 'USD',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'submitted_at' => $status === 'draft' ? null : now(),
        ]);
    }
}
