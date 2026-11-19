<?php

namespace Database\Factories;

use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_id' => Version::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => ComponentType::Api,
            'technology' => 'Laravel',
            'technology_version' => '9.52.16',
        ];
    }
}
