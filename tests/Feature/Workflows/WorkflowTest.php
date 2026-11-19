<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Workflows\ArchiveWorkflowStep;
use App\Actions\Workflows\MoveWorkflowStep;
use App\Actions\Workflows\SaveWorkflowStep;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Livewire\BusinessRules;
use App\Livewire\Workflows;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->credit = Module::factory()->create(['name' => 'Crédit']);
    $this->simulation = Feature::factory()->for($this->credit)->create(['name' => 'Simulation']);
    $this->committee = Feature::factory()->for($this->credit)->create(['name' => 'Comité']);
    $this->version = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->version->id], [$this->credit->id]);
    $this->workflow = Workflow::factory()->for($this->version->product)->create(['name' => 'Octroi de crédit']);

    $this->ruleOn = fn (int $versionId, ?Feature $feature, ?Module $module = null, string $title = 'Règle'): BusinessRule => BusinessRule::query()->create([
        'implementation_id' => Version::query()->findOrFail($versionId)->implementations()
            ->where($feature !== null ? 'feature_id' : 'module_id', $feature->id ?? $module?->id)->sole()->id,
        'title' => $title,
        'description' => 'Description',
        'source' => RuleSource::Interview,
        'status' => RuleStatus::Pending,
    ]);
    $this->step = fn (array $data = []): WorkflowStep => app(SaveWorkflowStep::class)->handle([
        'workflow_id' => $this->workflow->id, 'name' => 'Étape', 'actor' => null, 'feature_id' => null,
        'description' => null, 'business_rule_ids' => [], ...$data,
    ]);
});

it('creates a workflow from the list and refuses a duplicate name in the same product', function () {
    Livewire::test(Workflows\Index::class)
        ->call('create')
        ->set('form.product_id', (string) $this->version->product_id)
        ->set('form.name', 'Traitement des réclamations')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('workflows.show', Workflow::query()->where('name', 'Traitement des réclamations')->sole()));

    Livewire::test(Workflows\Index::class)
        ->call('create')
        ->set('form.product_id', (string) $this->version->product_id)
        ->set('form.name', 'Octroi de crédit')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique']);
});

it('appends steps in order and links the rules that apply to them', function () {
    $rule = ($this->ruleOn)($this->version->id, $this->simulation);
    $moduleRule = ($this->ruleOn)($this->version->id, null, $this->credit, 'Règle du module');

    $first = ($this->step)(['name' => 'Simulation', 'feature_id' => $this->simulation->id, 'business_rule_ids' => [$rule->id, $moduleRule->id]]);
    $second = ($this->step)(['name' => 'Décision', 'feature_id' => $this->committee->id, 'business_rule_ids' => [$moduleRule->id]]);

    expect([$first->position, $second->position])->toBe([1, 2])
        ->and($first->displayCode())->toBe($this->workflow->displayCode().' · étape 1')
        ->and($first->businessRules()->pluck('business_rules.id')->sort()->values()->all())->toBe([$rule->id, $moduleRule->id])
        ->and($moduleRule->workflowSteps()->count())->toBe(2);
});

it('refuses a rule from a version of another product', function () {
    $other = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$other->id], [], [$this->simulation->id]);
    $foreign = ($this->ruleOn)($other->id, $this->simulation);

    ($this->step)(['feature_id' => $this->simulation->id, 'business_rule_ids' => [$foreign->id]]);
})->throws(DomainException::class, 'Une règle liée n’appartient pas à une version de ce produit.');

it('reorders steps and renumbers them when one is archived or restored', function () {
    [$a, $b, $c] = [($this->step)(['name' => 'A']), ($this->step)(['name' => 'B']), ($this->step)(['name' => 'C'])];

    app(MoveWorkflowStep::class)->handle($c, -1);
    expect($this->workflow->steps()->pluck('name')->all())->toBe(['A', 'C', 'B']);

    app(MoveWorkflowStep::class)->handle($a, -1);
    expect($this->workflow->steps()->pluck('name')->all())->toBe(['A', 'C', 'B']);

    app(ArchiveWorkflowStep::class)->handle($a->refresh());
    expect($this->workflow->steps()->active()->pluck('position', 'name')->all())->toBe(['C' => 1, 'B' => 2]);

    app(ArchiveWorkflowStep::class)->handle($a->refresh(), archive: false);
    expect($this->workflow->steps()->active()->pluck('position', 'name')->all())->toBe(['C' => 1, 'B' => 2, 'A' => 3]);
});

it('offers the rules of the chosen feature and its modules on the workflow page', function () {
    $rule = ($this->ruleOn)($this->version->id, $this->simulation, null, 'Taux plafond');
    ($this->ruleOn)($this->version->id, $this->committee, null, 'Quorum du comité');

    Livewire::test(Workflows\Show::class, ['workflow' => $this->workflow])
        ->call('createStep')
        ->set('stepForm.name', 'Simulation de l’offre')
        ->set('stepForm.actor', 'Conseiller')
        ->set('stepForm.feature_id', (string) $this->simulation->id)
        ->assertSee('Taux plafond')
        ->assertDontSee('Quorum du comité')
        ->set('stepForm.business_rule_ids', [(string) $rule->id])
        ->call('saveStep')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSee('Simulation de l’offre')
        ->assertSee($rule->displayCode());

    $step = $this->workflow->steps()->sole();
    expect($step->feature_id)->toBe($this->simulation->id)->and($step->businessRules()->sole()->id)->toBe($rule->id);

    Livewire::test(Workflows\Show::class, ['workflow' => $this->workflow])
        ->call('editStep', $step->id)
        ->set('stepForm.feature_id', (string) $this->committee->id)
        ->assertSet('stepForm.business_rule_ids', []);

    Livewire::test(BusinessRules\Show::class, ['rule' => $rule])
        ->assertSee('Octroi de crédit')
        ->assertSee('étape 1 : Simulation de l’offre');
});

it('renders the workflow pages', function () {
    ($this->step)(['name' => 'Simulation', 'feature_id' => $this->simulation->id]);

    $this->get(route('workflows.index'))->assertOk()->assertSee('Octroi de crédit');
    $this->get(route('workflows.show', $this->workflow))->assertOk()->assertSee('Crédit › Simulation');
});
