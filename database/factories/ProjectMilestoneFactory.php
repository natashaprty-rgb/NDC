<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ProjectMilestone> */
class ProjectMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'Milestone '.fake()->numberBetween(1, 9999),
            'due_date' => today()->addMonth(),
            'status' => 'pending',
        ];
    }
}
