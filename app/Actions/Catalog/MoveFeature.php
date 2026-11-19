<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

final class MoveFeature
{
    public function __construct(private readonly Positions $positions) {}

    public function handle(Feature $feature, Module $module, int $position): void
    {
        DB::transaction(function () use ($feature, $module, $position): void {
            $oldModuleId = $feature->module_id;

            $feature->module()->associate($module);
            $feature->save();
            // Its new main module can no longer be one of its other modules (RG-16).
            DB::table('module_feature')->where('feature_id', $feature->id)->where('module_id', $module->id)->delete();

            $this->positions->insert($feature, $position);

            if ($oldModuleId !== $module->id) {
                $this->positions->compactFeatures($oldModuleId);
            }
        });
    }
}
