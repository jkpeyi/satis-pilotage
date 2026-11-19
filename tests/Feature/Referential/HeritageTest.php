<?php

use App\Actions\Referential\ArchiveDeployment;
use App\Actions\Referential\SaveDeployment;
use App\Actions\Referential\SaveVersion;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Livewire\Dashboard;
use App\Livewire\Versions\Form;
use App\Livewire\Versions\Show;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\Release;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->repository = Repository::factory()->for($this->version)->create();
    $this->environment = Environment::factory()->for($this->version)->create(['repository_id' => $this->repository->id, 'deployed_ref' => 'main']);
    $this->release = Release::factory()->for($this->repository)->create(['name' => 'v2.3.0', 'commit_sha' => str_repeat('a', 40)]);

    $this->deploy = fn (array $data = []): Deployment => app(SaveDeployment::class)->handle([
        'environment_id' => $this->environment->id,
        'release_id' => $this->release->id,
        'commit_sha' => null,
        'deployed_at' => '2026-06-20 21:00',
        'verification_status' => VerificationStatus::ToVerify,
        'evidence' => null,
        'notes' => null,
        ...$data,
    ]);
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

it('records a deployment and moves the environment reference to the latest one', function () {
    $first = ($this->deploy)(['verification_status' => VerificationStatus::Verified, 'evidence' => 'Pipeline 1234']);

    expect($first->reference())->toBe('v2.3.0 (aaaaaaa)')
        ->and($first->deployedCommit())->toBe(str_repeat('a', 40))
        ->and($first->verified_by)->toBe($this->user->id)
        ->and($this->environment->refresh()->deployed_ref)->toBe('v2.3.0');

    $hotfix = ($this->deploy)(['release_id' => null, 'commit_sha' => 'b1c2d3e4f5', 'deployed_at' => '2026-07-01 08:00']);
    expect($this->environment->refresh()->deployed_ref)->toBe('b1c2d3e4f5');

    app(ArchiveDeployment::class)->handle($hotfix);
    expect($this->environment->refresh()->deployed_ref)->toBe('v2.3.0');
});

it('rejects a release that does not come from the environment repository', function () {
    $foreign = Release::factory()->create();

    ($this->deploy)(['release_id' => $foreign->id]);
})->throws(DomainException::class, 'Cette release ne provient pas du dépôt de cet environnement.');

it('requires a release or a commit, down to the database', function () {
    expect(fn () => ($this->deploy)(['release_id' => null]))->toThrow(DomainException::class);

    expect(fn () => Deployment::query()->create(['environment_id' => $this->environment->id, 'deployed_at' => now()]))
        ->toThrow(QueryException::class);
});

it('manages releases and deployments from the version page', function () {
    Livewire::test(Show::class, ['version' => $this->version])
        ->call('createRelease', $this->repository->id)
        ->set('releaseForm.name', 'v2.4.0')
        ->set('releaseForm.commit_sha', 'not-a-sha')
        ->call('saveRelease')
        ->assertHasErrors(['releaseForm.commit_sha'])
        ->set('releaseForm.commit_sha', 'ABCDEF1234')
        ->call('saveRelease')
        ->assertHasNoErrors()
        ->call('createRelease', $this->repository->id)
        ->set('releaseForm.name', 'v2.4.0')
        ->call('saveRelease')
        ->assertHasErrors(['releaseForm.name' => 'unique']);

    $release = Release::query()->where('name', 'v2.4.0')->sole();
    expect($release->commit_sha)->toBe('abcdef1234');

    Livewire::test(Show::class, ['version' => $this->version])
        ->call('createDeployment', $this->environment->id)
        ->call('saveDeployment')
        ->assertHasErrors(['deploymentForm.release_id', 'deploymentForm.commit_sha'])
        ->set('deploymentForm.release_id', (string) $release->id)
        ->set('deploymentForm.deployed_at', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('saveDeployment')
        ->assertHasErrors(['deploymentForm.deployed_at'])
        ->set('deploymentForm.deployed_at', '2026-10-05T10:30')
        ->set('deploymentForm.verification_status', 'verified')
        ->call('saveDeployment')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSee('v2.4.0 (abcdef1)')
        ->assertSee('Vérifié');

    expect($this->environment->refresh()->deployed_ref)->toBe('v2.4.0');
});

it('does not count future or retired versions as versions in service', function () {
    Version::factory()->for($this->version->product)->create(['status' => VersionStatus::Future]);
    Version::factory()->for($this->version->product)->create(['status' => VersionStatus::Retired]);

    Livewire::test(Dashboard::class)
        ->assertViewHas('counts', fn (array $counts): bool => $counts['Versions en service'] === 1);
});
