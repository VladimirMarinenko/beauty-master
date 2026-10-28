<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'master']),
            'name' => fake()->randomElement(['Женская стрижка', 'Маникюр', 'Окрашивание', 'Укладка']),
            'price' => fake()->numberBetween(500, 5000),
            'duration' => fake()->randomElement([30, 45, 60, 90, 120]),
            'description' => null,
        ];
    }
}
