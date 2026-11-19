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

it('EF-203: creates a feature from the version scope', function () {
    Livewire::test(Scope::class, ['version' => $this->version])
        ->set('newKind', 'feature')
        ->set('newParentId', (string) $this->module->id)
        ->set('newName', 'Garanties')
        ->call('createItem')
        ->assertHasNoErrors();

    expect($this->version->implementations()->whereHas('feature', fn ($query) => $query->where('name', 'Garanties'))->exists())->toBeTrue();
});

it('requires a parent module to create a feature', function () {
    Livewire::test(Scope::class, ['version' => $this->version])
        ->set('newKind', 'feature')
        ->set('newName', 'Orpheline')
        ->call('createItem')
        ->assertHasErrors('newParentId');
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
