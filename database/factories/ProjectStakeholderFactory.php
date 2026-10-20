<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectStakeholder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectStakeholder>
 */
class ProjectStakeholderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => fake()->randomElement(['contractor', 'consultant', 'stakeholder', 'contact']),
            'name' => fake()->name(),
            'organization' => fake()->company(),
            'role' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
