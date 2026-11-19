<?php

namespace App\Actions\Catalog;

use App\Exceptions\DuplicateCatalogItem;
use App\Models\Feature;
use App\Models\Module;
use App\Queries\CatalogTree;
use App\Support\CatalogName;

/**
 * A name is unique across the whole catalog, modules and features together, among active items.
 * Archived items keep their name (a converted item leaves an archived copy behind, RG-14).
 */
final class EnsureUniqueCatalogName
{
    /**
     * @param  list<Module|Feature>  $except  items being renamed, converted or restored
     *
     * @throws DuplicateCatalogItem
     */
    public function handle(string $name, array $except = []): void
    {
        $existing = $this->find($name, $except);

        if ($existing !== null) {
            throw new DuplicateCatalogItem($existing, $this->path($existing));
        }
    }

    /**
     * @param  list<Module|Feature>  $except
     */
    public function find(string $name, array $except = []): Module|Feature|null
    {
        $key = CatalogName::key($name);
        if ($key === '') {
            return null;
        }

        $exceptIds = fn (string $class): array => array_map(
            fn (Module|Feature $item): int => (int) $item->getKey(),
            array_values(array_filter($except, fn (Module|Feature $item): bool => $item instanceof $class)),
        );

        return Module::query()->active()->where('name_key', $key)->whereKeyNot($exceptIds(Module::class))->first()
            ?? Feature::query()->active()->where('name_key', $key)->whereKeyNot($exceptIds(Feature::class))->first();
    }

    public function path(Module|Feature $item): string
    {
        $tree = CatalogTree::load(withArchived: true);
        $moduleIds = $item instanceof Module
            ? [...array_reverse($tree->ancestorModuleIds($item->id)), $item->id]
            : array_reverse($tree->featureAncestorModuleIds($item->id));

        $names = array_map(fn (int $id): string => $tree->modules[$id]['name'] ?? '?', $moduleIds);
        if ($item instanceof Feature) {
            $names[] = $item->name;
        }

        return implode(' › ', $names);
    }
}
