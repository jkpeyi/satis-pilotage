<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Enums\RuleSource;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Version;
use App\Queries\VersionDiff;

beforeEach(function () {
    $this->credit = Module::factory()->create(['name' => 'Crédit', 'position' => 0]);
    $this->simulation = Feature::factory()->for($this->credit)->create(['name' => 'Simulation', 'position' => 0]);
    $this->scoring = Feature::factory()->for($this->credit)->create(['name' => 'Scoring', 'position' => 1]);
    $this->epargne = Module::factory()->create(['name' => 'Épargne', 'position' => 1]);
    $this->comptes = Module::factory()->childOf($this->epargne)->create(['name' => 'Comptes']);
    $this->ouverture = Feature::factory()->for($this->comptes)->create(['name' => 'Ouverture']);

    $this->a = Version::factory()->create(['name' => 'Banque A']);
    $this->b = Version::factory()->create(['name' => 'Banque B']);
});

function implementationOf(Version $version, $item)
{
    return $version->implementations()->where($item instanceof Feature ? 'feature_id' : 'module_id', $item->id)->sole();
}

it('lists what each version has that the other lacks, grouped at the highest level', function () {
    app(AttachCatalogItems::class)->handle([$this->a->id], [$this->credit->id, $this->epargne->id]);
    app(AttachCatalogItems::class)->handle([$this->b->id], [], [$this->simulation->id]);

    $diff = app(VersionDiff::class)->build($this->a, $this->b);

    expect(array_column($diff['onlyInA'], 'name'))->toBe(['Scoring', 'Épargne'])
        ->and($diff['onlyInA'][1]['children'])->toBe(['Comptes', 'Ouverture'])
        ->and($diff['onlyInA'][0]['path'])->toBe('Crédit')
        ->and($diff['onlyInB'])->toBe([])
        ->and($diff['counts'])->toBe(['onlyInA' => 4, 'onlyInB' => 0, 'different' => 0, 'same' => 2]);
});

it('RG-05: treats an item marked absent as missing from the version', function () {
    app(AttachCatalogItems::class)->handle([$this->a->id, $this->b->id], [$this->credit->id]);
    implementationOf($this->b, $this->scoring)->update(['status' => ImplementationStatus::Absent]);

    $diff = app(VersionDiff::class)->build($this->a, $this->b);

    expect(array_column($diff['onlyInA'], 'name'))->toBe(['Scoring'])
        ->and($diff['onlyInA'][0]['other']->status)->toBe(ImplementationStatus::Absent);
});

it('lists items present in both versions but different, with the reasons', function () {
    app(AttachCatalogItems::class)->handle([$this->a->id, $this->b->id], [$this->credit->id]);
    implementationOf($this->a, $this->simulation)->update(['status' => ImplementationStatus::Defective, 'deviation' => 'Taux mensuel']);
    $origin = BusinessRule::query()->create(['implementation_id' => implementationOf($this->a, $this->scoring)->id, 'title' => 'Seuil', 'description' => '600', 'source' => RuleSource::Code]);
    BusinessRule::query()->create(['implementation_id' => implementationOf($this->b, $this->scoring)->id, 'origin_rule_id' => $origin->id, 'title' => 'Seuil', 'description' => '650', 'source' => RuleSource::Code]);

    $diff = app(VersionDiff::class)->build($this->a, $this->b);

    expect(array_column($diff['different'], 'name'))->toBe(['Simulation', 'Scoring'])
        ->and($diff['different'][0]['reasons'])->toBe(['État', 'Description fonctionnelle'])
        ->and($diff['different'][1]['reasons'])->toBe(['Règles métier'])
        ->and($diff['counts']['same'])->toBe(1);
});

it('considers identical copies of a rule as the same rule', function () {
    app(AttachCatalogItems::class)->handle([$this->a->id, $this->b->id], [], [$this->scoring->id]);
    $origin = BusinessRule::query()->create(['implementation_id' => implementationOf($this->a, $this->scoring)->id, 'title' => 'Seuil', 'description' => '600', 'source' => RuleSource::Code]);
    BusinessRule::query()->create(['implementation_id' => implementationOf($this->b, $this->scoring)->id, 'origin_rule_id' => $origin->id, 'title' => 'Seuil', 'description' => '600', 'source' => RuleSource::Interview]);

    $diff = app(VersionDiff::class)->build($this->a, $this->b);

    expect($diff['different'])->toBe([])
        ->and($diff['counts']['same'])->toBe(2);
});
