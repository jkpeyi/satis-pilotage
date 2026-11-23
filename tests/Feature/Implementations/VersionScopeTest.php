<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Livewire\Versions\Scope;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->module = Module::factory()->create(['name' => 'Crédit']);
    $this->feature = Feature::factory()->for($this->module)->create(['name' => 'Simulation']);
    app(AttachCatalogItems::class)->handle([$this->version->id], [$this->module->id]);
    $this->featureImplementation = $this->version->implementations()->where('feature_id', $this->feature->id)->sole();
});

it('EF-210: renders the version scope as a tree', function () {
    $this->get(route('versions.scope', $this->version))
        ->assertOk()
        ->assertSeeInOrder(['Crédit', 'Simulation']);
});

it('RG-06: changes one status and records who did it', function () {
    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('setStatus', $this->featureImplementation->id, 'defective');

    $change = $this->featureImplementation->statusChanges()->sole();
    expect($change->to_status)->toBe(ImplementationStatus::Defective)
        ->and($change->user_id)->toBe($this->user->id);
});

it('EF-206: changes the status of the selection with a note', function () {
    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('selectAll')
        ->set('bulkStatus', 'partial')
        ->set('bulkNote', 'Revue Crédit')
        ->call('applyBulkStatus')
        ->assertHasNoErrors()
        ->assertSet('selectedIds', []);

    expect($this->version->implementations()->pluck('status')->unique()->all())->toBe([ImplementationStatus::Partial]);
});

it('refuses to change implementations of another version', function () {
    $foreign = Implementation::factory()->create();

    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('setStatus', $foreign->id, 'defective')
        ->assertNotFound();
});

it('filters the scope by status', function () {
    $this->featureImplementation->update(['status' => ImplementationStatus::Absent]);

    Livewire::test(Scope::class, ['version' => $this->version])
        ->set('statusFilter', 'absent')
        ->assertSee('Simulation')
        ->assertDontSee('Crédit</span>', escape: false);
});

it('EF-203: creates modules, sub-modules and features inline from the version scope', function () {
    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('startDraft', $this->module->id, 'feature')
        ->assertDispatched('scope-open-module', id: $this->module->id)
        ->set('draftName', 'Garanties')->call('saveDraft')
        ->call('setDraftKind', 'module')
        ->set('draftName', 'Remboursement')->call('saveDraft')
        ->call('indentDraft')
        ->call('setDraftKind', 'feature')
        ->set('draftName', 'Échéancier')->call('saveDraft')
        ->call('outdentDraft')->call('outdentDraft')
        ->set('draftName', 'Épargne')->call('saveDraft')
        ->assertHasNoErrors()
        ->assertSet('draft', ['parent_id' => null, 'kind' => 'module']);

    $repayment = Module::query()->where('name', 'Remboursement')->sole();
    $attached = fn (string $name) => $this->version->implementations()
        ->where(fn ($query) => $query->whereHas('feature', fn ($feature) => $feature->where('name', $name))->orWhereHas('module', fn ($module) => $module->where('name', $name)))
        ->exists();

    expect($repayment->parent_id)->toBe($this->module->id)
        ->and(Feature::query()->where('name', 'Échéancier')->sole()->module_id)->toBe($repayment->id)
        ->and(Module::query()->where('name', 'Épargne')->sole()->parent_id)->toBeNull()
        ->and(collect(['Garanties', 'Remboursement', 'Échéancier', 'Épargne'])->every($attached))->toBeTrue();
});

it('keeps a feature under a module when entering it inline', function () {
    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('startDraft', null, 'feature')
        ->assertSet('draft', ['parent_id' => null, 'kind' => 'module'])
        ->call('setDraftKind', 'feature')
        ->assertSet('draft.kind', 'module');
});

it('RG-02: moves an item by drag and drop among the items of the version, in the shared catalog', function () {
    $other = Version::factory()->create();
    $hidden = Feature::factory()->for($this->module)->create(['name' => 'Non rattachée']);
    $scoring = Feature::factory()->for($this->module)->create(['name' => 'Scoring']);
    $savings = Module::factory()->create(['name' => 'Épargne']);
    app(AttachCatalogItems::class)->handle([$this->version->id], [], [$scoring->id]);
    app(AttachCatalogItems::class)->handle([$this->version->id], [$savings->id]);
    $order = fn () => Feature::query()->where('module_id', $this->module->id)->orderBy('position')->pluck('name')->all();
    expect($order())->toBe(['Simulation', 'Non rattachée', 'Scoring']);

    // Scoring dropped first among the two features of this version: before Simulation in the catalog.
    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('move', 'features', $scoring->id, $this->module->id, 0)
        ->assertDispatched('notify');
    expect($order())->toBe(['Scoring', 'Simulation', 'Non rattachée']);

    // Simulation dropped under Épargne.
    Livewire::test(Scope::class, ['version' => $this->version])->call('move', 'features', $this->feature->id, $savings->id, 0);
    expect($this->feature->refresh()->module_id)->toBe($savings->id);

    // A module of another version only is not a valid destination.
    $elsewhere = Module::factory()->create();
    app(AttachCatalogItems::class)->handle([$other->id], [$elsewhere->id]);
    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('move', 'features', $scoring->id, $elsewhere->id, 0)
        ->assertDispatched('notify', message: 'Le module de destination n’est pas dans le périmètre de cette version.');
    expect($scoring->refresh()->module_id)->toBe($this->module->id);
});

it('RG-15: reuses an existing item from the inline entry: attach, move here or show here too', function () {
    $savings = Module::factory()->create(['name' => 'Épargne']);
    app(AttachCatalogItems::class)->handle([$this->version->id], [$savings->id]);
    $guarantees = Feature::factory()->for(Module::factory()->create(['name' => 'Garanties']))->create(['name' => 'Caution']);
    $alert = Feature::factory()->for(Module::factory()->create(['name' => 'Notifications']))->create(['name' => 'Alerte']);
    $report = Feature::factory()->for(Module::factory()->create(['name' => 'Reporting']))->create(['name' => 'Relevé']);

    $page = Livewire::test(Scope::class, ['version' => $this->version])
        ->call('startDraft', $savings->id, 'feature')
        ->set('draftName', 'Caution')
        ->assertSee('Rattacher')->assertSee('Déplacer ici')->assertSee('Ajouter ici')
        ->call('saveDraft')
        ->assertHasErrors(['draftName'])
        ->call('reuseExisting', 'feature:'.$guarantees->id, 'attach')
        ->assertHasNoErrors()
        ->set('draftName', 'Caution')
        ->assertSee('Déjà dans le périmètre')
        ->call('reuseExisting', 'feature:'.$alert->id, 'move')
        ->call('reuseExisting', 'feature:'.$report->id, 'link');

    expect($guarantees->refresh()->module_id)->not->toBe($savings->id)
        ->and($alert->refresh()->module_id)->toBe($savings->id)
        ->and($report->refresh()->module_id)->not->toBe($savings->id)
        ->and($report->otherModules()->pluck('modules.id')->all())->toBe([$savings->id])
        ->and($this->version->implementations()->whereIn('feature_id', [$guarantees->id, $alert->id, $report->id])->count())->toBe(3)
        ->and($this->version->implementations()->where('module_id', $guarantees->module_id)->exists())->toBeTrue();
});

it('detaches the selection with its subtree', function () {
    $moduleImplementation = $this->version->implementations()->where('module_id', $this->module->id)->sole();

    Livewire::test(Scope::class, ['version' => $this->version])
        ->set('selectedIds', [(string) $moduleImplementation->id])
        ->call('detachSelected');

    expect($this->featureImplementation->refresh()->isArchived())->toBeTrue();
});

it('attaches catalog items from the modal', function () {
    $epargne = Module::factory()->create(['name' => 'Épargne']);
    Feature::factory()->for($epargne)->create();

    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('openAttach')
        ->set('attachModuleIds', [(string) $epargne->id])
        ->call('attach')
        ->assertSet('showAttach', false);

    expect($this->version->implementations()->count())->toBe(4);
});

it('shows the status history of an implementation', function () {
    $this->featureImplementation->update(['status' => ImplementationStatus::Operational]);

    Livewire::test(Scope::class, ['version' => $this->version])
        ->call('showHistoryOf', $this->featureImplementation->id)
        ->assertSet('showHistory', true)
        ->assertSee('Opérationnel');
});
