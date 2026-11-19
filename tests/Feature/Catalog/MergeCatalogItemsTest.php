<?php

use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\MergeCatalogItems;
use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Livewire\Catalog\Duplicates;
use App\Livewire\Catalog\Index as CatalogPage;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->credit = app(CreateModule::class)->handle(['name' => 'Crédit']);
    $this->kept = app(CreateFeature::class)->handle(['name' => 'Simulation', 'module_id' => $this->credit->id]);
    $this->duplicate = app(CreateFeature::class)->handle(['name' => 'Simulaton', 'module_id' => $this->credit->id, 'code' => 'CRD-SIM', 'standard_description' => 'Calcule les échéances.']);
    $this->onlyDuplicate = Version::factory()->create();
    $this->both = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->onlyDuplicate->id, $this->both->id], [], [$this->duplicate->id]);
    app(AttachCatalogItems::class)->handle([$this->both->id], [], [$this->kept->id]);

    $this->implementationOf = fn (Version $version, Feature $feature): ?Implementation => $version->implementations()->where('feature_id', $feature->id)->first();
});

it('moves implementations, workflow steps, code and description, deletes the duplicate and logs the merge', function () {
    $moving = ($this->implementationOf)($this->onlyDuplicate, $this->duplicate);
    $step = Workflow::factory()->create()->steps()->create(['position' => 1, 'name' => 'Simulation', 'feature_id' => $this->duplicate->id]);

    $summary = app(MergeCatalogItems::class)->handle($this->duplicate, $this->kept);

    expect($summary)->toMatchArray(['implementations_moved' => 1, 'implementations_merged' => 1, 'steps_moved' => 1])
        ->and($moving->refresh()->feature_id)->toBe($this->kept->id)
        ->and($step->refresh()->feature_id)->toBe($this->kept->id)
        ->and(Feature::query()->find($this->duplicate->id))->toBeNull()
        ->and($this->kept->refresh()->code)->toBe('CRD-SIM')
        ->and($this->kept->standard_description)->toBe('Calcule les échéances.')
        ->and(DB::table('catalog_merges')->sole())->toMatchArray(['kind' => 'feature', 'kept_id' => $this->kept->id, 'merged_id' => $this->duplicate->id, 'merged_name' => 'Simulaton', 'created_by' => $this->user->id]);
});

it('merges the two implementations of a version that has both, keeping rules, issues, history and deviations', function () {
    $fromDuplicate = ($this->implementationOf)($this->both, $this->duplicate);
    $fromKept = ($this->implementationOf)($this->both, $this->kept);
    $fromDuplicate->update(['status' => ImplementationStatus::Defective, 'deviation' => 'Taux mensuel.']);
    $fromKept->update(['deviation' => 'Arrondi au centime.']);
    $rule = BusinessRule::query()->create(['implementation_id' => $fromDuplicate->id, 'title' => 'Plafond', 'description' => 'Plafond', 'source' => RuleSource::Code]);
    $issue = Issue::query()->create(['title' => 'Arrondi', 'description' => 'Arrondi', 'type' => IssueType::Bug, 'severity' => IssueSeverity::Major]);
    $issue->implementations()->attach([$fromDuplicate->id, $fromKept->id]);

    app(MergeCatalogItems::class)->handle($this->duplicate, $this->kept);

    $merged = $fromKept->refresh();
    expect(Implementation::query()->find($fromDuplicate->id))->toBeNull()
        ->and($this->both->implementations()->where('feature_id', $this->kept->id)->count())->toBe(1)
        ->and($rule->refresh()->implementation_id)->toBe($merged->id)
        ->and($issue->implementations()->pluck('implementations.id')->all())->toBe([$merged->id])
        ->and($merged->status)->toBe(ImplementationStatus::Defective)
        ->and($merged->deviation)->toBe("Arrondi au centime.\n\n---\n\nTaux mensuel.")
        ->and($merged->statusChanges()->pluck('to_status')->map->value->all())->toContain('defective')
        ->and(DB::table('implementation_status_changes')->where('implementation_id', $merged->id)->orderByDesc('id')->value('note'))->toBe('Reprise de l’état du doublon fusionné.');
});

it('moves the sub-modules and features of a duplicate module, even when the kept one sits inside it', function () {
    $outer = app(CreateModule::class)->handle(['name' => 'Remboursements']);
    $inner = app(CreateModule::class)->handle(['name' => 'Remboursement', 'parent_id' => $outer->id]);
    $schedule = app(CreateFeature::class)->handle(['name' => 'Échéancier', 'module_id' => $outer->id]);

    app(MergeCatalogItems::class)->handle($outer, $inner);

    expect(Module::query()->find($outer->id))->toBeNull()
        ->and($inner->refresh()->parent_id)->toBeNull()
        ->and($schedule->refresh()->module_id)->toBe($inner->id);
});

it('refuses to merge a module with a feature, an item with itself, or into an archived item', function () {
    $merge = app(MergeCatalogItems::class);

    expect(fn () => $merge->handle($this->duplicate, $this->credit))->toThrow(DomainException::class, 'Un module ne peut être fusionné')
        ->and(fn () => $merge->handle($this->kept, $this->kept))->toThrow(DomainException::class)
        ->and(function () use ($merge) {
            $this->kept->archive();
            $merge->handle($this->duplicate, $this->kept);
        })->toThrow(DomainException::class, 'L’élément conservé doit être actif.');
});

it('merges from the catalog item panel', function () {
    Livewire::test(CatalogPage::class)
        ->call('select', 'feature:'.$this->duplicate->id)
        ->call('startMerge')
        ->set('mergeSearch', 'Simul')
        ->assertSee('Crédit › Simulation')
        ->set('mergeTarget', 'feature:'.$this->kept->id)
        ->assertSee('1 rattachement(s) passeront sur « Simulation »')
        ->assertSee('1 version(s) ont déjà les deux éléments')
        ->call('merge')
        ->assertHasNoErrors()
        ->assertSet('selected', 'feature:'.$this->kept->id);

    expect(Feature::query()->find($this->duplicate->id))->toBeNull();
});

it('lists close names on the duplicates page and merges them', function () {
    $other = app(CreateFeature::class)->handle(['name' => 'Rachat', 'module_id' => $this->credit->id]);

    Livewire::test(Duplicates::class)
        ->assertSee('Simulaton')
        ->assertDontSee('Rachat')
        ->call('merge', 'feature:'.$this->duplicate->id, 'feature:'.$this->kept->id)
        ->assertDispatched('notify')
        ->assertDontSee('Simulaton');

    expect(Feature::query()->find($this->duplicate->id))->toBeNull()->and($other->exists)->toBeTrue();

    $this->get(route('catalog.duplicates'))->assertOk()->assertSee('Doublons du catalogue');
});
