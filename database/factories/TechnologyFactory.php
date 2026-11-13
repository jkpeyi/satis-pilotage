<?php

namespace Database\Factories;

use App\Models\Technology;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technology>
 */
class TechnologyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'component' => fake()->randomElement(['php', 'laravel', 'react', 'node', 'mysql']),
            'cycle' => (string) fake()->unique()->numberBetween(1, 999),
            'end_of_support' => fake()->dateTimeBetween('-2 years', '+2 years'),
        ];
    }
}
