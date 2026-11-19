<?php

namespace Database\Factories;

use App\Enums\IntegrationProtocol;
use App\Models\Component;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_component_id' => Component::factory(),
            'external_system' => fake()->company(),
            'protocol' => IntegrationProtocol::Rest,
        ];
    }
}
