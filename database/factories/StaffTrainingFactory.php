<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffTraining;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTraining>
 */
class StaffTrainingFactory extends Factory
{
    protected $model = StaffTraining::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 years', '-1 week');

        return [
            'staff_id' => Staff::factory(),
            'title' => fake()->randomElement(['Data protection essentials', 'Network administration', 'Project management', 'Cyber security awareness']),
            'provider' => fake()->company(),
            'type' => fake()->randomElement(array_keys(StaffTraining::TYPES)),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+5 days')->format('Y-m-d'),
            'certificate_number' => fake()->bothify('CERT-####-??'),
        ];
    }
}
