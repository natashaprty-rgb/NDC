<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectRisk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRisk>
 */
class ProjectRiskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => fake()->randomElement(['risk', 'challenge', 'delay']),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'likelihood' => 'medium',
            'impact' => 'medium',
            'status' => 'open',
            'corrective_action' => fake()->sentence(),
            'identified_on' => now()->subDays(10),
        ];
    }
}
