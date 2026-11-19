<?php

use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * The command cleans a catalog that predates the uniqueness rule. To reproduce that state, the
 * unique index migration is rolled back, which a transaction-based test cannot do: this test
 * rebuilds the database before and after.
 */
beforeEach(function () {
    Artisan::call('migrate:fresh');
    Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_23_000200_add_unique_active_names_to_catalog_tables.php']);
});

afterEach(function () {
    Artisan::call('migrate:fresh');
    RefreshDatabaseState::$migrated = false;
});

it('previews, then merges duplicates so that the uniqueness migration can run', function () {
    $credit = Module::query()->create(['name' => 'Crédit', 'position' => 1]);
    $creditCopy = Module::query()->create(['name' => 'credit', 'position' => 2]);
    $kept = Feature::query()->create(['name' => 'Simulation', 'module_id' => $credit->id, 'position' => 1]);
    $copy = Feature::query()->create(['name' => 'SIMULATION', 'module_id' => $creditCopy->id, 'position' => 1]);
    Module::query()->create(['name' => 'Rachat', 'position' => 3]);
    Feature::query()->create(['name' => 'Rachat', 'module_id' => $credit->id, 'position' => 2]);
    $version = Version::factory()->create();
    Implementation::query()->create(['version_id' => $version->id, 'feature_id' => $kept->id]);
    Implementation::query()->create(['version_id' => Version::factory()->create()->id, 'feature_id' => $copy->id]);

    expect(fn () => Artisan::call('migrate'))->toThrow(RuntimeException::class, 'catalog:merge-duplicates');

    $this->artisan('catalog:merge-duplicates')
        ->expectsOutputToContain('Module « Crédit » : on garde')
        ->expectsOutputToContain('fusionne et supprime')
        ->expectsOutputToContain('Réunir « Rachat » : le sous-module')
        ->expectsOutputToContain('Aperçu seulement')
        ->assertSuccessful();
    expect(Module::query()->count())->toBe(3);

    // "Rachat" is a root module: it cannot become a feature, the command says so and goes on.
    $this->artisan('catalog:merge-duplicates', ['--force' => true])
        ->expectsOutputToContain('impossible : Un module racine')
        ->assertSuccessful();

    expect(Module::query()->where('name_key', 'credit')->count())->toBe(1)
        ->and(Feature::query()->where('name_key', 'simulation')->count())->toBe(1)
        ->and(Implementation::query()->whereNotNull('feature_id')->pluck('feature_id')->unique()->count())->toBe(1)
        ->and(DB::table('catalog_merges')->count())->toBe(2)
        ->and(Artisan::call('migrate'))->toBe(0);
});
