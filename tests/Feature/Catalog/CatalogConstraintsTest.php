<?php

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Database\QueryException;

it('RG-01: rejects a feature without a module', function () {
    Feature::query()->create(['name' => 'Orphan feature']);
})->throws(QueryException::class);

it('RG-01: shows a module with a parent as a sub-module', function () {
    $module = Module::factory()->create();
    $submodule = Module::factory()->childOf($module)->create();
    $subSubmodule = Module::factory()->childOf($submodule)->create();

    expect($module->isSubmodule())->toBeFalse()
        ->and($module->typeLabel())->toBe('Module')
        ->and($submodule->typeLabel())->toBe('Sous-module')
        ->and($subSubmodule->parent->parent->is($module))->toBeTrue();
});

it('RG-01: prevents deleting a module that still has features', function () {
    $feature = Feature::factory()->create();

    $feature->module->delete();
})->throws(QueryException::class);

it('RG-01: prevents deleting a module that still has sub-modules', function () {
    $module = Module::factory()->create();
    Module::factory()->childOf($module)->create();

    $module->delete();
})->throws(QueryException::class);
