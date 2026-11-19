<?php

namespace App\Actions\Implementations;

use App\Enums\ImplementationStatus;
use App\Models\Implementation;
use App\Queries\CatalogTree;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Attaches catalog items to one or more versions (EF-204, RG-04).
 *
 * Ancestors are always attached so that the version tree stays consistent; descendants are
 * attached unless $withDescendants is false. An implementation archived earlier is restored
 * instead of being created again (unique per version and item, RG-03). New ones start with an unknown presence.
 */
final class AttachCatalogItems
{
    /**
     * @param  list<int>  $versionIds
     * @param  list<int>  $moduleIds
     * @param  list<int>  $featureIds
     * @return int Number of implementations created or restored.
     */
    public function handle(array $versionIds, array $moduleIds, array $featureIds = [], bool $withDescendants = true): int
    {
        [$targetModuleIds, $targetFeatureIds] = $this->targets($moduleIds, $featureIds, $withDescendants);

        if ($versionIds === [] || ($targetModuleIds === [] && $targetFeatureIds === [])) {
            return 0;
        }

        return DB::transaction(function () use ($versionIds, $targetModuleIds, $targetFeatureIds): int {
            $now = now();
            $userId = Auth::id();
            $count = 0;

            foreach ($versionIds as $versionId) {
                $existing = Implementation::query()
                    ->where('version_id', $versionId)
                    ->where(fn ($query) => $query->whereIn('module_id', $targetModuleIds)->orWhereIn('feature_id', $targetFeatureIds))
                    ->get(['id', 'module_id', 'feature_id', 'archived_at']);

                $toRestore = $existing->whereNotNull('archived_at')->modelKeys();
                if ($toRestore !== []) {
                    Implementation::query()->whereKey($toRestore)->update(['archived_at' => null, 'updated_by' => $userId, 'updated_at' => $now]);
                    $count += count($toRestore);
                }

                $rows = [];
                $existingModules = $existing->pluck('module_id')->filter()->all();
                $existingFeatures = $existing->pluck('feature_id')->filter()->all();

                foreach (array_diff($targetModuleIds, $existingModules) as $moduleId) {
                    $rows[] = $this->row($versionId, $moduleId, null, $userId, $now);
                }
                foreach (array_diff($targetFeatureIds, $existingFeatures) as $featureId) {
                    $rows[] = $this->row($versionId, null, $featureId, $userId, $now);
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    Implementation::query()->insert($chunk);
                }
                $count += count($rows);
            }

            return $count;
        });
    }

    /**
     * @param  list<int>  $moduleIds
     * @param  list<int>  $featureIds
     * @return array{0: list<int>, 1: list<int>}
     */
    private function targets(array $moduleIds, array $featureIds, bool $withDescendants): array
    {
        $tree = CatalogTree::load();
        $modules = [];
        $features = [];

        foreach ($moduleIds as $moduleId) {
            if (! isset($tree->modules[$moduleId])) {
                continue;
            }
            array_push($modules, $moduleId, ...$tree->ancestorModuleIds($moduleId));

            if ($withDescendants) {
                array_push($modules, ...$tree->descendantModuleIds($moduleId));
                array_push($features, ...$tree->featureIdsInSubtree($moduleId));
            }
        }

        foreach ($featureIds as $featureId) {
            if (! isset($tree->features[$featureId])) {
                continue;
            }
            $features[] = $featureId;
            array_push($modules, ...$tree->featureAncestorModuleIds($featureId));
        }

        return [array_values(array_unique($modules)), array_values(array_unique($features))];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $versionId, ?int $moduleId, ?int $featureId, int|string|null $userId, \DateTimeInterface $now): array
    {
        return [
            'version_id' => $versionId,
            'module_id' => $moduleId,
            'feature_id' => $featureId,
            'status' => ImplementationStatus::Unknown->value,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
