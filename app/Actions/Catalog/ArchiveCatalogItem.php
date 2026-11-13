<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use App\Queries\CatalogTree;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Archives or restores a catalog item (RG-12).
 *
 * Archiving a module archives its whole subtree. Restoring an item also restores its ancestors,
 * so that it is visible again in the tree; restoring a module restores its subtree.
 * Implementations are kept: their history stays readable.
 */
final class ArchiveCatalogItem
{
    public function __construct(private readonly Positions $positions) {}

    public function handle(Module|Feature $item, bool $archive = true): void
    {
        $tree = CatalogTree::load(withArchived: true);

        if ($item instanceof Module) {
            $moduleIds = [$item->id, ...$tree->descendantModuleIds($item->id)];
            $featureIds = $tree->featureIdsInSubtree($item->id);
        } else {
            $moduleIds = [];
            $featureIds = [$item->id];
        }

        if (! $archive) {
            $ancestorIds = $item instanceof Module ? $tree->ancestorModuleIds($item->id) : $tree->featureAncestorModuleIds($item->id);
            $moduleIds = [...$moduleIds, ...$ancestorIds];
        }

        DB::transaction(function () use ($item, $archive, $moduleIds, $featureIds): void {
            $values = ['archived_at' => $archive ? now() : null, 'updated_by' => Auth::id(), 'updated_at' => now()];

            Module::query()->whereKey($moduleIds)->update($values);
            Feature::query()->whereKey($featureIds)->update($values);

            if ($archive) {
                $this->positions->compactSiblingsOf($item);
            } else {
                $this->positions->insert($item->refresh(), null);
            }
        });
    }
}
