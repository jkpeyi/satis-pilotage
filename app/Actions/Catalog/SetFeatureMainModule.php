<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Makes one of the feature's other modules its main module; the former main module becomes one of
 * its other modules (RG-16). The main module gives the feature's path in the catalog and reports,
 * and is the branch attached with it in a version (RG-04).
 */
final class SetFeatureMainModule
{
    public function __construct(private readonly Positions $positions) {}

    public function handle(Feature $feature, Module $module): void
    {
        if (! $feature->otherModules()->whereKey($module->id)->exists()) {
            throw new DomainException('Choisissez l’un des autres modules de la fonctionnalité.');
        }

        DB::transaction(function () use ($feature, $module): void {
            $formerMainId = $feature->module_id;

            DB::table('module_feature')->where('feature_id', $feature->id)->where('module_id', $module->id)->delete();
            DB::table('module_feature')->insert([
                'module_id' => $formerMainId,
                'feature_id' => $feature->id,
                'position' => (int) DB::table('module_feature')->where('module_id', $formerMainId)->max('position') + 1,
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);

            $feature->module()->associate($module);
            $feature->save();
            $this->positions->insert($feature, null);
            $this->positions->compactFeatures($formerMainId);
        });
    }
}
