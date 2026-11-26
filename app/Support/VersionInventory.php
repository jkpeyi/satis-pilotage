<?php

namespace App\Support;

use App\Enums\ComponentType;
use App\Enums\RepositoryRole;
use App\Models\Component;
use App\Models\Repository;
use App\Models\Version;

/**
 * The applications inventory columns of a version, shared by the versions list and L1: stacks
 * by role, database engines and repository URLs. Expects repositories and components loaded.
 */
final class VersionInventory
{
    /**
     * Technologies and versions of the version's repositories with this role, e.g.
     * "laravel 9.52.16, php 8.1.27"; empty when none is entered.
     */
    public static function stack(Version $version, RepositoryRole $role): string
    {
        return $version->repositories
            ->filter(fn (Repository $repository): bool => $repository->archived_at === null && $repository->role === $role)
            ->flatMap(fn (Repository $repository): array => collect($repository->stack ?? [])
                ->map(fn (string $technologyVersion, string $technology): string => $technology.' '.$technologyVersion)->values()->all())
            ->unique()->sort()->implode(', ');
    }

    /**
     * Database engines, from the « base de données » components, e.g. "MySQL 5.7".
     */
    public static function databases(Version $version): string
    {
        return $version->components
            ->filter(fn (Component $component): bool => $component->archived_at === null && $component->type === ComponentType::Database)
            ->map(fn (Component $component): ?string => $component->technologyLabel())
            ->filter()->unique()->implode(', ');
    }

    /**
     * @return list<string>
     */
    public static function repositoryUrls(Version $version): array
    {
        return $version->repositories
            ->filter(fn (Repository $repository): bool => $repository->archived_at === null && $repository->web_url !== null)
            ->pluck('web_url')->values()->all();
    }
}
