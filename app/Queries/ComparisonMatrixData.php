<?php

namespace App\Queries;

use App\Models\Version;
use Illuminate\Support\Facades\DB;

/**
 * Data of the comparison matrix (EF-302): catalog rows in tree order × selected versions.
 *
 * Built with three queries whatever the size, and sent as compact JSON to the browser,
 * which filters and renders it (pivot, differences, collapse) without round trips.
 * Rows that no selected version uses are left out. An empty cell means "not attached" (RG-05).
 */
final class ComparisonMatrixData
{
    /**
     * @param  list<int>  $versionIds  in display order
     * @return array{
     *     versions: list<array{id: int, name: string, customer: ?string, reference: bool}>,
     *     rows: list<array{key: string, type: string, id: int, name: string, code: ?string, depth: int, parent: ?string}>,
     *     cells: array<string, array<int, array{id: int, s: string, d: bool, r: int, i: int}>>
     * }
     */
    public function build(array $versionIds, ?int $rootModuleId = null): array
    {
        $versions = Version::query()->with(['customer', 'product'])->findMany($versionIds)->keyBy('id');
        $versionIds = array_values(array_filter($versionIds, fn (int $id): bool => $versions->has($id)));

        $cells = $this->cells($versionIds);
        $tree = CatalogTree::load();

        $rows = [];
        $walk = function (int $moduleId, int $depth, ?string $parent) use (&$walk, &$rows, $tree, $cells): void {
            $key = 'module:'.$moduleId;

            if (! isset($cells[$key])) {
                return;
            }

            $module = $tree->modules[$moduleId];
            $rows[] = ['key' => $key, 'type' => 'module', 'id' => $moduleId, 'name' => $module['name'], 'code' => $module['code'], 'depth' => $depth, 'parent' => $parent];

            foreach ($tree->childModuleIds($moduleId) as $childId) {
                $walk($childId, $depth + 1, $key);
            }

            foreach ($tree->featureIds($moduleId) as $featureId) {
                $featureKey = 'feature:'.$featureId;

                if (isset($cells[$featureKey])) {
                    $feature = $tree->features[$featureId];
                    $rows[] = ['key' => $featureKey, 'type' => 'feature', 'id' => $featureId, 'name' => $feature['name'], 'code' => $feature['code'], 'depth' => $depth + 1, 'parent' => $key];
                }
            }
        };

        $roots = $rootModuleId !== null && isset($tree->modules[$rootModuleId]) ? [$rootModuleId] : $tree->rootModuleIds();
        foreach ($roots as $moduleId) {
            $walk($moduleId, 0, null);
        }

        return [
            'versions' => array_map(fn (int $id): array => [
                'id' => $id,
                'name' => $versions[$id]->name,
                'customer' => $versions[$id]->customer?->name,
                'reference' => $versions[$id]->product->reference_version_id === $id,
            ], $versionIds),
            'rows' => $rows,
            'cells' => $cells,
        ];
    }

    /**
     * @param  list<int>  $versionIds
     * @return array<string, array<int, array{id: int, s: string, d: bool, r: int, i: int}>>
     */
    private function cells(array $versionIds): array
    {
        if ($versionIds === []) {
            return [];
        }

        $rules = DB::table('business_rules')->select('implementation_id', DB::raw('count(*) as total'))
            ->whereNull('archived_at')->groupBy('implementation_id');
        $issues = DB::table('implementation_issue')->join('issues', 'issues.id', '=', 'implementation_issue.issue_id')
            ->select('implementation_issue.implementation_id', DB::raw('count(*) as total'))
            ->whereNull('issues.archived_at')->groupBy('implementation_issue.implementation_id');

        $rows = DB::table('implementations')
            ->leftJoinSub($rules, 'rules', 'rules.implementation_id', '=', 'implementations.id')
            ->leftJoinSub($issues, 'issues', 'issues.implementation_id', '=', 'implementations.id')
            ->whereIn('implementations.version_id', $versionIds)
            ->whereNull('implementations.archived_at')
            ->get([
                'implementations.id', 'implementations.version_id', 'implementations.module_id', 'implementations.feature_id',
                'implementations.status', DB::raw("(implementations.deviation IS NOT NULL AND implementations.deviation <> '') as has_deviation"),
                DB::raw('COALESCE(rules.total, 0) as rules_count'), DB::raw('COALESCE(issues.total, 0) as issues_count'),
            ]);

        $cells = [];
        foreach ($rows as $row) {
            $key = $row->module_id !== null ? 'module:'.$row->module_id : 'feature:'.$row->feature_id;
            $cells[$key][(int) $row->version_id] = [
                'id' => (int) $row->id,
                's' => (string) $row->status,
                'd' => (bool) $row->has_deviation,
                'r' => (int) $row->rules_count,
                'i' => (int) $row->issues_count,
            ];
        }

        return $cells;
    }
}
