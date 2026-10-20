<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_number' => 'STF-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'job_title' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+252 61 #######'),
            'employment_status' => 'active',
            'employment_type' => 'permanent',
            'hire_date' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
            'gender' => fake()->randomElement(array_keys(Staff::GENDERS)),
        ];
    }

    /**
     * Fill the encrypted personal fields.
     */
    public function withSensitive(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'national_id' => fake()->numerify('SO-#########'),
            'personal_phone' => fake()->numerify('+252 61 #######'),
            'address' => fake()->address(),
            'emergency_contact' => fake()->name().' '.fake()->numerify('+252 61 #######'),
        ]);
    }
}
