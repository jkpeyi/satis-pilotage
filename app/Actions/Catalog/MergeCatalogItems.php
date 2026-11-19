<?php

namespace App\Actions\Catalog;

use App\Actions\Implementations\ChangeImplementationStatus;
use App\Enums\ImplementationStatus;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\User;
use App\Queries\CatalogTree;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate catalog item into the one kept: everything the duplicate holds moves to the
 * kept item, then the empty duplicate is deleted and the merge is logged in catalog_merges.
 *
 * - Sub-modules and features of a duplicate module move under the kept module.
 * - An implementation moves to the kept item. When a version implements both, the two are merged:
 *   rules, issues and status history move to the remaining one (the active one, the kept item's
 *   when both are), an unknown status takes the duplicate's (with a history row, RG-06), and
 *   deviations and notes are put together.
 * - Workflow steps, product tags, the catalog code and the standard description follow.
 */
final class MergeCatalogItems
{
    private const SEPARATOR = "\n\n---\n\n";

    public function __construct(
        private readonly Positions $positions,
        private readonly ChangeImplementationStatus $changeStatus,
    ) {}

    /**
     * @return array{implementations_moved: int, implementations_merged: int, children_moved: int, rules_moved: int, issues_moved: int, steps_moved: int}
     */
    public function handle(Module|Feature $duplicate, Module|Feature $kept, ?User $user = null): array
    {
        if ($duplicate::class !== $kept::class) {
            throw new DomainException('Un module ne peut être fusionné qu’avec un module, une fonctionnalité qu’avec une fonctionnalité. Convertissez d’abord l’un des deux.');
        }

        if ($duplicate->is($kept)) {
            throw new DomainException('Choisissez deux éléments différents.');
        }

        if ($kept->isArchived()) {
            throw new DomainException('L’élément conservé doit être actif.');
        }

        $user ??= Auth::user();

        return DB::transaction(function () use ($duplicate, $kept, $user): array {
            $summary = ['implementations_moved' => 0, 'implementations_merged' => 0, 'children_moved' => 0, 'rules_moved' => 0, 'issues_moved' => 0, 'steps_moved' => 0];

            if ($duplicate instanceof Module && $kept instanceof Module) {
                $summary['children_moved'] = $this->moveChildren($duplicate, $kept);
            }

            $column = $duplicate instanceof Module ? 'module_id' : 'feature_id';
            $this->mergeImplementations($column, (int) $duplicate->getKey(), (int) $kept->getKey(), $user, $summary);

            if ($duplicate instanceof Feature) {
                $summary['steps_moved'] = DB::table('workflow_steps')->where('feature_id', $duplicate->id)->update(['feature_id' => $kept->id]);
            }

            $kept->products()->syncWithoutDetaching($duplicate->products()->pluck('products.id')->all());

            $code = $duplicate->code;
            $log = [
                'kind' => $duplicate instanceof Module ? 'module' : 'feature',
                'kept_id' => $kept->getKey(),
                'merged_id' => $duplicate->getKey(),
                'merged_name' => $duplicate->name,
                'merged_code' => $code,
                'details' => json_encode($summary, JSON_THROW_ON_ERROR),
                'created_by' => $user?->id,
            ];

            $this->positions->compactSiblingsOf($duplicate);
            $duplicate->products()->detach();
            $duplicate->delete();

            $kept->refresh();
            $kept->fill([
                'code' => $kept->code ?? $code,
                'standard_description' => filled($kept->standard_description) ? $kept->standard_description : $duplicate->standard_description,
            ])->save();

            DB::table('catalog_merges')->insert($log);

            return $summary;
        });
    }

    /**
     * When the kept module sits inside the duplicate, it first takes the duplicate's place.
     */
    private function moveChildren(Module $duplicate, Module $kept): int
    {
        $tree = CatalogTree::load(withArchived: true);

        if (in_array($duplicate->id, $tree->ancestorModuleIds($kept->id), true)) {
            $kept->parent_id = $duplicate->parent_id;
            $kept->save();
            $this->positions->insert($kept, null);
        }

        $moved = 0;
        $nextModule = (int) Module::query()->where('parent_id', $kept->id)->max('position');
        foreach (Module::query()->where('parent_id', $duplicate->id)->whereKeyNot($kept->id)->orderBy('position')->get() as $child) {
            $child->forceFill(['parent_id' => $kept->id, 'position' => ++$nextModule])->save();
            $moved++;
        }

        $nextFeature = (int) Feature::query()->where('module_id', $kept->id)->max('position');
        foreach (Feature::query()->where('module_id', $duplicate->id)->orderBy('position')->get() as $feature) {
            $feature->forceFill(['module_id' => $kept->id, 'position' => ++$nextFeature])->save();
            $moved++;
        }

        return $moved;
    }

    /**
     * @param  'module_id'|'feature_id'  $column
     * @param  array<string, int>  $summary
     */
    private function mergeImplementations(string $column, int $duplicateId, int $keptId, ?User $user, array &$summary): void
    {
        $duplicates = Implementation::query()->where($column, $duplicateId)->get();
        $keptByVersion = Implementation::query()->where($column, $keptId)
            ->whereIn('version_id', $duplicates->pluck('version_id'))->get()->keyBy('version_id');

        foreach ($duplicates as $implementation) {
            /** @var Implementation|null $other */
            $other = $keptByVersion->get($implementation->version_id);

            if ($other === null) {
                $implementation->forceFill([$column => $keptId])->save();
                $summary['implementations_moved']++;

                continue;
            }

            // Both exist in this version: keep the active one, the kept item's when both are.
            [$survivor, $loser] = $implementation->isArchived() || ! $other->isArchived()
                ? [$other, $implementation]
                : [$implementation, $other];

            $summary['rules_moved'] += DB::table('business_rules')->where('implementation_id', $loser->id)->update(['implementation_id' => $survivor->id]);

            $issueIds = DB::table('implementation_issue')->where('implementation_id', $loser->id)->pluck('issue_id');
            foreach ($issueIds as $issueId) {
                $summary['issues_moved'] += DB::table('implementation_issue')->insertOrIgnore(['implementation_id' => $survivor->id, 'issue_id' => $issueId]);
            }
            DB::table('implementation_issue')->where('implementation_id', $loser->id)->delete();

            DB::table('implementation_status_changes')->where('implementation_id', $loser->id)->update(['implementation_id' => $survivor->id]);

            $survivor->forceFill([
                'deviation' => $this->combine($survivor->deviation, $loser->deviation),
                'assessment_note' => $this->combine($survivor->assessment_note, $loser->assessment_note),
            ])->save();

            $loserStatus = $loser->status;
            $loser->delete();

            if ($survivor->status === ImplementationStatus::Unknown && $loserStatus !== ImplementationStatus::Unknown) {
                $this->changeStatus->handle([$survivor->id], $loserStatus, $user, 'Reprise de l’état du doublon fusionné.');
            }

            if ($survivor->getAttribute($column) !== $keptId) {
                $survivor->refresh()->forceFill([$column => $keptId])->save();
            }

            $summary['implementations_merged']++;
        }
    }

    private function combine(?string $first, ?string $second): ?string
    {
        $parts = array_values(array_unique(array_filter([trim((string) $first), trim((string) $second)], fn (string $part): bool => $part !== '')));

        return $parts === [] ? null : implode(self::SEPARATOR, $parts);
    }
}
