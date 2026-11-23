<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Strategy\ArchiveStrategyItem;
use App\Actions\Strategy\ChangeStrategyApproval;
use App\Actions\Strategy\SaveRefactoringStrategy;
use App\Actions\Strategy\SaveStrategyDecision;
use App\Actions\Strategy\SaveStrategyItem;
use App\Enums\ApprovalStatus;
use App\Enums\DecisionStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Livewire\Strategies\Index;
use App\Livewire\Strategies\Show;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\RefactoringStrategy;
use App\Models\StrategyOption;
use App\Models\StrategyRisk;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $feature = Feature::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->version->id], [], [$feature->id]);
    $this->issue = app(SaveIssue::class)->handle([
        'title' => 'Injection SQL', 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Security,
        'severity' => IssueSeverity::Blocking, 'status' => IssueStatus::Qualified, 'proposed_solution' => null,
        'effort_min_days' => null, 'effort_max_days' => null, 'confidence' => null, 'owner_id' => null,
        'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null,
    ], null, [$this->version->implementations()->where('feature_id', $feature->id)->sole()->id]);

    $this->data = ['product_id' => $this->version->product_id, 'title' => 'Convergence vers le socle', 'context' => 'Trop de versions divergentes.', 'migration' => null, 'safety' => null, 'saas_notes' => null];
    $this->strategy = app(SaveRefactoringStrategy::class)->handle($this->data, null, [$this->issue->id]);
    $this->option = app(SaveStrategyItem::class)->handle($this->strategy, new StrategyOption, ['name' => 'Converger', 'is_recommended' => true]);
    $this->approve = function (): void {
        app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::InReview, $this->user);
        app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::Approved, $this->user, 'Validé en comité DTI.');
    };
});

it('runs the approval circuit and records who approved and when', function () {
    expect(fn () => app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::Approved, $this->user))
        ->toThrow(DomainException::class, 'Passage impossible');

    ($this->approve)();

    expect($this->strategy->refresh())
        ->approval_status->toBe(ApprovalStatus::Approved)
        ->approved_by->toBe($this->user->id)
        ->approval_note->toBe('Validé en comité DTI.')
        ->and($this->strategy->approved_at)->not->toBeNull();
});

it('requires a context and a scenario before review, and a reason to reject', function () {
    $empty = app(SaveRefactoringStrategy::class)->handle([...$this->data, 'context' => null]);

    expect(fn () => app(ChangeStrategyApproval::class)->handle($empty, ApprovalStatus::InReview, $this->user))
        ->toThrow(DomainException::class, 'contexte et au moins un scénario');

    app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::InReview, $this->user);
    expect(fn () => app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::Rejected, $this->user, ' '))
        ->toThrow(DomainException::class, 'pourquoi');
});

it('RG-20: changing the content of an approved strategy brings it back to draft', function (Closure $change) {
    ($this->approve)();

    $change->call($this);

    expect($this->strategy->refresh())
        ->approval_status->toBe(ApprovalStatus::Draft)
        ->approved_by->toBeNull()
        ->approved_at->toBeNull();
})->with([
    'context' => [fn () => app(SaveRefactoringStrategy::class)->handle([...$this->data, 'context' => 'Autre contexte'], $this->strategy, [$this->issue->id])],
    'findings' => [fn () => app(SaveRefactoringStrategy::class)->handle($this->data, $this->strategy, [])],
    'new scenario' => [fn () => app(SaveStrategyItem::class)->handle($this->strategy, new StrategyOption, ['name' => 'Réécrire'])],
    'risk' => [fn () => app(SaveStrategyItem::class)->handle($this->strategy, new StrategyRisk, ['description' => 'Régression'])],
    'archived scenario' => [fn () => app(ArchiveStrategyItem::class)->handle($this->option)],
]);

it('RG-20: saving an approved strategy unchanged, or recording a decision, keeps its approval', function () {
    ($this->approve)();

    app(SaveRefactoringStrategy::class)->handle($this->data, $this->strategy, [$this->issue->id]);
    app(SaveStrategyDecision::class)->handle($this->strategy, ['strategy_option_id' => $this->option->id, 'title' => 'Converger', 'rationale' => 'Moins risqué', 'status' => DecisionStatus::Accepted, 'decided_on' => null]);

    expect($this->strategy->refresh()->approval_status)->toBe(ApprovalStatus::Approved);
});

it('records who took a decision, requires its rationale and keeps its scenario in the strategy', function () {
    $other = app(SaveRefactoringStrategy::class)->handle($this->data);
    $foreign = app(SaveStrategyItem::class)->handle($other, new StrategyOption, ['name' => 'Ailleurs']);
    $save = fn (array $data, $decision = null) => app(SaveStrategyDecision::class)->handle($this->strategy, ['strategy_option_id' => null, 'title' => 'Converger', 'rationale' => null, 'status' => DecisionStatus::Proposed, 'decided_on' => null, ...$data], $decision);

    $decision = $save([]);
    expect($decision->decided_by)->toBeNull()
        ->and(fn () => $save(['status' => DecisionStatus::Accepted], $decision))->toThrow(DomainException::class, 'justification')
        ->and(fn () => $save(['strategy_option_id' => $foreign->id]))->toThrow(DomainException::class, 'autre stratégie');

    $save(['status' => DecisionStatus::Accepted, 'rationale' => 'Moins risqué', 'strategy_option_id' => $this->option->id], $decision);
    expect($decision->refresh())->decided_by->toBe($this->user->id)->decided_on->toDateString()->toBe(today()->toDateString());
});

it('keeps a single recommended scenario and findings of the product', function () {
    app(SaveStrategyItem::class)->handle($this->strategy, new StrategyOption, ['name' => 'Réécrire', 'is_recommended' => true]);
    $foreignIssue = Issue::query()->create(['title' => 'Ailleurs', 'description' => 'x', 'type' => 'bug', 'severity' => 'minor']);

    expect($this->strategy->options()->where('is_recommended', true)->pluck('name')->all())->toBe(['Réécrire'])
        ->and(fn () => app(SaveRefactoringStrategy::class)->handle($this->data, $this->strategy, [$foreignIssue->id]))->toThrow(DomainException::class, 'problèmes de ce produit');
});

it('writes, submits and approves a strategy from its pages', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('form.product_id', (string) $this->version->product_id)
        ->set('form.title', 'Réécriture du Crédit')
        ->set('form.context', 'Module Crédit à réécrire.')
        ->call('save')
        ->assertHasNoErrors();

    $strategy = RefactoringStrategy::query()->where('title', 'Réécriture du Crédit')->sole();

    Livewire::test(Show::class, ['strategy' => $strategy])
        ->call('changeApproval', 'in_review')
        ->assertDispatched('notify', message: 'Avant la revue, décrivez le contexte et au moins un scénario.')
        ->call('createItem', 'option')
        ->set('itemForm.name', 'Réécrire en Laravel 12')
        ->set('itemForm.effort_min_days', '40')
        ->call('saveItem')
        ->assertHasNoErrors()
        ->call('createItem', 'decision')
        ->set('itemForm.name', 'Lancer la réécriture')
        ->call('saveItem')
        ->call('changeApproval', 'in_review')
        ->call('changeApproval', 'rejected')
        ->assertSet('showReject', true)
        ->set('approvalNote', 'Chiffrage à revoir.')
        ->call('changeApproval', 'rejected')
        ->assertSee('Chiffrage à revoir.');

    expect($strategy->refresh()->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($strategy->options()->sole()->effort_max_days)->toBe('40.0');

    $this->get(route('strategies.index'))->assertOk()->assertSee('Réécriture du Crédit')->assertSee('Rejeté');
});
