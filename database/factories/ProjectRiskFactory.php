<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ProjectRisk> */
class ProjectRiskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => 'risk',
            'title' => 'Risk '.fake()->numberBetween(1, 9999),
            'likelihood' => 'medium',
            'impact' => 'medium',
            'status' => 'open',
        ];
    }
}
