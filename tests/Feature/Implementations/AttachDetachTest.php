<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Implementations\CreateCatalogItemInVersion;
use App\Actions\Implementations\DetachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Enums\RuleSource;
use App\Exceptions\DetachRequiresConfirmation;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Version;

beforeEach(function () {
    $this->credit = Module::factory()->create(['name' => 'Crédit']);
    $this->octroi = Module::factory()->childOf($this->credit)->create(['name' => 'Octroi']);
    $this->simulation = Feature::factory()->for($this->octroi)->create(['name' => 'Simulation']);
    $this->scoring = Feature::factory()->for($this->octroi)->create(['name' => 'Scoring']);
    $this->version = Version::factory()->create();
});

function attachedNames(Version $version): array
{
    return $version->implementations()->whereNull('archived_at')->with(['module', 'feature'])->get()
        ->map(fn (Implementation $implementation) => $implementation->catalogItem()->name)
        ->sort()->values()->all();
}

it('RG-04: attaching a feature also attaches its ancestors', function () {
    $count = app(AttachCatalogItems::class)->handle([$this->version->id], [], [$this->simulation->id]);

    expect($count)->toBe(3)
        ->and(attachedNames($this->version))->toBe(['Crédit', 'Octroi', 'Simulation']);
});

it('RG-04: attaching a module attaches its descendants unless asked otherwise', function () {
    $other = Version::factory()->create();

    app(AttachCatalogItems::class)->handle([$this->version->id], [$this->octroi->id]);
    app(AttachCatalogItems::class)->handle([$other->id], [$this->octroi->id], withDescendants: false);

    expect(attachedNames($this->version))->toBe(['Crédit', 'Octroi', 'Scoring', 'Simulation'])
        ->and(attachedNames($other))->toBe(['Crédit', 'Octroi']);
});

it('RG-04: attaches to several versions at once and starts with an unknown presence', function () {
    $versions = Version::factory()->count(2)->create();

    app(AttachCatalogItems::class)->handle($versions->modelKeys(), [], [$this->scoring->id]);

    expect(Implementation::query()->count())->toBe(6)
        ->and(Implementation::query()->pluck('status')->unique()->all())->toBe([ImplementationStatus::Unknown]);
});

it('RG-03: attaching twice creates nothing new and restores an archived implementation', function () {
    app(AttachCatalogItems::class)->handle([$this->version->id], [$this->credit->id]);
    $implementation = $this->version->implementations()->where('feature_id', $this->simulation->id)->sole();
    $implementation->update(['status' => ImplementationStatus::Defective]);
    $implementation->archive();

    $count = app(AttachCatalogItems::class)->handle([$this->version->id], [$this->credit->id]);

    expect($count)->toBe(1)
        ->and(Implementation::query()->count())->toBe(4)
        ->and($implementation->refresh()->isArchived())->toBeFalse()
        ->and($implementation->status)->toBe(ImplementationStatus::Defective);
});

it('detaching a module detaches its subtree', function () {
    app(AttachCatalogItems::class)->handle([$this->version->id], [$this->credit->id]);

    $count = app(DetachCatalogItems::class)->handle([$this->version->id], [$this->octroi->id]);

    expect($count)->toBe(3)
        ->and(attachedNames($this->version))->toBe(['Crédit']);
});

it('RG-12: asks for confirmation before detaching items that carry business rules', function () {
    app(AttachCatalogItems::class)->handle([$this->version->id], [$this->credit->id]);
    $implementation = $this->version->implementations()->where('feature_id', $this->simulation->id)->sole();
    BusinessRule::query()->create([
        'implementation_id' => $implementation->id, 'title' => 'Taux plafond', 'description' => '…', 'source' => RuleSource::Code,
    ]);

    expect(fn () => app(DetachCatalogItems::class)->handle([$this->version->id], [], [$this->simulation->id]))
        ->toThrow(DetachRequiresConfirmation::class);
    expect($implementation->refresh()->isArchived())->toBeFalse();

    app(DetachCatalogItems::class)->handle([$this->version->id], [], [$this->simulation->id], confirmed: true);
    expect($implementation->refresh()->isArchived())->toBeTrue();
});

it('EF-203: creates a feature from a version and attaches it with its ancestors', function () {
    $feature = app(CreateCatalogItemInVersion::class)->handle($this->version, 'feature', 'Remboursement anticipé', $this->octroi->id);

    expect($feature)->toBeInstanceOf(Feature::class)
        ->and(attachedNames($this->version))->toBe(['Crédit', 'Octroi', 'Remboursement anticipé']);
});
