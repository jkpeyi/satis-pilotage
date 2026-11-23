<?php

namespace App\Actions\Implementations;

use App\Actions\Catalog\MoveFeature;
use App\Actions\Catalog\MoveModule;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Version;
use App\Queries\CatalogTree;
use DomainException;

/**
 * Drag and drop in a version's scope: the scope only shows the items of that version, so the
 * position among the visible siblings is translated into a position among all the siblings of the
 * catalog. The hierarchy belongs to the catalog (RG-02): every version sees the move.
 */
final class MoveItemInVersionScope
{
    public function __construct(
        private readonly MoveModule $moveModule,
        private readonly MoveFeature $moveFeature,
    ) {}

    /**
     * @param  'module'|'feature'  $kind
     * @param  int  $visibleIndex  position among the version's items under the new parent
     */
    public function handle(Version $version, string $kind, int $itemId, ?int $parentId, int $visibleIndex): void
    {
        if ($kind === 'feature' && $parentId === null) {
            throw new DomainException('Une fonctionnalité appartient toujours à un module.');
        }

        $tree = CatalogTree::load();
        $attached = $version->implementations()->whereNull('archived_at')
            ->get(['module_id', 'feature_id']);
        $attachedIds = $kind === 'module'
            ? $attached->pluck('module_id')->filter()->map(fn ($id): int => (int) $id)->all()
            : $attached->pluck('feature_id')->filter()->map(fn ($id): int => (int) $id)->all();

        if ($parentId !== null && ! $attached->pluck('module_id')->contains($parentId)) {
            throw new DomainException('Le module de destination n’est pas dans le périmètre de cette version.');
        }

        $siblings = $kind === 'module'
            ? ($parentId === null ? $tree->rootModuleIds() : $tree->childModuleIds($parentId))
            : $tree->featureIds((int) $parentId);
        $siblings = array_values(array_filter($siblings, fn (int $id): bool => $id !== $itemId));
        $visible = array_values(array_filter($siblings, fn (int $id): bool => in_array($id, $attachedIds, true)));

        $position = match (true) {
            $visibleIndex < count($visible) => (int) array_search($visible[max(0, $visibleIndex)], $siblings, true),
            $visible !== [] => (int) array_search(end($visible), $siblings, true) + 1,
            default => count($siblings),
        };

        if ($kind === 'module') {
            $this->moveModule->handle(Module::query()->findOrFail($itemId), $parentId !== null ? Module::query()->findOrFail($parentId) : null, $position);
        } else {
            $this->moveFeature->handle(Feature::query()->findOrFail($itemId), Module::query()->findOrFail((int) $parentId), $position);
        }
    }
}
