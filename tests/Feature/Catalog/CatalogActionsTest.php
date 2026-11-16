<?php

use App\Actions\Catalog\ArchiveCatalogItem;
use App\Actions\Catalog\ConvertFeatureToSubmodule;
use App\Actions\Catalog\ConvertSubmoduleToFeature;
use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\MoveFeature;
use App\Actions\Catalog\MoveModule;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\Module;

function positions(iterable $items): array
{
    return collect($items)->mapWithKeys(fn ($item) => [$item->name => $item->position])->all();
}

it('appends new modules and features at the end of their siblings', function () {
    $credit = app(CreateModule::class)->handle(['name' => 'Crédit']);
    $epargne = app(CreateModule::class)->handle(['name' => 'Épargne']);
    $simulation = app(CreateFeature::class)->handle(['name' => 'Simulation', 'module_id' => $credit->id]);
    $octroi = app(CreateFeature::class)->handle(['name' => 'Octroi', 'module_id' => $credit->id]);

    expect([$credit->position, $epargne->position, $simulation->position, $octroi->position])->toBe([0, 1, 0, 1]);
});

it('inserts a module at a given position and shifts its siblings', function () {
    $a = app(CreateModule::class)->handle(['name' => 'A']);
    $b = app(CreateModule::class)->handle(['name' => 'B']);
    app(CreateModule::class)->handle(['name' => 'C'], position: 1);

    expect(positions(Module::query()->get()))->toBe(['A' => 0, 'B' => 2, 'C' => 1]);
});

it('RG-01: moves a module under another one and compacts the former siblings', function () {
    $a = app(CreateModule::class)->handle(['name' => 'A']);
    $b = app(CreateModule::class)->handle(['name' => 'B']);
    $c = app(CreateModule::class)->handle(['name' => 'C']);

    app(MoveModule::class)->handle($b, $a, 0);

    expect($b->refresh()->parent_id)->toBe($a->id)
        ->and($b->isSubmodule())->toBeTrue()
        ->and(positions(Module::query()->whereNull('parent_id')->get()))->toBe(['A' => 0, 'C' => 1]);
});

it('RG-01: refuses to move a module below itself', function () {
    $a = app(CreateModule::class)->handle(['name' => 'A']);
    $b = app(CreateModule::class)->handle(['name' => 'B', 'parent_id' => $a->id]);
    $c = app(CreateModule::class)->handle(['name' => 'C', 'parent_id' => $b->id]);

    expect(fn () => app(MoveModule::class)->handle($a, $c, 0))->toThrow(DomainException::class)
        ->and(fn () => app(MoveModule::class)->handle($a, $a, 0))->toThrow(DomainException::class);
});

it('moves a feature to another module at a given position', function () {
    $credit = Module::factory()->create();
    $epargne = Module::factory()->create();
    $feature = app(CreateFeature::class)->handle(['name' => 'Simulation', 'module_id' => $credit->id]);
    app(CreateFeature::class)->handle(['name' => 'Ouverture', 'module_id' => $epargne->id]);

    app(MoveFeature::class)->handle($feature, $epargne, 0);

    expect($feature->refresh()->module_id)->toBe($epargne->id)
        ->and(positions($epargne->features()->get()))->toBe(['Simulation' => 0, 'Ouverture' => 1]);
});

it('RG-12: archives a module with its subtree and restores it with its ancestors', function () {
    $root = Module::factory()->create();
    $sub = Module::factory()->childOf($root)->create();
    $feature = Feature::factory()->for($sub)->create();

    app(ArchiveCatalogItem::class)->handle($root);
    expect($root->refresh()->isArchived())->toBeTrue()
        ->and($sub->refresh()->isArchived())->toBeTrue()
        ->and($feature->refresh()->isArchived())->toBeTrue();

    app(ArchiveCatalogItem::class)->handle($root, archive: false);
    expect($sub->refresh()->isArchived())->toBeFalse()
        ->and($feature->refresh()->isArchived())->toBeFalse();

    app(ArchiveCatalogItem::class)->handle($root);
    app(ArchiveCatalogItem::class)->handle($feature, archive: false);
    expect($feature->refresh()->isArchived())->toBeFalse()
        ->and($sub->refresh()->isArchived())->toBeFalse()
        ->and($root->refresh()->isArchived())->toBeFalse();
});

it('RG-14: converts a feature into a sub-module and keeps implementations, rules, issues and history', function () {
    $module = Module::factory()->create();
    $feature = Feature::factory()->for($module)->create(['code' => 'CRD-SIM', 'name' => 'Simulation']);
    $implementation = Implementation::factory()->forFeature($feature)->create();
    $implementation->update(['status' => ImplementationStatus::Defective]);
    $rule = BusinessRule::query()->create([
        'implementation_id' => $implementation->id, 'title' => 'Taux plafond', 'description' => '…', 'source' => RuleSource::Code,
    ]);
    $issue = Issue::query()->create([
        'title' => 'Arrondi', 'description' => '…', 'type' => IssueType::Bug, 'severity' => IssueSeverity::Major,
    ]);
    $issue->implementations()->attach($implementation);

    $submodule = app(ConvertFeatureToSubmodule::class)->handle($feature);

    $implementation->refresh();
    expect($submodule->parent_id)->toBe($module->id)
        ->and($submodule->code)->toBe('CRD-SIM')
        ->and($submodule->name)->toBe('Simulation')
        ->and($feature->refresh()->isArchived())->toBeTrue()
        ->and($implementation->module_id)->toBe($submodule->id)
        ->and($implementation->feature_id)->toBeNull()
        ->and($implementation->status)->toBe(ImplementationStatus::Defective)
        ->and($implementation->businessRules()->sole()->is($rule))->toBeTrue()
        ->and($implementation->issues()->sole()->is($issue))->toBeTrue()
        ->and($implementation->statusChanges()->count())->toBe(1);
});

it('RG-14: converts an empty sub-module back into a feature', function () {
    $module = Module::factory()->create();
    $submodule = Module::factory()->childOf($module)->create(['name' => 'Simulation']);
    $implementation = Implementation::factory()->create(['module_id' => $submodule->id]);

    $feature = app(ConvertSubmoduleToFeature::class)->handle($submodule);

    expect($feature->module_id)->toBe($module->id)
        ->and($submodule->refresh()->isArchived())->toBeTrue()
        ->and($implementation->refresh()->feature_id)->toBe($feature->id)
        ->and($implementation->module_id)->toBeNull();
});

it('RG-14: refuses to convert a root module or a sub-module that still has items', function () {
    $root = Module::factory()->create();
    $submodule = Module::factory()->childOf($root)->create();
    Feature::factory()->for($submodule)->create();

    expect(fn () => app(ConvertSubmoduleToFeature::class)->handle($root))->toThrow(DomainException::class)
        ->and(fn () => app(ConvertSubmoduleToFeature::class)->handle($submodule))->toThrow(DomainException::class);
});
