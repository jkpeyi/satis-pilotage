<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;

/**
 * Keeps sibling positions contiguous (0, 1, 2…) after an insertion, a move or a removal.
 * Siblings are the active sub-modules of the same parent, or the active features of the same module.
 */
final class Positions
{
    /**
     * Places the item at the given position among its siblings (at the end when null).
     */
    public function insert(Module|Feature $item, ?int $position): void
    {
        $ids = $this->siblingIds($item);
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $item->id));

        $position = $position === null ? count($ids) : max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$item->id]);

        $this->apply($item::class, $ids);
    }

    public function compactModules(?int $parentId): void
    {
        $ids = Module::query()->where('parent_id', $parentId)->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->pluck('id')->all();

        $this->apply(Module::class, $ids);
    }

    public function compactFeatures(int $moduleId): void
    {
        $ids = Feature::query()->where('module_id', $moduleId)->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->pluck('id')->all();

        $this->apply(Feature::class, $ids);
    }

    public function compactSiblingsOf(Module|Feature $item): void
    {
        $item instanceof Module ? $this->compactModules($item->parent_id) : $this->compactFeatures($item->module_id);
    }

    /**
     * @return list<int>
     */
    private function siblingIds(Module|Feature $item): array
    {
        $query = $item instanceof Module
            ? Module::query()->where('parent_id', $item->parent_id)
            : Feature::query()->where('module_id', $item->module_id);

        return $query->whereNull('archived_at')->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @param  class-string<Module|Feature>  $class
     * @param  array<int, int>  $orderedIds
     */
    private function apply(string $class, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $position => $id) {
            $class::query()->whereKey($id)->where('position', '!=', $position)->update(['position' => $position]);
        }
    }
}
