<?php

use App\Actions\Architecture\SaveComponent;
use App\Enums\ComponentType;
use App\Livewire\Versions\Architecture;
use App\Models\Component;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->repository = Repository::factory()->for($this->version)->create(['path' => 'satis/api']);
});

it('describes the components of a version with a note', function () {
    $page = Livewire::test(Architecture::class, ['version' => $this->version])
        ->call('createComponent')
        ->set('componentForm.name', 'API métier')
        ->set('componentForm.type', 'api')
        ->set('componentForm.repository_id', (string) $this->repository->id)
        ->set('componentForm.technology', 'Laravel')
        ->set('componentForm.technology_version', '9.52.16')
        ->set('componentForm.description', 'Expose les **services** métier.')
        ->call('saveComponent')
        ->assertHasNoErrors();

    $api = Component::query()->where('name', 'API métier')->sole();
    expect($api->technologyLabel())->toBe('Laravel 9.52.16')
        ->and($api->description)->toBe('Expose les **services** métier.');

    $page->assertSee('Expose les **services** métier.')
        ->assertDontSee('API et intégrations')
        ->assertDontSee('Ajouter une intégration');
});

it('refuses a repository of another version', function () {
    app(SaveComponent::class)->handle([
        'version_id' => $this->version->id, 'repository_id' => Repository::factory()->create()->id, 'name' => 'API',
        'type' => ComponentType::Api, 'technology' => null, 'technology_version' => null, 'description' => null,
    ]);
})->throws(DomainException::class, 'Le dépôt choisi n’appartient pas à cette version.');

it('archives and restores components', function () {
    $component = Component::factory()->for($this->version)->create(['name' => 'Moteur de rapprochement']);

    Livewire::test(Architecture::class, ['version' => $this->version])
        ->call('toggleComponentArchive', $component->id)
        ->assertDontSee('Moteur de rapprochement');

    // The toggle « éléments archivés » of the version page is passed down to the block.
    Livewire::test(Architecture::class, ['version' => $this->version, 'showArchived' => true])
        ->assertSee('Moteur de rapprochement')
        ->call('toggleComponentArchive', $component->id);

    expect($component->refresh()->isArchived())->toBeFalse();
});

it('shows the components in the version page, under the repositories, without integrations', function () {
    $this->get(route('versions.show', $this->version))->assertOk()
        ->assertSeeInOrder(['Dépôts', 'Composants', 'Environnements'])
        ->assertDontSee('API et intégrations');
});
