<?php

use App\Actions\Referential\SaveVersion;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Livewire\Dashboard;
use App\Livewire\Versions\Form;
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

it('no longer offers environments, releases or deployments on the version page', function () {
    $this->get(route('versions.show', $this->version))
        ->assertOk()
        ->assertSee('Dépôts')
        ->assertDontSee('Environnements')
        ->assertDontSee('Releases')
        ->assertDontSee('Déploiements');
});

it('does not count future or retired versions as versions in service', function () {
    Version::factory()->for($this->version->product)->create(['status' => VersionStatus::Future]);
    Version::factory()->for($this->version->product)->create(['status' => VersionStatus::Retired]);

    Livewire::test(Dashboard::class)
        ->assertViewHas('counts', fn (array $counts): bool => $counts['Versions en service'] === 1);
});
