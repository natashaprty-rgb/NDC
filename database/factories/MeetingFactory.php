<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Meeting> */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 20))->setTime(10, 0);

        return [
            'reference' => 'T-MTG-'.fake()->unique()->numerify('#####'),
            'title' => 'Test meeting '.fake()->unique()->numberBetween(1, 99999),
            'meeting_type' => 'internal',
            'location' => 'Room 1',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'status' => 'scheduled',
        ];
    }
}
