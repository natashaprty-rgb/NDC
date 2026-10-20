<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'deliverables' => fake()->sentence(),
            'due_date' => fake()->dateTimeBetween('now', '+6 months'),
            'status' => 'pending',
            'sort_order' => 0,
        ];
    }
}
