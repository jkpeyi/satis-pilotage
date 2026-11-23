<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Remediation\SaveRemediationAction;
use App\Actions\Remediation\SaveWorkPackage;
use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\WorkPackageStatus;
use App\Livewire\Remediation\Index;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Product;
use App\Models\RemediationAction;
use App\Models\User;
use App\Models\Version;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->product = $this->version->product;
    $feature = Feature::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->version->id], [], [$feature->id]);
    $this->issue = app(SaveIssue::class)->handle([
        'title' => 'Injection SQL', 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Security,
        'severity' => IssueSeverity::Blocking, 'status' => IssueStatus::Qualified, 'proposed_solution' => null,
        'effort_min_days' => null, 'effort_max_days' => null, 'confidence' => null, 'owner_id' => null,
        'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => 'https://gitlab.example.test/satis/api/-/issues/7',
    ], null, [$this->version->implementations()->where('feature_id', $feature->id)->sole()->id]);

    $this->save = fn (array $data = [], ?RemediationAction $action = null, array $dependencies = []): RemediationAction => app(SaveRemediationAction::class)->handle([
        'product_id' => $this->product->id, 'work_package_id' => null, 'issue_id' => $this->issue->id, 'title' => 'Requêtes préparées',
        'description' => null, 'priority' => ActionPriority::P1, 'status' => ActionStatus::Todo, 'owner_id' => null,
        'starts_on' => null, 'due_on' => null, 'effort_days' => '3', 'acceptance_criteria' => null, 'test_evidence' => null,
        'gitlab_issue_url' => null, ...$data,
    ], $action, $dependencies);
});

it('RG-19: closes an action only with acceptance criteria and test evidence, and records the date', function () {
    $action = ($this->save)();

    expect(fn () => ($this->save)(['status' => ActionStatus::Done, 'acceptance_criteria' => 'Plus de concaténation'], $action))
        ->toThrow(DomainException::class, 'critères de réception et la preuve de test');

    $this->travelTo('2026-12-03');
    ($this->save)(['status' => ActionStatus::Done, 'acceptance_criteria' => 'Plus de concaténation', 'test_evidence' => 'https://gitlab.example.test/satis/api/-/pipelines/42'], $action);
    expect($action->refresh()->completed_on?->toDateString())->toBe('2026-12-03');

    $this->travelTo('2026-12-10');
    ($this->save)(['status' => ActionStatus::Done, 'acceptance_criteria' => 'Plus de concaténation', 'test_evidence' => 'Pipeline 43'], $action);
    expect($action->refresh()->completed_on?->toDateString())->toBe('2026-12-03');

    ($this->save)(['status' => ActionStatus::InProgress], $action);
    expect($action->refresh()->completed_on)->toBeNull();
});

it('RG-19: the database refuses a done action without completion date', function () {
    $action = ($this->save)();

    RemediationAction::query()->whereKey($action->id)->update(['status' => 'done']);
})->throws(QueryException::class);

it('refuses dependency cycles, self dependencies and dependencies on another product', function () {
    $first = ($this->save)(['title' => 'Un']);
    $second = ($this->save)(['title' => 'Deux'], null, [$first->id]);
    $third = ($this->save)(['title' => 'Trois'], null, [$second->id]);

    expect(fn () => ($this->save)(['title' => 'Un'], $first, [$third->id]))->toThrow(DomainException::class, 'cycle')
        ->and(fn () => ($this->save)(['title' => 'Un'], $first, [$first->id]))->toThrow(DomainException::class, 'elle-même');

    $foreign = RemediationAction::query()->create(['product_id' => Product::factory()->create()->id, 'title' => 'Ailleurs']);
    expect(fn () => ($this->save)(['title' => 'Quatre'], null, [$foreign->id]))->toThrow(DomainException::class, 'même produit')
        ->and($third->dependencies()->pluck('remediation_actions.id')->all())->toBe([$second->id]);
});

it('keeps the problem and the work package within the product of the action', function () {
    $otherIssue = Issue::query()->create(['title' => 'Ailleurs', 'description' => 'x', 'type' => 'bug', 'severity' => 'minor']);
    $foreignPackage = app(SaveWorkPackage::class)->handle(['product_id' => Product::factory()->create()->id, 'name' => 'Lot 1', 'objective' => null, 'starts_on' => null, 'due_on' => null, 'status' => WorkPackageStatus::Planned]);

    expect(fn () => ($this->save)(['issue_id' => $otherIssue->id]))->toThrow(DomainException::class, 'aucune version de ce produit')
        ->and(fn () => ($this->save)(['work_package_id' => $foreignPackage->id]))->toThrow(DomainException::class, 'autre produit');
});

it('plans an action from a problem, then manages it on the action plan page', function () {
    $this->get(route('issues.show', $this->issue))->assertOk()->assertSee('Planifier une action');

    $page = Livewire::withQueryParams(['product' => (string) $this->product->id, 'issue' => (string) $this->issue->id])
        ->test(Index::class)
        ->assertSet('showForm', true)
        ->assertSet('form.issue_id', (string) $this->issue->id)
        ->assertSet('form.priority', 'p1')
        ->assertSet('form.gitlab_issue_url', 'https://gitlab.example.test/satis/api/-/issues/7')
        ->set('form.owner_id', (string) $this->user->id)
        ->set('form.due_on', '2026-01-15')
        ->call('save')
        ->assertHasNoErrors();

    $action = RemediationAction::query()->sole();
    expect($action->title)->toBe('Traiter '.$this->issue->displayCode().' · Injection SQL');

    $page->call('createPackage')
        ->set('packageForm.name', 'Lot 1 — Sécurité')
        ->set('packageForm.due_on', '2026-02-28')
        ->call('savePackage')
        ->assertHasNoErrors()
        ->call('edit', $action->id)
        ->set('form.work_package_id', (string) $action->product->workPackages()->sole()->id)
        ->set('form.status', 'done')
        ->call('save')
        ->assertHasErrors(['form.acceptance_criteria', 'form.test_evidence'])
        ->set('form.status', 'in_progress')
        ->call('save')
        ->assertHasNoErrors()
        ->set('lateOnly', true)
        ->assertSee($action->displayCode())
        ->assertSee('en retard');

    $this->get(route('issues.show', $this->issue))->assertSee($action->displayCode())->assertSee('En cours');
});
