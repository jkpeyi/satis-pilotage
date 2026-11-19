<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Removes a feature from one of its other modules (RG-16). Its main module and its
 * implementations are not affected.
 */
final class UnlinkFeatureFromModule
{
    public function handle(Feature $feature, Module $module): void
    {
        DB::table('module_feature')->where('feature_id', $feature->id)->where('module_id', $module->id)->delete();
    }
}
