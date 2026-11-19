<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns a feature into a sub-module of its module (RG-14, EF-213).
 *
 * The implementations are re-pointed to the new module: their ids do not change,
 * so business rules, issues and status history follow without migration.
 */
final class ConvertFeatureToSubmodule
{
    public function __construct(
        private readonly CreateModule $createModule,
        private readonly Positions $positions,
    ) {}

    public function handle(Feature $feature): Module
    {
        if ($feature->otherModules()->exists()) {
            throw new DomainException('Cette fonctionnalité apparaît dans plusieurs modules : retirez-la des autres modules avant de la convertir en sous-module.');
        }

        if ($feature->code !== null && Module::query()->where('code', $feature->code)->exists()) {
            throw new DomainException("Le code {$feature->code} est déjà utilisé par un module.");
        }

        return DB::transaction(function () use ($feature): Module {
            $code = $feature->code;
            $feature->forceFill(['code' => null, 'archived_at' => now()])->save();
            $this->positions->compactFeatures($feature->module_id);

            $module = $this->createModule->handle([
                'name' => $feature->name,
                'parent_id' => $feature->module_id,
                'code' => $code,
                'standard_description' => $feature->standard_description,
            ]);
            $module->products()->sync($feature->products()->pluck('products.id')->all());

            Implementation::query()->where('feature_id', $feature->id)->update([
                'module_id' => $module->id,
                'feature_id' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            return $module;
        });
    }
}
