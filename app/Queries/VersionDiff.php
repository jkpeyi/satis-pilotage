<?php

namespace App\Queries;

use App\Enums\FeaturePresence;
use App\Models\BusinessRule;
use App\Models\Implementation;
use App\Models\Version;
use Illuminate\Support\Collection;

/**
 * Functional differences between two versions: what A has that B lacks, the reverse,
 * and what both have but differently.
 *
 * An item is "present" in a version when it is attached and not "missing": "missing" means
 * expected but absent (RG-05), so functionally the version does not have it.
 * Items present on one side only are grouped at the highest level: a module that only A has
 * is one entry, with the number of sub-items it brings.
 */
final class VersionDiff
{
    /**
     * @return array{
     *     onlyInA: list<array<string, mixed>>,
     *     onlyInB: list<array<string, mixed>>,
     *     different: list<array<string, mixed>>,
     *     counts: array{onlyInA: int, onlyInB: int, different: int, same: int}
     * }
     */
    public function build(Version $a, Version $b): array
    {
        $tree = CatalogTree::load();
        $sideA = $this->implementations($a);
        $sideB = $this->implementations($b);

        $onlyInA = [];
        $onlyInB = [];
        $different = [];
        $counts = ['onlyInA' => 0, 'onlyInB' => 0, 'different' => 0, 'same' => 0];

        foreach ($this->itemsInTreeOrder($tree) as $item) {
            $key = $item['key'];
            $inA = $this->isPresent($sideA->get($key));
            $inB = $this->isPresent($sideB->get($key));

            if ($inA && $inB) {
                $reasons = $this->differences($sideA[$key], $sideB[$key]);

                if ($reasons === []) {
                    $counts['same']++;

                    continue;
                }

                $counts['different']++;
                $different[] = [...$this->describe($tree, $item), 'a' => $sideA[$key], 'b' => $sideB[$key], 'reasons' => $reasons];
            } elseif ($inA || $inB) {
                $side = $inA ? 'onlyInA' : 'onlyInB';
                $counts[$side]++;

                // Folded into its parent module when that module is on the same side.
                $parentKey = $item['parent'];
                $parentOnSameSide = $parentKey !== null
                    && $this->isPresent(($inA ? $sideA : $sideB)->get($parentKey))
                    && ! $this->isPresent(($inA ? $sideB : $sideA)->get($parentKey));

                if ($parentOnSameSide) {
                    continue;
                }

                $entry = [
                    ...$this->describe($tree, $item),
                    'implementation' => $inA ? $sideA[$key] : $sideB[$key],
                    'other' => ($inA ? $sideB : $sideA)->get($key),
                    'children' => $this->foldedChildren($tree, $item, $inA ? $sideA : $sideB, $inA ? $sideB : $sideA),
                ];

                $side === 'onlyInA' ? $onlyInA[] = $entry : $onlyInB[] = $entry;
            }
        }

        return compact('onlyInA', 'onlyInB', 'different', 'counts');
    }

    /**
     * Active implementations of a version, keyed "module:12" / "feature:34", with their active rules.
     *
     * @return Collection<string, Implementation>
     */
    private function implementations(Version $version): Collection
    {
        return $version->implementations()
            ->whereNull('archived_at')
            ->with(['businessRules' => fn ($query) => $query->whereNull('archived_at')])
            ->get()
            ->keyBy(fn (Implementation $implementation): string => $implementation->module_id !== null
                ? 'module:'.$implementation->module_id
                : 'feature:'.$implementation->feature_id)
            ->toBase();
    }

    private function isPresent(?Implementation $implementation): bool
    {
        return $implementation !== null
            && ! in_array($implementation->status->presence(), [FeaturePresence::Absent, FeaturePresence::NotApplicable], true);
    }

    /**
     * @return list<string> French labels of what differs, empty when equivalent.
     */
    private function differences(Implementation $a, Implementation $b): array
    {
        $reasons = [];

        if ($a->status !== $b->status) {
            $reasons[] = 'État';
        }

        if (trim((string) $a->deviation) !== trim((string) $b->deviation)) {
            $reasons[] = 'Description fonctionnelle';
        }

        if ($this->ruleSignature($a) !== $this->ruleSignature($b)) {
            $reasons[] = 'Règles métier';
        }

        return $reasons;
    }

    /**
     * Rule families (origin and copies, RG-11) with their text: equal signatures mean same rules.
     *
     * @return array<int, string>
     */
    private function ruleSignature(Implementation $implementation): array
    {
        $signature = [];

        foreach ($implementation->businessRules as $rule) {
            /** @var BusinessRule $rule */
            $signature[$rule->origin_rule_id ?? $rule->id] = md5(trim($rule->title)."\n".trim($rule->description));
        }

        ksort($signature);

        return $signature;
    }

    /**
     * @return list<array{key: string, type: string, id: int, name: string, parent: ?string}>
     */
    private function itemsInTreeOrder(CatalogTree $tree): array
    {
        $items = [];
        $walk = function (int $moduleId, ?string $parent) use (&$walk, &$items, $tree): void {
            $key = 'module:'.$moduleId;
            $items[] = ['key' => $key, 'type' => 'module', 'id' => $moduleId, 'name' => $tree->modules[$moduleId]['name'], 'parent' => $parent];

            foreach ($tree->childModuleIds($moduleId) as $childId) {
                $walk($childId, $key);
            }

            foreach ($tree->featureIds($moduleId) as $featureId) {
                $items[] = ['key' => 'feature:'.$featureId, 'type' => 'feature', 'id' => $featureId, 'name' => $tree->features[$featureId]['name'], 'parent' => $key];
            }
        };

        foreach ($tree->rootModuleIds() as $moduleId) {
            $walk($moduleId, null);
        }

        return $items;
    }

    /**
     * @param  array{key: string, type: string, id: int, name: string, parent: ?string}  $item
     * @return array{key: string, type: string, name: string, typeLabel: string, path: string}
     */
    private function describe(CatalogTree $tree, array $item): array
    {
        $ancestors = $item['type'] === 'module' ? $tree->ancestorModuleIds($item['id']) : $tree->featureAncestorModuleIds($item['id']);

        return [
            'key' => $item['key'],
            'type' => $item['type'],
            'name' => $item['name'],
            'typeLabel' => $item['type'] === 'feature' ? 'Fonctionnalité' : ($item['parent'] === null ? 'Module' : 'Sous-module'),
            'path' => implode(' › ', array_map(fn (int $id): string => $tree->modules[$id]['name'], array_reverse($ancestors))),
        ];
    }

    /**
     * Names of the items a module brings on its side only (its present sub-items absent from the other side).
     *
     * @param  array{key: string, type: string, id: int, name: string, parent: ?string}  $item
     * @param  Collection<string, Implementation>  $own
     * @param  Collection<string, Implementation>  $other
     * @return list<string>
     */
    private function foldedChildren(CatalogTree $tree, array $item, Collection $own, Collection $other): array
    {
        if ($item['type'] !== 'module') {
            return [];
        }

        $names = [];
        foreach ($tree->descendantModuleIds($item['id']) as $moduleId) {
            if ($this->isPresent($own->get('module:'.$moduleId)) && ! $this->isPresent($other->get('module:'.$moduleId))) {
                $names[] = $tree->modules[$moduleId]['name'];
            }
        }
        foreach ($tree->featureIdsInSubtree($item['id']) as $featureId) {
            if ($this->isPresent($own->get('feature:'.$featureId)) && ! $this->isPresent($other->get('feature:'.$featureId))) {
                $names[] = $tree->features[$featureId]['name'];
            }
        }

        return $names;
    }
}
