<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns an empty sub-module into a feature of its parent module (RG-14, EF-213).
 */
final class ConvertSubmoduleToFeature
{
    public function __construct(
        private readonly CreateFeature $createFeature,
        private readonly Positions $positions,
    ) {}

    public function handle(Module $module): Feature
    {
        if ($module->parent_id === null) {
            throw new DomainException('Seul un sous-module peut devenir une fonctionnalité : une fonctionnalité doit appartenir à un module.');
        }

        if ($module->children()->whereNull('archived_at')->exists() || $module->features()->whereNull('archived_at')->exists()) {
            throw new DomainException('Ce sous-module contient encore des éléments. Déplacez-les ou archivez-les avant la conversion.');
        }

        if ($module->code !== null && Feature::query()->where('code', $module->code)->exists()) {
            throw new DomainException("Le code {$module->code} est déjà utilisé par une fonctionnalité.");
        }

        return DB::transaction(function () use ($module): Feature {
            $code = $module->code;
            $parentId = $module->parent_id;
            $module->forceFill(['code' => null, 'archived_at' => now()])->save();
            $this->positions->compactModules($parentId);

            $feature = $this->createFeature->handle([
                'name' => $module->name,
                'module_id' => $parentId,
                'code' => $code,
                'standard_description' => $module->standard_description,
            ]);
            $feature->products()->sync($module->products()->pluck('products.id')->all());

            Implementation::query()->where('module_id', $module->id)->update([
                'feature_id' => $feature->id,
                'module_id' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            return $feature;
        });
    }
}
