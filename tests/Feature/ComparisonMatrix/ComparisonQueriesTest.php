<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Module;
use App\Models\Product;
use App\Models\Version;
use App\Queries\ComparisonMatrixData;
use App\Queries\RowComparison;

beforeEach(function () {
    $this->credit = Module::factory()->create(['name' => 'Crédit', 'position' => 0]);
    $this->simulation = Feature::factory()->for($this->credit)->create(['name' => 'Simulation', 'position' => 0]);
    $this->scoring = Feature::factory()->for($this->credit)->create(['name' => 'Scoring', 'position' => 1]);
    $this->unused = Module::factory()->create(['name' => 'Inutilisé', 'position' => 1]);

    $this->product = Product::factory()->create();
    $this->baseline = Version::factory()->baseline()->for($this->product)->create();
    $this->client = Version::factory()->for($this->product)->create();
    $this->product->update(['reference_version_id' => $this->baseline->id]);

    app(AttachCatalogItems::class)->handle([$this->baseline->id], [$this->credit->id]);
    app(AttachCatalogItems::class)->handle([$this->client->id], [], [$this->simulation->id]);
});

function implementationIn(Version $version, Feature $feature)
{
    return $version->implementations()->where('feature_id', $feature->id)->sole();
}

it('EF-302: lists used rows in tree order with one cell per attached version', function () {
    $data = app(ComparisonMatrixData::class)->build([$this->client->id, $this->baseline->id]);

    expect(array_column($data['rows'], 'key'))->toBe(['module:'.$this->credit->id, 'feature:'.$this->simulation->id, 'feature:'.$this->scoring->id])
        ->and(array_column($data['rows'], 'depth'))->toBe([0, 1, 1])
        ->and(array_column($data['versions'], 'id'))->toBe([$this->client->id, $this->baseline->id])
        ->and($data['versions'][1]['reference'])->toBeTrue()
        ->and($data['cells']['feature:'.$this->scoring->id])->toHaveKey($this->baseline->id)
        ->and($data['cells']['feature:'.$this->scoring->id])->not->toHaveKey($this->client->id);
});

it('EF-302: carries status, deviation flag, rule and issue counts in each cell', function () {
    $implementation = implementationIn($this->client, $this->simulation);
    $implementation->update(['status' => ImplementationStatus::Defective, 'deviation' => 'Taux mensuel']);
    BusinessRule::query()->create(['implementation_id' => $implementation->id, 'title' => 'R', 'description' => 'D', 'source' => RuleSource::Code]);
    $archived = BusinessRule::query()->create(['implementation_id' => $implementation->id, 'title' => 'R2', 'description' => 'D', 'source' => RuleSource::Code]);
    $archived->archive();
    Issue::query()->create(['title' => 'I', 'description' => 'D', 'type' => IssueType::Bug, 'severity' => IssueSeverity::Minor])
        ->implementations()->attach($implementation);

    $cell = app(ComparisonMatrixData::class)->build([$this->client->id])['cells']['feature:'.$this->simulation->id][$this->client->id];

    expect($cell)->toBe(['id' => $implementation->id, 's' => 'defective', 'd' => true, 'r' => 1, 'i' => 1]);
});

it('EF-301: restricts the matrix to a branch of the catalog', function () {
    $other = Module::factory()->create(['name' => 'Épargne']);
    app(AttachCatalogItems::class)->handle([$this->baseline->id], [$other->id]);

    $data = app(ComparisonMatrixData::class)->build([$this->baseline->id], $this->credit->id);

    expect(array_column($data['rows'], 'name'))->toBe(['Crédit', 'Simulation', 'Scoring']);
});

it('EF-306: aligns business rules by origin and flags divergent copies', function () {
    $origin = BusinessRule::query()->create(['implementation_id' => implementationIn($this->baseline, $this->simulation)->id, 'title' => 'Plafond', 'description' => '10 %', 'source' => RuleSource::Code]);
    BusinessRule::query()->create(['implementation_id' => implementationIn($this->client, $this->simulation)->id, 'origin_rule_id' => $origin->id, 'title' => 'Plafond', 'description' => '12 %', 'source' => RuleSource::Code]);
    BusinessRule::query()->create(['implementation_id' => implementationIn($this->client, $this->simulation)->id, 'title' => 'Spécifique', 'description' => 'x', 'source' => RuleSource::Interview]);

    $comparison = app(RowComparison::class)->build('feature:'.$this->simulation->id, [$this->baseline->id, $this->client->id]);

    expect($comparison['ruleFamilies'])->toHaveCount(2)
        ->and($comparison['ruleFamilies'][0]['title'])->toBe('Plafond')
        ->and($comparison['ruleFamilies'][0]['divergent'])->toBeTrue()
        ->and($comparison['ruleFamilies'][1]['rules'][$this->baseline->id])->toBeNull()
        ->and($comparison['implementations'][$this->baseline->id])->not->toBeNull();
});

it('EF-306: shows a version that does not use the item as empty', function () {
    $comparison = app(RowComparison::class)->build('feature:'.$this->scoring->id, [$this->baseline->id, $this->client->id]);

    expect($comparison['implementations'][$this->client->id])->toBeNull()
        ->and($comparison['versions']->pluck('id')->all())->toBe([$this->baseline->id, $this->client->id]);
});
