<?php

namespace App\Actions\Catalog;

use App\Exceptions\DuplicateCatalogItem;
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
    public function __construct(
        private readonly Positions $positions,
        private readonly EnsureUniqueCatalogName $ensureUniqueName,
    ) {}

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

        if (! $archive) {
            $this->ensureRestorable($moduleIds, $featureIds);
        }

        DB::transaction(function () use ($item, $archive, $moduleIds, $featureIds, $tree): void {
            if ($archive && $item instanceof Module) {
                $featureIds = $this->keepSharedFeatures($featureIds, $moduleIds, $tree);
            }

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

    /**
     * Restoring must not bring back a name now used by another active item, nor two items of the
     * restored subtree with the same name.
     *
     * @param  list<int>  $moduleIds
     * @param  list<int>  $featureIds
     */
    private function ensureRestorable(array $moduleIds, array $featureIds): void
    {
        $items = [
            ...Module::query()->whereKey($moduleIds)->archived()->get()->all(),
            ...Feature::query()->whereKey($featureIds)->archived()->get()->all(),
        ];
        $seen = [];

        foreach ($items as $restored) {
            $key = $restored->name_key;
            if (isset($seen[$key])) {
                throw new DuplicateCatalogItem($seen[$key], $this->ensureUniqueName->path($seen[$key]));
            }
            $seen[$key] = $restored;
            $this->ensureUniqueName->handle($restored->name);
        }
    }

    /**
     * A feature of the archived subtree that also appears in an active module elsewhere stays
     * active: that module becomes its main module (RG-16).
     *
     * @param  list<int>  $featureIds
     * @param  list<int>  $archivedModuleIds
     * @return list<int> features to archive
     */
    private function keepSharedFeatures(array $featureIds, array $archivedModuleIds, CatalogTree $tree): array
    {
        $toArchive = [];

        foreach ($featureIds as $featureId) {
            $elsewhere = array_values(array_filter(
                $tree->otherModuleIds($featureId),
                fn (int $moduleId): bool => ! in_array($moduleId, $archivedModuleIds, true) && ! ($tree->modules[$moduleId]['archived'] ?? true),
            ));

            if ($elsewhere === []) {
                $toArchive[] = $featureId;

                continue;
            }

            DB::table('module_feature')->where('feature_id', $featureId)->where('module_id', $elsewhere[0])->delete();
            $feature = Feature::query()->findOrFail($featureId);
            $feature->module()->associate($elsewhere[0]);
            $feature->save();
            $this->positions->insert($feature, null);
        }

        return $toArchive;
    }
}
