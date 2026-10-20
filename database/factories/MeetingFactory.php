<?php

namespace Database\Factories;

use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 20))->setTime(fake()->numberBetween(8, 15), 0);

        return [
            'title' => fake()->sentence(4),
            'type' => 'meeting',
            'description' => fake()->optional()->paragraph(),
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'location' => fake()->randomElement(['Board room', 'Conference room A', 'Online']),
            'status' => 'scheduled',
            'agenda' => "1. Opening\n2. Progress review\n3. AOB",
        ];
    }

    public function past(): static
    {
        return $this->state(function () {
            $start = now()->subDays(fake()->numberBetween(1, 20))->setTime(10, 0);

            return ['start_at' => $start, 'end_at' => $start->copy()->addHours(2)];
        });
    }
}
