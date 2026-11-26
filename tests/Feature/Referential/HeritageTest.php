<?php

use App\Actions\Referential\SaveVersion;
use App\Enums\EnvironmentType;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Livewire\Dashboard;
use App\Livewire\Versions\Form;
use App\Livewire\Versions\Show;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->repository = Repository::factory()->for($this->version)->create();
});

it('records lifecycle, historical pivot, technical owner and verification of a version', function () {
    $data = [
        'product_id' => $this->version->product_id,
        'customer_id' => $this->version->customer_id,
        'name' => $this->version->name,
        'status' => VersionStatus::InProduction,
        'is_historical_pivot' => true,
        'technical_owner_id' => $this->user->id,
        'verification_status' => VerificationStatus::Verified,
        'notes' => null,
    ];

    $version = app(SaveVersion::class)->handle($data, $this->version);

    expect($version->is_historical_pivot)->toBeTrue()
        ->and($version->technicalOwner?->name)->toBe('Awa Koné')
        ->and($version->verification_status)->toBe(VerificationStatus::Verified)
        ->and($version->verified_by)->toBe($this->user->id)
        ->and($version->verified_at)->not->toBeNull();

    $version = app(SaveVersion::class)->handle([...$data, 'verification_status' => VerificationStatus::ToVerify], $version);

    expect($version->verified_by)->toBeNull()->and($version->verified_at)->toBeNull();
});

it('keeps the original verifier when a verified version is saved again', function () {
    $other = User::factory()->create();
    $this->version->applyVerificationStatus(VerificationStatus::Verified);
    $this->version->save();

    $this->actingAs($other);
    $this->version->applyVerificationStatus(VerificationStatus::Verified);

    expect($this->version->verified_by)->toBe($this->user->id);
});

it('edits the new version fields from the version form', function () {
    Livewire::test(Form::class, ['version' => $this->version])
        ->set('form.status', 'future')
        ->set('form.is_historical_pivot', true)
        ->set('form.technical_owner_id', (string) $this->user->id)
        ->set('form.verification_status', 'unverifiable')
        ->call('save')
        ->assertHasNoErrors();

    $version = $this->version->refresh();
    expect($version->status)->toBe(VersionStatus::Future)
        ->and($version->is_historical_pivot)->toBeTrue()
        ->and($version->technical_owner_id)->toBe($this->user->id)
        ->and($version->verification_status)->toBe(VerificationStatus::Unverifiable);
});

it('records a deputy who differs from the technical owner', function () {
    $deputy = User::factory()->create(['name' => 'Koffi Yao']);

    Livewire::test(Form::class, ['version' => $this->version])
        ->set('form.technical_owner_id', (string) $this->user->id)
        ->set('form.deputy_id', (string) $this->user->id)
        ->call('save')
        ->assertHasErrors(['form.deputy_id' => 'different'])
        ->set('form.deputy_id', (string) $deputy->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->version->refresh()->deputy_id)->toBe($deputy->id);
    $this->get(route('versions.show', $this->version))->assertSee('Suppléant : Koffi Yao');
    $this->get(route('versions.index'))->assertSee('Koffi Yao');
});

it('manages environments of a version by kind, name and notes, without releases or deployments', function () {
    Livewire::test(Show::class, ['version' => $this->version])
        ->call('createEnvironment')
        ->assertViewHas('environmentTypes', fn ($types): bool => $types->all() === ['production' => 'Production', 'staging' => 'Pré-production', 'testing' => 'Test'])
        ->set('environmentForm.type', 'staging')
        ->set('environmentForm.name', 'Préprod Abidjan')
        ->set('environmentForm.notes', 'Données anonymisées.')
        ->call('saveEnvironment')
        ->assertHasNoErrors()
        ->assertSee('Préprod Abidjan')
        ->call('createEnvironment')
        ->set('environmentForm.name', 'Préprod Abidjan')
        ->call('saveEnvironment')
        ->assertHasErrors(['environmentForm.name' => 'unique']);

    expect($this->version->environments()->sole())->type->toBe(EnvironmentType::Staging)->notes->toBe('Données anonymisées.');
    $this->get(route('versions.show', $this->version))->assertDontSee('Releases')->assertDontSee('Déploiements');
});

it('does not count future or retired versions as versions in service', function () {
    Version::factory()->for($this->version->product)->create(['status' => VersionStatus::Future]);
    Version::factory()->for($this->version->product)->create(['status' => VersionStatus::Retired]);

    Livewire::test(Dashboard::class)
        ->assertViewHas('counts', fn (array $counts): bool => $counts['Versions en service'] === 1);
});
