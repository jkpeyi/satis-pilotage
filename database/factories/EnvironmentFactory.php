<?php

namespace Database\Factories;

use App\Enums\EnvironmentType;
use App\Models\Environment;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Environment>
 */
class EnvironmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_id' => Version::factory(),
            'type' => EnvironmentType::Production,
            'name' => 'Production',
            'url' => fake()->url(),
            'database_engine' => 'MySQL',
            'database_version' => '5.7',
        ];
    }
}
