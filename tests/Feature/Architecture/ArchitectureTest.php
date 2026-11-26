<?php

use App\Actions\Architecture\SaveComponent;
use App\Actions\Architecture\SaveIntegration;
use App\Enums\ComponentType;
use App\Enums\IntegrationProtocol;
use App\Enums\VerificationStatus;
use App\Livewire\Versions\Architecture;
use App\Models\Component;
use App\Models\Integration;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->repository = Repository::factory()->for($this->version)->create(['path' => 'satis/api']);
});

it('describes components and integrations from the architecture page', function () {
    $page = Livewire::test(Architecture::class, ['version' => $this->version])
        ->call('createComponent')
        ->set('componentForm.name', 'API métier')
        ->set('componentForm.type', 'api')
        ->set('componentForm.repository_id', (string) $this->repository->id)
        ->set('componentForm.technology', 'Laravel')
        ->set('componentForm.technology_version', '9.52.16')
        ->set('componentForm.description', 'Expose les **services** métier.')
        ->call('saveComponent')
        ->assertHasNoErrors()
        ->call('createComponent')
        ->set('componentForm.name', 'Front agences')
        ->set('componentForm.type', 'web')
        ->call('saveComponent')
        ->assertHasNoErrors();

    [$api, $front] = [Component::query()->where('name', 'API métier')->sole(), Component::query()->where('name', 'Front agences')->sole()];
    expect($api->technologyLabel())->toBe('Laravel 9.52.16')
        ->and($api->description)->toBe('Expose les **services** métier.');
    // Integrations are added from the « API et intégrations » card only, not from a component row.
    $page->assertSee('Expose les **services** métier.')
        ->assertDontSeeHtml('wire:click="createIntegration('.$api->id.')"');

    $page->call('createIntegration', $front->id)
        ->set('integrationForm.target_component_id', (string) $front->id)
        ->call('saveIntegration')
        ->assertHasErrors(['integrationForm.target_component_id' => 'different'])
        ->set('integrationForm.target_component_id', (string) $api->id)
        ->set('integrationForm.protocol', 'rest')
        ->call('saveIntegration')
        ->assertHasNoErrors()
        ->call('createIntegration', $api->id)
        ->set('integrationForm.target_kind', 'external')
        ->call('saveIntegration')
        ->assertHasErrors(['integrationForm.external_system'])
        ->set('integrationForm.external_system', 'Centrale des risques')
        ->set('integrationForm.protocol', 'file')
        ->call('saveIntegration')
        ->assertHasNoErrors()
        ->assertSee('Centrale des risques')
        ->assertSee('Échange de fichiers');

    expect(Integration::query()->where('source_component_id', $front->id)->sole()->targetLabel())->toBe($api->displayCode().' · API métier')
        ->and(Integration::query()->where('source_component_id', $api->id)->sole()->isExternal())->toBeTrue();
});

it('refuses a repository of another version and an integration without exactly one target', function () {
    $data = [
        'version_id' => $this->version->id, 'repository_id' => Repository::factory()->create()->id, 'name' => 'API',
        'type' => ComponentType::Api, 'technology' => null, 'technology_version' => null, 'description' => null,
        'evidence_url' => null, 'verification_status' => VerificationStatus::ToVerify,
    ];
    expect(fn () => app(SaveComponent::class)->handle($data))->toThrow(DomainException::class, 'Le dépôt choisi n’appartient pas à cette version.');

    $component = Component::factory()->for($this->version)->create();
    $integration = [
        'source_component_id' => $component->id, 'target_component_id' => null, 'external_system' => null,
        'protocol' => IntegrationProtocol::Rest, 'description' => null, 'evidence_url' => null, 'verification_status' => VerificationStatus::ToVerify,
    ];
    expect(fn () => app(SaveIntegration::class)->handle($integration))->toThrow(DomainException::class)
        ->and(fn () => app(SaveIntegration::class)->handle([...$integration, 'target_component_id' => $component->id]))->toThrow(DomainException::class);
});

it('enforces exactly one target and no self-integration in the database', function () {
    $component = Component::factory()->for($this->version)->create();
    $other = Component::factory()->for($this->version)->create();

    expect(fn () => Integration::query()->create(['source_component_id' => $component->id, 'target_component_id' => $other->id, 'external_system' => 'X', 'protocol' => 'rest']))
        ->toThrow(QueryException::class)
        ->and(fn () => Integration::query()->create(['source_component_id' => $component->id, 'target_component_id' => $component->id, 'protocol' => 'rest']))
        ->toThrow(QueryException::class);
});

it('archives and restores components and integrations', function () {
    $component = Component::factory()->for($this->version)->create(['name' => 'Moteur de rapprochement']);
    $integration = Integration::factory()->create(['source_component_id' => $component->id]);

    Livewire::test(Architecture::class, ['version' => $this->version])
        ->call('toggleIntegrationArchive', $integration->id)
        ->call('toggleComponentArchive', $component->id)
        ->assertDontSee('Moteur de rapprochement');

    // The toggle « éléments archivés » of the version page is passed down to the block.
    Livewire::test(Architecture::class, ['version' => $this->version, 'showArchived' => true])
        ->assertSee('Moteur de rapprochement')
        ->call('toggleComponentArchive', $component->id);

    expect($component->refresh()->isArchived())->toBeFalse()->and($integration->refresh()->isArchived())->toBeTrue();
});

it('shows components and integrations in the version page, under the repositories', function () {
    $this->get(route('versions.show', $this->version))->assertOk()
        ->assertSeeInOrder(['Dépôts', 'Composants', 'API et intégrations', 'Environnements']);
});
