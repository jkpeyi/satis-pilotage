<?php

use App\Actions\Catalog\ArchiveCatalogItem;
use App\Actions\Catalog\ConvertFeatureToSubmodule;
use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\LinkFeatureToModule;
use App\Actions\Catalog\MergeCatalogItems;
use App\Actions\Catalog\ReuniteSubmoduleWithFeature;
use App\Actions\Catalog\SetFeatureMainModule;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Reports\GenerateReport;
use App\Enums\ImplementationStatus;
use App\Enums\RuleSource;
use App\Livewire\Catalog\Duplicates;
use App\Livewire\Catalog\Index as CatalogPage;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use App\Queries\CatalogTree;
use App\Queries\ComparisonMatrixData;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->complaints = app(CreateModule::class)->handle(['name' => 'Réclamations']);
    $this->general = app(CreateModule::class)->handle(['name' => 'Général', 'parent_id' => $this->complaints->id]);
    $this->process = app(CreateModule::class)->handle(['name' => 'Processus', 'parent_id' => $this->complaints->id]);
    $this->search = app(CreateFeature::class)->handle(['name' => 'Rechercher une réclamation à partir de sa référence', 'module_id' => $this->general->id]);
    $this->link = fn (Feature $feature, Module $module) => app(LinkFeatureToModule::class)->handle($feature, $module);
});

it('RG-16: shows one feature under several modules without copying it', function () {
    ($this->link)($this->search, $this->process);
    $tree = CatalogTree::load();

    expect($tree->featureIds($this->general->id))->toBe([$this->search->id])
        ->and($tree->sharedFeatureIds($this->process->id))->toBe([$this->search->id])
        ->and($tree->otherModuleIds($this->search->id))->toBe([$this->process->id])
        ->and(Feature::query()->where('name_key', $this->search->name_key)->count())->toBe(1)
        ->and(fn () => ($this->link)($this->search, $this->process))->toThrow(DomainException::class, 'apparaît déjà')
        ->and(fn () => ($this->link)($this->search, $this->general))->toThrow(DomainException::class, 'module principal');
});

it('RG-16: swaps the main module with one of the other modules', function () {
    ($this->link)($this->search, $this->process);

    app(SetFeatureMainModule::class)->handle($this->search, $this->process);

    expect($this->search->refresh()->module_id)->toBe($this->process->id)
        ->and($this->search->otherModules()->pluck('modules.id')->all())->toBe([$this->general->id]);
});

it('RG-04 / RG-16: attaches only the main branch with the feature, and the shared feature with a module', function () {
    ($this->link)($this->search, $this->process);
    $alpha = Version::factory()->create();
    $beta = Version::factory()->create();

    app(AttachCatalogItems::class)->handle([$alpha->id], [], [$this->search->id]);
    app(AttachCatalogItems::class)->handle([$beta->id], [$this->process->id]);

    expect($alpha->implementations()->pluck('module_id')->filter()->sort()->values()->all())->toBe([$this->complaints->id, $this->general->id])
        ->and($beta->implementations()->pluck('module_id')->filter()->sort()->values()->all())->toBe([$this->complaints->id, $this->process->id])
        ->and($beta->implementations()->pluck('feature_id')->filter()->values()->all())->toBe([$this->search->id]);
});

it('keeps a shared feature active when its main module is archived', function () {
    ($this->link)($this->search, $this->process);

    app(ArchiveCatalogItem::class)->handle($this->general);

    expect($this->search->refresh()->isArchived())->toBeFalse()
        ->and($this->search->module_id)->toBe($this->process->id)
        ->and($this->search->otherModules()->count())->toBe(0);
});

it('refuses to convert a shared feature to a sub-module', function () {
    ($this->link)($this->search, $this->process);

    app(ConvertFeatureToSubmodule::class)->handle($this->search);
})->throws(DomainException::class, 'plusieurs modules');

it('gives the merged feature the modules of the duplicate', function () {
    $copy = app(CreateFeature::class)->handle(['name' => 'Recherche par référence', 'module_id' => $this->process->id]);

    app(MergeCatalogItems::class)->handle($copy, $this->search);

    expect($this->search->otherModules()->pluck('modules.id')->all())->toBe([$this->process->id]);
});

it('reunites a sub-module and a feature of the same name into one feature present in both modules', function () {
    $submodule = Module::query()->create(['name' => 'Rechercher une reclamation a partir de sa reference (copie)', 'parent_id' => $this->process->id, 'position' => 0]);
    $version = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$version->id], [$submodule->id]);
    $implementation = $version->implementations()->where('module_id', $submodule->id)->sole();
    $implementation->update(['status' => ImplementationStatus::Operational]);
    $rule = BusinessRule::query()->create(['implementation_id' => $implementation->id, 'title' => 'Référence obligatoire', 'description' => 'R', 'source' => RuleSource::Interview]);

    $summary = app(ReuniteSubmoduleWithFeature::class)->handle($submodule, $this->search);

    $moved = $implementation->refresh();
    expect($summary['implementations_moved'])->toBe(1)
        ->and($moved->feature_id)->toBe($this->search->id)
        ->and($moved->module_id)->toBeNull()
        ->and($moved->status)->toBe(ImplementationStatus::Operational)
        ->and($rule->refresh()->implementation_id)->toBe($moved->id)
        ->and(Module::query()->find($submodule->id))->toBeNull()
        ->and($this->search->otherModules()->pluck('modules.id')->all())->toBe([$this->process->id])
        ->and(json_decode((string) DB::table('catalog_merges')->value('details'), true)['reunited_into'])->toBe('feature');
});

it('refuses to reunite a root module or a sub-module that still contains items', function () {
    $reunite = app(ReuniteSubmoduleWithFeature::class);

    expect(fn () => $reunite->handle($this->complaints, $this->search))->toThrow(DomainException::class, 'module racine');

    app(CreateFeature::class)->handle(['name' => 'Escalader', 'module_id' => $this->process->id]);
    expect(fn () => $reunite->handle($this->process, $this->search))->toThrow(DomainException::class, 'contient des éléments');
});

it('links an existing feature from the quick entry and manages its modules from its panel', function () {
    Livewire::test(CatalogPage::class)
        ->call('toggle', $this->complaints->id)
        ->call('startDraft', $this->process->id, 'feature')
        ->set('draftName', 'rechercher une reclamation')
        ->assertSee('Ajouter ici')
        ->call('linkExistingHere', 'feature:'.$this->search->id)
        ->assertHasNoErrors()
        ->call('select', 'feature:'.$this->search->id)
        ->assertSee('Réclamations › Processus')
        ->assertSee('Principal')
        ->call('makeMainModule', $this->process->id)
        ->call('unlinkModule', $this->general->id)
        ->set('linkModuleId', (string) $this->general->id)
        ->call('linkModule')
        ->assertHasNoErrors();

    expect($this->search->refresh()->module_id)->toBe($this->process->id)
        ->and($this->search->otherModules()->pluck('modules.id')->all())->toBe([$this->general->id]);
});

it('offers to reunite a sub-module and a feature of the same name on the duplicates page', function () {
    DB::table('modules')->insert(['name' => $this->search->name, 'name_key' => $this->search->name_key, 'parent_id' => $this->process->id, 'position' => 9, 'created_at' => now(), 'updated_at' => now()]);
    $submodule = Module::query()->where('name_key', $this->search->name_key)->sole();

    Livewire::test(Duplicates::class)
        ->assertSee('Réunir')
        ->call('reunite', $submodule->id, $this->search->id)
        ->assertDispatched('notify');

    expect(Module::query()->find($submodule->id))->toBeNull()
        ->and($this->search->otherModules()->pluck('modules.id')->all())->toBe([$this->process->id]);
});

it('lists a shared feature once in the comparison matrix and in L2, with its other modules', function () {
    Storage::fake('local');
    ($this->link)($this->search, $this->process);
    $version = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$version->id], [$this->complaints->id]);

    $rows = app(ComparisonMatrixData::class)->build([$version->id])['rows'];
    expect(collect($rows)->where('key', 'feature:'.$this->search->id)->count())->toBe(1);

    $generation = app(GenerateReport::class)->handle('L2', new ReportScope($version->product_id), User::query()->firstOrFail());
    $features = json_decode($generation->snapshot, true)['sections']['features.table']['rows'];
    expect($features)->toHaveCount(1)
        ->and($features[0])->toMatchArray(['module' => 'Réclamations', 'submodule' => 'Général', 'other_modules' => 'Processus']);
});
