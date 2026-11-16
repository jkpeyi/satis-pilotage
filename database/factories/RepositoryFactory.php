<?php

namespace Database\Factories;

use App\Enums\RepositoryRole;
use App\Models\Repository;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_id' => Version::factory(),
            'path' => 'satis/'.fake()->unique()->slug(2),
            'role' => RepositoryRole::Backend,
            'production_branch' => 'main',
            'active_branches' => ['main'],
            'stack' => ['php' => '8.1', 'laravel' => '9.52.16'],
        ];
    }
}
