<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingActionItem>
 */
class MeetingActionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'type' => 'action',
            'description' => fake()->sentence(8),
            'due_date' => today()->addWeek(),
            'status' => 'open',
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_date' => today()->subDays(2), 'status' => 'open']);
    }
}
