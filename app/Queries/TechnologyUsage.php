<?php

namespace App\Queries;

use App\Actions\Referential\ResolveStackSupport;
use App\Models\Component;
use App\Models\Repository;
use App\Models\Version;
use Illuminate\Support\Collection;

/**
 * Active client versions using each technology cycle, through the stacks of their repositories
 * and their components (database engines included).
 */
final class TechnologyUsage
{
    public function __construct(private readonly ResolveStackSupport $resolveStackSupport) {}

    /**
     * @return array<int, Collection<int, Version>> technology id => versions, by name
     */
    public function versionsByTechnology(): array
    {
        $usage = [];
        $add = function (?int $technologyId, Version $version) use (&$usage): void {
            if ($technologyId !== null) {
                $usage[$technologyId][$version->id] = $version;
            }
        };

        Repository::query()->active()->whereNotNull('stack')
            ->whereHas('version', fn ($query) => $query->whereNull('archived_at'))->with('version.customer')->get()
            ->each(function (Repository $repository) use ($add): void {
                foreach ($repository->stack ?? [] as $component => $version) {
                    $add($this->resolveStackSupport->match(strtolower((string) $component), (string) $version)?->id, $repository->version);
                }
            });

        Component::query()->active()->whereNotNull('technology')->whereNotNull('technology_version')
            ->whereHas('version', fn ($query) => $query->whereNull('archived_at'))->with('version.customer')->get()
            ->each(function (Component $component) use ($add): void {
                $add($this->resolveStackSupport->match(strtolower((string) $component->technology), (string) $component->technology_version)?->id, $component->version);
            });

        return array_map(fn (array $versions): Collection => collect($versions)->sortBy('name')->values(), $usage);
    }
}
