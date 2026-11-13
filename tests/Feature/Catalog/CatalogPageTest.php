<?php

use App\Livewire\Catalog\Index;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the catalog page', function () {
    Module::factory()->create(['name' => 'Crédit']);

    $this->get(route('catalog.index'))->assertOk()->assertSee('Crédit');
});

it('EF-202: creates items with the keyboard: Enter, Tab and kind switch', function () {
    $component = Livewire::test(Index::class)
        ->call('startDraft', null)
        ->set('draftName', 'Crédit')->call('saveDraft')
        ->call('indentDraft')
        ->set('draftName', 'Octroi')->call('saveDraft')
        ->call('indentDraft')
        ->call('setDraftKind', 'feature')
        ->set('draftName', 'Simulation')->call('saveDraft')
        ->set('draftName', 'Scoring')->call('saveDraft')
        ->call('outdentDraft')
        ->call('outdentDraft')
        ->set('draftName', 'Épargne')->call('saveDraft')
        ->assertHasNoErrors();

    $credit = Module::query()->where('name', 'Crédit')->sole();
    $octroi = Module::query()->where('name', 'Octroi')->sole();

    expect($octroi->parent_id)->toBe($credit->id)
        ->and($octroi->features()->pluck('name')->all())->toBe(['Simulation', 'Scoring'])
        ->and(Module::query()->where('name', 'Épargne')->sole()->parent_id)->toBeNull()
        ->and($component->get('draft'))->toBe(['parent_id' => null, 'kind' => 'module']);
});

it('EF-202: keeps root items as modules', function () {
    Livewire::test(Index::class)
        ->call('startDraft', null, 'feature')
        ->call('setDraftKind', 'feature')
        ->assertSet('draft.kind', 'module');
});

it('EF-202: requires a name', function () {
    Livewire::test(Index::class)
        ->call('startDraft', null)
        ->call('saveDraft')
        ->assertHasErrors('draftName');
});

it('EF-201: moves a module by drag and drop and refuses a cycle', function () {
    $a = Module::factory()->create(['name' => 'A', 'position' => 0]);
    $b = Module::factory()->create(['name' => 'B', 'position' => 1]);

    Livewire::test(Index::class)->call('move', 'modules', $b->id, $a->id, 0);
    expect($b->refresh()->parent_id)->toBe($a->id);

    Livewire::test(Index::class)->call('move', 'modules', $a->id, $b->id, 0)->assertDispatched('notify');
    expect($a->refresh()->parent_id)->toBeNull();
});

it('EF-211: shows the item sheet and saves it with its products', function () {
    $feature = Feature::factory()->create(['name' => 'Simulation']);
    $product = Product::factory()->create();
    Implementation::factory()->forFeature($feature)->create();

    Livewire::test(Index::class)
        ->call('select', 'feature:'.$feature->id)
        ->assertSee('Versions qui l')
        ->set('form.code', 'crd-sim')
        ->set('form.standard_description', 'Calcule les échéances.')
        ->set('form.product_ids', [(string) $product->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($feature->refresh()->code)->toBe('CRD-SIM')
        ->and($feature->products()->pluck('products.id')->all())->toBe([$product->id]);
});

it('rejects a code already used by another feature', function () {
    Feature::factory()->create(['code' => 'CRD-SIM']);
    $feature = Feature::factory()->create();

    Livewire::test(Index::class)
        ->call('select', 'feature:'.$feature->id)
        ->set('form.code', 'crd-sim')
        ->call('save')
        ->assertHasErrors(['form.code' => 'unique']);
});

it('EF-213: converts the selected feature and selects the new sub-module', function () {
    $feature = Feature::factory()->create();

    $component = Livewire::test(Index::class)
        ->call('select', 'feature:'.$feature->id)
        ->call('convert');

    $submodule = Module::query()->where('parent_id', $feature->module_id)->sole();
    $component->assertSet('selected', 'module:'.$submodule->id);
});

it('EF-204: attaches the selected module to several versions', function () {
    $module = Module::factory()->create();
    Feature::factory()->for($module)->create();
    $versions = Version::factory()->count(2)->create();

    Livewire::test(Index::class)
        ->call('select', 'module:'.$module->id)
        ->set('attachVersionIds', array_map('strval', $versions->modelKeys()))
        ->call('attachToVersions')
        ->assertHasNoErrors();

    expect(Implementation::query()->count())->toBe(4);
});

it('RG-12: asks for confirmation before detaching an item that has business rules', function () {
    $implementation = Implementation::factory()->create();
    $implementation->businessRules()->create(['title' => 'Règle', 'description' => '…', 'source' => 'code']);

    $component = Livewire::test(Index::class)
        ->call('select', 'module:'.$implementation->module_id)
        ->call('detachFromVersion', $implementation->version_id);

    expect($component->get('detachWarning'))->not->toBeNull()
        ->and($implementation->refresh()->isArchived())->toBeFalse();

    $component->call('confirmDetach');
    expect($implementation->refresh()->isArchived())->toBeTrue();
});

it('searches the catalog by name and code', function () {
    Feature::factory()->create(['name' => 'Remboursement anticipé']);
    Feature::factory()->create(['name' => 'Autre', 'code' => 'XYZ']);

    Livewire::test(Index::class)
        ->set('search', 'anticip')
        ->assertSee('Remboursement anticipé')
        ->assertDontSee('Autre')
        ->set('search', 'xyz')
        ->assertSee('Autre');
});
