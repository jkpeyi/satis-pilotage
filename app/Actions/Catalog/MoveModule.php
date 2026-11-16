<?php

namespace App\Actions\Catalog;

use App\Models\Module;
use App\Queries\CatalogTree;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Moves a module under another parent (or to the root) at a given position.
 * A module cannot be moved below itself (RG-01).
 */
final class MoveModule
{
    public function __construct(private readonly Positions $positions) {}

    public function handle(Module $module, ?Module $newParent, int $position): void
    {
        if ($newParent !== null) {
            $tree = CatalogTree::load(withArchived: true);

            if ($newParent->is($module) || in_array($newParent->id, $tree->descendantModuleIds($module->id), true)) {
                throw new DomainException('Un module ne peut pas être déplacé dans lui-même ni dans un de ses sous-modules.');
            }
        }

        DB::transaction(function () use ($module, $newParent, $position): void {
            $oldParentId = $module->parent_id;

            $module->parent()->associate($newParent);
            $module->save();

            $this->positions->insert($module, $position);

            if ($oldParentId !== $module->parent_id) {
                $this->positions->compactModules($oldParentId);
            }
        });
    }
}
