<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A sub-module and a feature describe the same thing (same name, RG-15): the sub-module becomes
 * that feature, shown under the sub-module's parent too (RG-16). Its implementations, with their
 * rules, issues and history, move to the feature (merged when a version has both, RG-14); the
 * empty sub-module is then deleted and the operation logged in catalog_merges.
 */
final class ReuniteSubmoduleWithFeature
{
    public function __construct(
        private readonly MergeCatalogItems $merge,
        private readonly Positions $positions,
    ) {}

    /**
     * @return array{implementations_moved: int, implementations_merged: int, rules_moved: int, issues_moved: int}
     */
    public function handle(Module $submodule, Feature $feature, ?User $user = null): array
    {
        if ($submodule->parent_id === null) {
            throw new DomainException('Un module racine ne peut pas devenir une fonctionnalité : déplacez-le d’abord sous un autre module.');
        }

        if ($feature->isArchived() || $submodule->isArchived()) {
            throw new DomainException('Les deux éléments doivent être actifs.');
        }

        $hasChildren = Module::query()->where('parent_id', $submodule->id)->exists()
            || Feature::query()->where('module_id', $submodule->id)->exists()
            || DB::table('module_feature')->where('module_id', $submodule->id)->exists();
        if ($hasChildren) {
            throw new DomainException('Ce sous-module contient des éléments : déplacez-les ou fusionnez-les avant de le réunir avec la fonctionnalité.');
        }

        $user ??= Auth::user();

        return DB::transaction(function () use ($submodule, $feature, $user): array {
            $summary = ['implementations_moved' => 0, 'implementations_merged' => 0, 'children_moved' => 0, 'rules_moved' => 0, 'issues_moved' => 0, 'steps_moved' => 0];
            $this->merge->mergeImplementations('module_id', $submodule->id, 'feature_id', $feature->id, $user, $summary);

            $parentId = (int) $submodule->parent_id;
            $alreadyThere = $feature->module_id === $parentId
                || DB::table('module_feature')->where('feature_id', $feature->id)->where('module_id', $parentId)->exists();
            if (! $alreadyThere) {
                $this->merge->linkFeature($feature->id, $parentId);
            }

            $feature->products()->syncWithoutDetaching($submodule->products()->pluck('products.id')->all());

            $log = [
                'kind' => 'module',
                'kept_id' => $feature->id,
                'merged_id' => $submodule->id,
                'merged_name' => $submodule->name,
                'merged_code' => $submodule->code,
                'details' => json_encode([...$summary, 'reunited_into' => 'feature'], JSON_THROW_ON_ERROR),
                'created_by' => $user?->id,
            ];

            $this->positions->compactSiblingsOf($submodule);
            $submodule->products()->detach();
            $submodule->delete();

            $feature->refresh()->fill([
                'code' => $feature->code ?? $log['merged_code'],
                'standard_description' => filled($feature->standard_description) ? $feature->standard_description : $submodule->standard_description,
            ])->save();

            DB::table('catalog_merges')->insert($log);

            return [
                'implementations_moved' => $summary['implementations_moved'],
                'implementations_merged' => $summary['implementations_merged'],
                'rules_moved' => $summary['rules_moved'],
                'issues_moved' => $summary['issues_moved'],
            ];
        });
    }
}
