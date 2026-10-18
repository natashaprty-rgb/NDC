<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Correspondence> */
class CorrespondenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TST-COR-'.fake()->unique()->numerify('######'),
            'direction' => 'incoming',
            'subject' => 'Test letter '.fake()->unique()->numberBetween(1, 999999),
            'sender' => 'Test Sender Organisation',
            'recipient' => 'NDC',
            'received_or_sent_date' => today(),
            'priority' => 'normal',
            'confidentiality' => 'internal',
            'status' => 'registered',
        ];
    }
}
