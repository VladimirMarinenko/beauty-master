<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+30 days');
        $duration = 60;

        return [
            'user_id' => User::factory()->state(['role' => 'master']),
            'master_id' => null,
            'client_id' => Client::factory(),
            'client_user_id' => null,
            'service_id' => Service::factory(),
            'service_ids' => null,
            'client_name' => fake()->name(),
            'client_phone' => fake()->numerify('+79#########'),
            'client_email' => null,
            'start_time' => $start,
            'end_time' => (clone $start)->modify("+{$duration} minutes"),
            'status' => 'planned',
            'notes' => null,
            'total_price' => 1000,
            'total_duration' => $duration,
            'is_fixed_price' => true,
            'custom_duration' => null,
        ];
    }
}
