<?php

namespace App\Actions\Implementations;

use App\Exceptions\DetachRequiresConfirmation;
use App\Models\BusinessRule;
use App\Models\Implementation;
use App\Queries\CatalogTree;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Detaches catalog items from versions by archiving their implementations (RG-12).
 *
 * Detaching a module also detaches its subtree, so that no item stays attached without its parent.
 * When the implementations carry business rules or issues, the caller must confirm.
 */
final class DetachCatalogItems
{
    /**
     * @param  list<int>  $versionIds
     * @param  list<int>  $moduleIds
     * @param  list<int>  $featureIds
     * @return int Number of implementations archived.
     *
     * @throws DetachRequiresConfirmation
     */
    public function handle(array $versionIds, array $moduleIds, array $featureIds = [], bool $confirmed = false): int
    {
        $tree = CatalogTree::load(withArchived: true);
        $modules = $moduleIds;
        $features = $featureIds;

        foreach ($moduleIds as $moduleId) {
            array_push($modules, ...$tree->descendantModuleIds($moduleId));
            array_push($features, ...$tree->featureIdsInSubtree($moduleId));
        }

        $implementationIds = Implementation::query()
            ->whereIn('version_id', $versionIds)
            ->whereNull('archived_at')
            ->where(fn ($query) => $query->whereIn('module_id', array_unique($modules))->orWhereIn('feature_id', array_unique($features)))
            ->pluck('id')
            ->all();

        if ($implementationIds === []) {
            return 0;
        }

        if (! $confirmed) {
            $rulesCount = BusinessRule::query()->whereIn('implementation_id', $implementationIds)->whereNull('archived_at')->count();
            $issuesCount = DB::table('implementation_issue')
                ->join('issues', 'issues.id', '=', 'implementation_issue.issue_id')
                ->whereIn('implementation_issue.implementation_id', $implementationIds)
                ->whereNull('issues.archived_at')
                ->distinct()
                ->count('issues.id');

            if ($rulesCount > 0 || $issuesCount > 0) {
                throw new DetachRequiresConfirmation($rulesCount, $issuesCount);
            }
        }

        return Implementation::query()->whereKey($implementationIds)->update([
            'archived_at' => now(),
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);
    }
}
