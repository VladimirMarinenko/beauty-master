<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'master_id' => User::factory()->state(['role' => 'master']),
            'client_user_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->numerify('+79#########'),
            'phone2' => null,
            'contact' => null,
            'is_permanent' => false,
            'notified_at' => null,
        ];
    }
}
