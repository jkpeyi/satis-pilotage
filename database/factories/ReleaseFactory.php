<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'name' => 'v'.fake()->unique()->numerify('#.#.##'),
            'commit_sha' => fake()->sha1(),
            'released_on' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
        ];
    }
}
