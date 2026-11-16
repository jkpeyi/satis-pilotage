<?php

namespace App\Queries;

use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;

/**
 * Detailed comparison of one catalog item across versions (EF-306):
 * statuses and deviations side by side, business rules aligned by their origin (RG-11), issues.
 */
final class RowComparison
{
    /**
     * @param  list<int>  $versionIds
     * @return array{
     *     item: Module|Feature,
     *     versions: Collection<int, Version>,
     *     implementations: array<int, Implementation|null>,
     *     ruleFamilies: list<array{title: string, rules: array<int, BusinessRule|null>, divergent: bool}>
     * }|null
     */
    public function build(string $rowKey, array $versionIds): ?array
    {
        [$type, $id] = array_pad(explode(':', $rowKey, 2), 2, '0');
        $item = match ($type) {
            'module' => Module::query()->find((int) $id),
            'feature' => Feature::query()->find((int) $id),
            default => null,
        };

        if ($item === null) {
            return null;
        }

        $versions = Version::query()->with('customer')->findMany($versionIds)
            ->sortBy(fn (Version $version): int|false => array_search($version->id, $versionIds, true))
            ->values();

        $byVersion = Implementation::query()
            ->whereIn('version_id', $versionIds)
            ->whereNull('archived_at')
            ->where($item instanceof Module ? 'module_id' : 'feature_id', $item->id)
            ->with([
                'businessRules' => fn ($query) => $query->whereNull('archived_at')->orderBy('id'),
                'issues' => fn ($query) => $query->whereNull('issues.archived_at')->orderBy('issues.id'),
            ])
            ->get()
            ->keyBy('version_id');

        $implementations = [];
        foreach ($versions as $version) {
            $implementations[$version->id] = $byVersion->get($version->id);
        }

        return [
            'item' => $item,
            'versions' => $versions,
            'implementations' => $implementations,
            'ruleFamilies' => $this->ruleFamilies($implementations),
        ];
    }

    /**
     * One line per rule family (the origin rule and its copies), one cell per version.
     *
     * @param  array<int, Implementation|null>  $implementations
     * @return list<array{title: string, rules: array<int, BusinessRule|null>, divergent: bool}>
     */
    private function ruleFamilies(array $implementations): array
    {
        $families = [];

        foreach ($implementations as $versionId => $implementation) {
            foreach ($implementation->businessRules ?? [] as $rule) {
                $familyId = $rule->origin_rule_id ?? $rule->id;
                $families[$familyId] ??= ['title' => $rule->title, 'rules' => array_fill_keys(array_keys($implementations), null), 'divergent' => false];
                $families[$familyId]['rules'][$versionId] = $rule;
            }
        }

        foreach ($families as &$family) {
            $present = array_filter($family['rules']);
            $signatures = array_unique(array_map(fn (BusinessRule $rule): string => $rule->title."\n".$rule->description, $present));
            $family['divergent'] = count($signatures) > 1;
        }

        return array_values($families);
    }
}
