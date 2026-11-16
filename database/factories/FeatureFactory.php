<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'name' => fake()->unique()->words(3, true),
            'standard_description' => fake()->sentence(),
            'position' => 0,
        ];
    }
}
