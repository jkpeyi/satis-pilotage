<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

/**
 * Catalog features of a product: those tagged with the product, plus those implemented in one
 * of its active versions. The catalog itself is shared by every product (RG-02).
 */
final class ProductCatalog
{
    public function __construct(public readonly CatalogTree $tree) {}

    public static function load(): self
    {
        return new self(CatalogTree::load());
    }

    /**
     * Feature ids in catalog order (module tree, then position).
     *
     * @return list<int>
     */
    public function featureIds(int $productId): array
    {
        $tagged = DB::table('feature_product')->where('product_id', $productId)->pluck('feature_id');
        $implemented = DB::table('implementations')
            ->join('versions', 'versions.id', '=', 'implementations.version_id')
            ->where('versions.product_id', $productId)
            ->whereNull('versions.archived_at')
            ->whereNull('implementations.archived_at')
            ->whereNotNull('implementations.feature_id')
            ->distinct()
            ->pluck('implementations.feature_id');

        $wanted = array_flip(array_map('intval', [...$tagged->all(), ...$implemented->all()]));

        $ordered = [];
        // Same order as the catalog tree: sub-modules first, then the module's own features.
        $walk = function (int $moduleId) use (&$walk, &$ordered, $wanted): void {
            foreach ($this->tree->childModuleIds($moduleId) as $childId) {
                $walk($childId);
            }
            foreach ($this->tree->featureIds($moduleId) as $featureId) {
                if (isset($wanted[$featureId])) {
                    $ordered[] = $featureId;
                }
            }
        };
        foreach ($this->tree->rootModuleIds() as $rootId) {
            $walk($rootId);
        }

        return $ordered;
    }

    /**
     * Module names from the root down to the feature's module, e.g. ["Crédit", "Octroi"].
     *
     * @return list<string>
     */
    public function modulePath(int $featureId): array
    {
        return array_map(
            fn (int $moduleId): string => $this->tree->modules[$moduleId]['name'],
            array_reverse($this->tree->featureAncestorModuleIds($featureId)),
        );
    }

    /**
     * "Crédit › Octroi › Simulation".
     */
    public function featureLabel(int $featureId): string
    {
        return implode(' › ', [...$this->modulePath($featureId), $this->tree->features[$featureId]['name'] ?? '?']);
    }
}
