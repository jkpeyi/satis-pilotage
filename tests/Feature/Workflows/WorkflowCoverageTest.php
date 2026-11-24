<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Workflows\SaveWorkflowStep;
use App\Enums\ImplementationStatus;
use App\Enums\VersionStatus;
use App\Enums\WorkflowStepKind;
use App\Livewire\Workflows\Show;
use App\Models\Feature;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Queries\WorkflowCoverage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->workflow = Workflow::factory()->create(['name' => 'Octroi d’un crédit']);
    $product = $this->workflow->product;
    $this->credit = Module::factory()->create(['name' => 'Crédit']);
    $this->file = Feature::factory()->for($this->credit)->create(['name' => 'Montage du dossier']);
    $this->committee = Feature::factory()->for($this->credit)->create(['name' => 'Comité de crédit']);
    $this->alpha = Version::factory()->for($product)->create(['name' => 'Banque Alpha']);
    $this->beta = Version::factory()->for($product)->create(['name' => 'Banque Beta']);
    $this->saas = Version::factory()->for($product)->create(['name' => 'Satis_SaaS', 'status' => VersionStatus::Future, 'customer_id' => null]);
    app(AttachCatalogItems::class)->handle([$this->alpha->id], [], [$this->file->id, $this->committee->id]);
    app(AttachCatalogItems::class)->handle([$this->beta->id], [], [$this->file->id]);
    $this->alpha->implementations()->where('feature_id', $this->file->id)->update(['status' => ImplementationStatus::Operational]);
    $this->alpha->implementations()->where('feature_id', $this->committee->id)->update(['status' => ImplementationStatus::Partial]);
    $this->beta->implementations()->where('feature_id', $this->file->id)->update(['status' => ImplementationStatus::Defective]);

    $save = fn (string $name, array $data = []): WorkflowStep => app(SaveWorkflowStep::class)->handle([
        'workflow_id' => $this->workflow->id, 'kind' => WorkflowStepKind::Action, 'name' => $name, 'actor' => null,
        'feature_id' => null, 'description' => null, 'business_rule_ids' => [], 'transitions' => [], ...$data,
    ]);
    $this->stepFile = $save('Constituer le dossier', ['feature_id' => $this->file->id]);
    $this->stepCommittee = $save('Passer en comité', ['feature_id' => $this->committee->id]);
    $this->stepNotify = $save('Notifier le client');
    $this->stepEnd = $save('Fin', ['kind' => WorkflowStepKind::End]);
});

it('gives each step the status of its feature in each version, without any entry per version', function () {
    $coverage = WorkflowCoverage::for($this->workflow);

    expect($coverage->versions->pluck('name')->all())->toBe(['Banque Alpha', 'Banque Beta', 'Satis_SaaS'])
        ->and($coverage->cell($this->stepFile, $this->alpha))->toBe(ImplementationStatus::Operational)
        ->and($coverage->cell($this->stepCommittee, $this->alpha))->toBe(ImplementationStatus::Partial)
        ->and($coverage->cell($this->stepFile, $this->beta))->toBe(ImplementationStatus::Defective)
        ->and($coverage->cell($this->stepCommittee, $this->beta))->toBe(WorkflowCoverage::NOT_ATTACHED)
        ->and($coverage->cell($this->stepNotify, $this->beta))->toBe(WorkflowCoverage::UNDEFINED)
        ->and($coverage->cell($this->stepEnd, $this->beta))->toBe(WorkflowCoverage::NOT_APPLICABLE)
        ->and($coverage->summary($this->alpha))->toBe(['steps' => 3, 'operational' => 1, 'blocking' => 0, 'other' => 1, 'undefined' => 1])
        ->and($coverage->summary($this->beta))->toBe(['steps' => 3, 'operational' => 0, 'blocking' => 2, 'other' => 0, 'undefined' => 1])
        ->and($coverage->nodeColors($this->beta))->toBe([
            $this->stepFile->id => 'red', $this->stepCommittee->id => 'red', $this->stepNotify->id => 'gray', $this->stepEnd->id => 'none',
        ]);
});

it('shows the coverage table and colors the diagram for a chosen version', function () {
    Livewire::test(Show::class, ['workflow' => $this->workflow])
        ->assertSee('Couverture par version')
        ->assertSee('Non rattachée')
        ->assertSee('2 bloquantes')
        ->assertSee('(perspective)')
        ->assertDontSeeHtml('fill="#fef2f2"')
        ->set('coverageVersion', (string) $this->beta->id)
        ->assertSeeHtml('fill="#fef2f2"')
        ->assertSee('non rattachée, absente ou défaillante');
});

it('RG-15: finds the step feature in the catalog, or creates it there and tags it with the product', function () {
    Feature::factory()->for(Module::factory()->create(['name' => 'Épargne']))->create(['name' => 'Ouverture de compte']);

    $page = Livewire::test(Show::class, ['workflow' => $this->workflow])
        ->call('createStep')
        ->set('stepForm.name', 'Décaisser')
        ->set('featureSearch', 'comite')
        ->assertSee('Comité de crédit')
        ->call('chooseFeature', $this->committee->id)
        ->assertSet('stepForm.feature_id', (string) $this->committee->id)
        ->assertSet('featureSearch', '')
        ->assertSee('Banque Alpha : partiel')
        ->assertSee('Banque Beta : non rattachée')
        ->call('clearFeature')
        ->set('featureSearch', 'Ouverture de compte')
        ->assertSee('hors produit')
        ->assertDontSee('Créer « Ouverture de compte »')
        ->set('featureSearch', 'Décaissement')
        ->assertSee('Créer « Décaissement » dans')
        ->call('createFeature')
        ->assertHasErrors(['newFeatureModuleId'])
        ->set('newFeatureModuleId', (string) $this->credit->id)
        ->call('createFeature')
        ->assertHasNoErrors();

    $created = Feature::query()->where('name', 'Décaissement')->sole();
    expect($created->module_id)->toBe($this->credit->id)
        ->and($created->products()->pluck('products.id')->all())->toBe([$this->workflow->product_id]);

    $page->assertSet('stepForm.feature_id', (string) $created->id)
        ->call('saveStep')
        ->assertHasNoErrors();

    expect(WorkflowStep::query()->where('name', 'Décaisser')->sole()->feature_id)->toBe($created->id);

    Livewire::test(Show::class, ['workflow' => $this->workflow])
        ->call('createStep')
        ->set('featureSearch', 'Montage du dossier')
        ->set('newFeatureModuleId', (string) $this->credit->id)
        ->call('createFeature')
        ->assertHasErrors(['featureSearch']);
});
