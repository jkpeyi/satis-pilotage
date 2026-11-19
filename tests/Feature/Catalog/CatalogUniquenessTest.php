<?php

use App\Actions\Catalog\ArchiveCatalogItem;
use App\Actions\Catalog\ConvertFeatureToSubmodule;
use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Implementations\AttachCatalogItems;
use App\Exceptions\DuplicateCatalogItem;
use App\Livewire\Catalog\Index as CatalogPage;
use App\Livewire\Versions\Scope;
use App\Models\Feature;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use App\Queries\CatalogSearch;
use App\Support\CatalogName;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->credit = app(CreateModule::class)->handle(['name' => 'Crédit']);
    $this->granting = app(CreateModule::class)->handle(['name' => 'Octroi', 'parent_id' => $this->credit->id]);
    $this->simulation = app(CreateFeature::class)->handle(['name' => 'Simulation', 'module_id' => $this->granting->id]);
    $this->savings = app(CreateModule::class)->handle(['name' => 'Épargne']);
});

it('compares names without case, accents, punctuation or extra spaces', function () {
    expect(CatalogName::key('  Crédit  à la CONSOMMATION ! '))->toBe('credit a la consommation')
        ->and(CatalogName::key('Pièces-justificatives'))->toBe(CatalogName::key('pieces justificatives'))
        ->and($this->credit->name_key)->toBe('credit');
});

it('RG-15: refuses a name already used anywhere in the catalog, modules and features together', function (string $kind, string $name) {
    $create = fn () => $kind === 'module'
        ? app(CreateModule::class)->handle(['name' => $name, 'parent_id' => $this->savings->id])
        : app(CreateFeature::class)->handle(['name' => $name, 'module_id' => $this->savings->id]);

    expect($create)->toThrow(DuplicateCatalogItem::class);
})->with([
    'same feature elsewhere' => ['feature', 'simulation'],
    'module named like a feature' => ['module', 'SIMULATION'],
    'feature named like a module' => ['feature', 'Credit'],
]);

it('names the existing item and its place in the message', function () {
    try {
        app(CreateFeature::class)->handle(['name' => 'Simulation', 'module_id' => $this->savings->id]);
    } catch (DuplicateCatalogItem $exception) {
        expect($exception->existing->is($this->simulation))->toBeTrue()
            ->and($exception->getMessage())->toContain('fonctionnalité : Crédit › Octroi › Simulation');

        return;
    }

    $this->fail('No exception');
});

it('refuses a rename to a used name, but accepts changing the case of its own name', function () {
    $save = fn (Module|Feature $item, string $name) => app(SaveCatalogItem::class)->handle($item, ['name' => $name, 'code' => null, 'standard_description' => null, 'product_ids' => []]);

    expect(fn () => $save($this->savings, 'Octroi'))->toThrow(DuplicateCatalogItem::class);

    $save($this->simulation, 'SIMULATION');
    expect($this->simulation->refresh()->name)->toBe('SIMULATION');
});

it('lets an archived item keep its name, but refuses to restore it over an active one', function () {
    app(ArchiveCatalogItem::class)->handle($this->savings);
    $new = app(CreateModule::class)->handle(['name' => 'Epargne']);

    expect($new->exists)->toBeTrue()
        ->and(fn () => app(ArchiveCatalogItem::class)->handle($this->savings->refresh(), archive: false))
        ->toThrow(DuplicateCatalogItem::class);
});

it('RG-14: still converts a feature to a sub-module under the same name', function () {
    $module = app(ConvertFeatureToSubmodule::class)->handle($this->simulation);

    expect($module->name)->toBe('Simulation')->and($this->simulation->refresh()->isArchived())->toBeTrue();
});

it('enforces unique active names in the database too', function () {
    expect(fn () => Feature::query()->create(['name' => 'simulation', 'module_id' => $this->savings->id, 'position' => 0]))
        ->toThrow(QueryException::class);
});

it('suggests matching items, the same name first, with their path', function () {
    app(CreateFeature::class)->handle(['name' => 'Simulation de rachat', 'module_id' => $this->savings->id]);

    $results = app(CatalogSearch::class)->search('simul');

    expect(array_column($results, 'name'))->toBe(['Simulation', 'Simulation de rachat'])
        ->and($results[0]['path'])->toBe('Crédit › Octroi › Simulation')
        ->and(app(CatalogSearch::class)->search('Simulation')[0]['exact'])->toBeTrue()
        ->and(app(CatalogSearch::class)->search('s'))->toBe([]);
});

it('offers existing items in the catalog quick entry and moves one here instead of duplicating it', function () {
    Livewire::test(CatalogPage::class)
        ->call('startDraft', $this->savings->id, 'feature')
        ->set('draftName', 'simulation')
        ->assertSee('Déjà dans le catalogue')
        ->assertSee('Crédit › Octroi › Simulation')
        ->call('saveDraft')
        ->assertHasErrors(['draftName'])
        ->call('moveExistingHere', 'feature:'.$this->simulation->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($this->simulation->refresh()->module_id)->toBe($this->savings->id)
        ->and(Feature::query()->where('name_key', 'simulation')->count())->toBe(1);
});

it('reports a duplicate name when renaming from the catalog page', function () {
    Livewire::test(CatalogPage::class)
        ->call('select', 'module:'.$this->savings->id)
        ->set('form.name', 'Crédit')
        ->call('save')
        ->assertHasErrors(['form.name']);
});

it('attaches the existing item from the version scope instead of creating a duplicate', function () {
    $version = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$version->id], [$this->savings->id]);

    Livewire::test(Scope::class, ['version' => $version])
        ->set('showCreate', true)
        ->set('newKind', 'feature')
        ->set('newParentId', (string) $this->savings->id)
        ->set('newName', 'Simulation')
        ->assertSee('Rattacher')
        ->call('createItem')
        ->assertHasErrors(['newName'])
        ->call('attachExisting', 'feature:'.$this->simulation->id)
        ->assertDispatched('notify')
        ->set('newName', 'Simulation')
        ->assertSee('Déjà rattaché');

    expect($version->implementations()->pluck('feature_id')->filter()->values()->all())->toBe([$this->simulation->id])
        ->and($version->implementations()->whereIn('module_id', [$this->credit->id, $this->granting->id])->count())->toBe(2);
});
