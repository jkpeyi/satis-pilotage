<?php

namespace App\Actions\Implementations;

use App\Actions\Catalog\LinkFeatureToModule;
use App\Actions\Catalog\MoveFeature;
use App\Actions\Catalog\MoveModule;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Version;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The name typed in a version's scope already exists in the catalog (RG-15): reuse that item
 * instead of creating a duplicate, and attach it to the version (with its ancestors, RG-04).
 *
 * - "attach": the item stays where it is in the catalog;
 * - "move": the item moves under the given module, for every version (RG-02);
 * - "link": the feature also appears under the given module (RG-16).
 */
final class ReuseCatalogItemInVersion
{
    public function __construct(
        private readonly AttachCatalogItems $attachCatalogItems,
        private readonly MoveModule $moveModule,
        private readonly MoveFeature $moveFeature,
        private readonly LinkFeatureToModule $linkFeatureToModule,
    ) {}

    /**
     * @param  'attach'|'move'|'link'  $mode
     * @return int implementations created or restored
     */
    public function handle(Version $version, Module|Feature $item, ?int $parentModuleId, string $mode, bool $withDescendants = false): int
    {
        if ($item->isArchived()) {
            throw new DomainException("« {$item->name} » est archivé : restaurez-le depuis le catalogue.");
        }

        return DB::transaction(function () use ($version, $item, $parentModuleId, $mode, $withDescendants): int {
            $parent = $parentModuleId !== null ? Module::query()->findOrFail($parentModuleId) : null;

            if ($mode === 'move') {
                if ($item instanceof Module) {
                    $this->moveModule->handle($item, $parent, Module::query()->where('parent_id', $parentModuleId)->whereNull('archived_at')->count());
                } elseif ($parent !== null) {
                    $this->moveFeature->handle($item, $parent, Feature::query()->where('module_id', $parent->id)->whereNull('archived_at')->count());
                } else {
                    throw new DomainException('Une fonctionnalité appartient toujours à un module.');
                }
            } elseif ($mode === 'link') {
                if (! $item instanceof Feature || $parent === null) {
                    throw new DomainException('Seule une fonctionnalité peut apparaître sous plusieurs modules.');
                }
                $this->linkFeatureToModule->handle($item, $parent);
            }

            return $item instanceof Module
                ? $this->attachCatalogItems->handle([$version->id], [$item->id], [], $withDescendants)
                : $this->attachCatalogItems->handle([$version->id], [], [$item->id]);
        });
    }
}
