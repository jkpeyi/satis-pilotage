<?php

use App\Enums\ImplementationStatus;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Version;
use Illuminate\Database\QueryException;

it('RG-03: rejects an implementation with both a module and a feature', function () {
    $feature = Feature::factory()->create();

    Implementation::factory()->create([
        'module_id' => $feature->module_id,
        'feature_id' => $feature->id,
    ]);
})->throws(QueryException::class);

it('RG-03: rejects an implementation with neither a module nor a feature', function () {
    Implementation::factory()->create(['module_id' => null, 'feature_id' => null]);
})->throws(QueryException::class);

it('RG-03: rejects attaching the same module twice to a version', function () {
    $implementation = Implementation::factory()->create();

    Implementation::factory()->create([
        'version_id' => $implementation->version_id,
        'module_id' => $implementation->module_id,
    ]);
})->throws(QueryException::class);

it('RG-03: rejects attaching the same feature twice to a version', function () {
    $implementation = Implementation::factory()->forFeature()->create();

    Implementation::factory()->forFeature($implementation->feature)->create([
        'version_id' => $implementation->version_id,
    ]);
})->throws(QueryException::class);

it('RG-03: allows the same module in several versions', function () {
    $module = Module::factory()->create();
    [$first, $second] = Version::factory()->count(2)->create();

    Implementation::factory()->create(['version_id' => $first->id, 'module_id' => $module->id]);
    Implementation::factory()->create(['version_id' => $second->id, 'module_id' => $module->id]);

    expect($module->implementations()->count())->toBe(2);
});

it('RG-03: resolves the targeted catalog item', function () {
    $onModule = Implementation::factory()->create();
    $onFeature = Implementation::factory()->forFeature()->create();

    expect($onModule->catalogItem())->toBeInstanceOf(Module::class)
        ->and($onFeature->catalogItem())->toBeInstanceOf(Feature::class);
});

it('RG-04: starts a new implementation with an unknown presence', function () {
    $implementation = Implementation::query()->create([
        'version_id' => Version::factory()->create()->id,
        'module_id' => Module::factory()->create()->id,
    ]);

    expect($implementation->refresh()->status)->toBe(ImplementationStatus::Unknown);
});
