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

            $this->positions->insert($feature, $position);

            if ($oldModuleId !== $module->id) {
                $this->positions->compactFeatures($oldModuleId);
            }
        });
    }
}
