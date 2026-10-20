<?php

namespace Database\Factories;

use App\Models\Correspondence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Correspondence>
 */
class CorrespondenceFactory extends Factory
{
    public function definition(): array
    {
        $direction = fake()->randomElement(['incoming', 'outgoing']);

        return [
            // Factory references use their own series so they never collide with ReferenceNumber::next().
            'reference_number' => 'TEST/'.($direction === 'incoming' ? 'IN' : 'OUT').'/'.fake()->unique()->numerify('######'),
            'direction' => $direction,
            'external_reference' => fake()->optional()->bothify('MIN/###/??'),
            'subject' => fake()->sentence(6),
            'sender' => $direction === 'incoming' ? fake()->company() : 'National Data Center Somalia',
            'recipient' => $direction === 'incoming' ? 'National Data Center Somalia' : fake()->company(),
            'letter_date' => fake()->dateTimeBetween('-2 months', '-1 week'),
            'received_or_sent_date' => fake()->dateTimeBetween('-1 week', 'now'),
            'status' => $direction === 'incoming' ? 'received' : 'pending',
            'priority' => 'normal',
            'is_confidential' => false,
            'summary' => fake()->paragraph(),
        ];
    }

    public function incoming(): static
    {
        return $this->state(fn () => [
            'direction' => 'incoming',
            'reference_number' => 'TEST/IN/'.fake()->unique()->numerify('######'),
            'status' => 'received',
        ]);
    }

    public function outgoing(): static
    {
        return $this->state(fn () => [
            'direction' => 'outgoing',
            'reference_number' => 'TEST/OUT/'.fake()->unique()->numerify('######'),
            'status' => 'pending',
        ]);
    }

    public function confidential(): static
    {
        return $this->state(fn () => ['is_confidential' => true]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['status' => 'received', 'response_due_date' => today()->subDays(3)]);
    }
}
