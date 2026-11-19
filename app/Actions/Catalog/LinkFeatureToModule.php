<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Makes an existing feature also appear under another module, instead of creating a duplicate
 * (RG-16). The feature stays one record: same name, same implementations in every version.
 */
final class LinkFeatureToModule
{
    public function handle(Feature $feature, Module $module): void
    {
        if ($feature->isArchived() || $module->isArchived()) {
            throw new DomainException('La fonctionnalité et le module doivent être actifs.');
        }

        if ($feature->module_id === $module->id) {
            throw new DomainException('C’est déjà le module principal de cette fonctionnalité.');
        }

        if ($feature->otherModules()->whereKey($module->id)->exists()) {
            throw new DomainException('La fonctionnalité apparaît déjà dans ce module.');
        }

        DB::table('module_feature')->insert([
            'module_id' => $module->id,
            'feature_id' => $feature->id,
            'position' => (int) DB::table('module_feature')->where('module_id', $module->id)->max('position') + 1,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }
}
