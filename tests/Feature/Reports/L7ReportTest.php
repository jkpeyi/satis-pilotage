<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Reports\GenerateReport;
use App\Actions\Strategy\ChangeStrategyApproval;
use App\Actions\Strategy\SaveRefactoringStrategy;
use App\Actions\Strategy\SaveStrategyDecision;
use App\Actions\Strategy\SaveStrategyItem;
use App\Enums\ApprovalStatus;
use App\Enums\DecisionStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\RiskLevel;
use App\Enums\VersionStatus;
use App\Livewire\Reports\Index;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\StrategyOption;
use App\Models\StrategyPhase;
use App\Models\StrategyRisk;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 10:00:00');
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $version = Version::factory()->for($this->product)->create();
    Version::factory()->for($this->product)->create(['name' => 'Satis_SaaS', 'status' => VersionStatus::Future, 'customer_id' => null]);
    $feature = Feature::factory()->create();
    app(AttachCatalogItems::class)->handle([$version->id], [], [$feature->id]);
    $this->issue = app(SaveIssue::class)->handle([
        'title' => 'Injection SQL', 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Security,
        'severity' => IssueSeverity::Blocking, 'status' => IssueStatus::Qualified, 'proposed_solution' => null,
        'effort_min_days' => null, 'effort_max_days' => null, 'confidence' => null, 'owner_id' => null,
        'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null,
    ], null, [$version->implementations()->where('feature_id', $feature->id)->sole()->id]);

    $this->strategy = app(SaveRefactoringStrategy::class)->handle([
        'product_id' => $this->product->id, 'title' => 'Convergence vers le socle', 'context' => "Trop de versions **divergentes**.\n\nObjectif : un seul socle.",
        'migration' => null, 'safety' => 'Rejouer la recette de chaque client.', 'saas_notes' => null,
    ], null, [$this->issue->id]);
    $items = app(SaveStrategyItem::class);
    $this->converge = $items->handle($this->strategy, new StrategyOption, ['name' => 'Converger', 'benefits' => 'Un seul code', 'drawbacks' => 'Migration des clients', 'effort_min_days' => '60', 'effort_max_days' => '90', 'risk_level' => RiskLevel::Medium, 'is_recommended' => true]);
    $this->rewrite = $items->handle($this->strategy, new StrategyOption, ['name' => 'Réécrire', 'benefits' => 'Code neuf']);
    $items->handle($this->strategy, new StrategyPhase, ['name' => 'Isoler le Crédit', 'prerequisites' => 'Tests de recette automatisés', 'rollback' => null]);
    $items->handle($this->strategy, new StrategyRisk, ['description' => 'Régression chez un client', 'probability' => RiskLevel::High, 'impact' => RiskLevel::High, 'mitigation' => 'Recette par client', 'owner_id' => $this->user->id]);
    $this->decision = app(SaveStrategyDecision::class)->handle($this->strategy, ['strategy_option_id' => $this->converge->id, 'title' => 'Retenir la convergence', 'rationale' => 'Moins risqué', 'status' => DecisionStatus::Accepted, 'decided_on' => null]);
    $this->pending = app(SaveStrategyDecision::class)->handle($this->strategy, ['strategy_option_id' => null, 'title' => 'Calendrier de migration', 'rationale' => null, 'status' => DecisionStatus::Proposed, 'decided_on' => null]);
    app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::InReview, $this->user);
    app(ChangeStrategyApproval::class)->handle($this->strategy, ApprovalStatus::Approved, $this->user, 'Comité DTI du 20/11.');

    $this->generate = fn (): ReportGeneration => app(GenerateReport::class)->handle('L7', new ReportScope($this->product->id, null, null, $this->strategy->id), $this->user);
});

it('lays out the strategy as written, with its approval in the control block', function () {
    $generation = ($this->generate)();
    $snapshot = json_decode($generation->snapshot, true);
    $text = fn (string $variable): string => json_encode($snapshot['sections'][$variable], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($generation->scope)->toBe(['product_id' => $this->product->id, 'customer_id' => null, 'strategy_id' => $this->strategy->id])
        ->and($snapshot['control'])->toMatchArray([
            'approver' => 'Awa Koné (Validé le 20/11/2026)',
            'approval' => 'Stratégie '.$this->strategy->displayCode().' validée par Awa Koné le 20/11/2026. Comité DTI du 20/11.',
        ])
        ->and($text('strategy.context'))->toContain('Trop de versions divergentes.')->toContain('Objectif : un seul socle.')->toContain('1 application en exploitation')
        ->and($text('strategy.baseline'))->toContain('1 problème à traiter, dont 1 bloquante')->toContain($this->issue->displayCode().' · Injection SQL (bloquante, qualifié)')
        ->and($text('strategy.options'))->toContain($this->converge->displayCode().' · Converger (recommandé)')->toContain('Inconvénients : Migration des clients')
        ->and($text('strategy.migration'))->toContain('Stratégie de migration à rédiger.')->toContain('Prérequis : Tests de recette automatisés')->toContain('Retour arrière : à confirmer')
        ->and($text('strategy.safety'))->toContain('Rejouer la recette de chaque client.')
        ->and($text('strategy.recommendation'))->toContain('Scénario recommandé : '.$this->converge->displayCode())
        ->toContain('probabilité élevée, impact élevé — maîtrise : Recette par client — responsable : Awa Koné')
        ->toContain($this->decision->displayCode().' · Retenir la convergence — acceptée (Awa Koné, 20/11/2026) — Moins risqué')
        ->and($text('strategy.saas_future'))->toContain('perspective future')->toContain('Satis_SaaS.');
});

it('compares the scenarios in a table and lists the unwritten parts as pending', function () {
    $generation = ($this->generate)();
    $snapshot = json_decode($generation->snapshot, true);
    $rows = collect($snapshot['sections']['strategy.tradeoffs']['rows'])->keyBy('name');

    expect($rows['Converger'])->toMatchArray(['effort' => '60 à 90 j/h', 'risk' => 'Moyen', 'recommended' => 'Oui', 'decision' => $this->decision->displayCode().' · acceptée'])
        ->and($rows['Réécrire'])->toMatchArray(['drawbacks' => null, 'effort' => null, 'risk' => null])
        ->and($snapshot['pending'])->toBe(['Stratégie : la stratégie de migration à rédiger'])
        ->and($generation->missing_count)->toBe(3);
});

it('requires a strategy of the product to generate L7, and shows pending decisions in L6', function () {
    Livewire::test(Index::class)
        ->set('form.code', 'L7')
        ->set('form.product_id', (string) $this->product->id)
        ->assertSee('Convergence vers le socle')
        ->call('generate')
        ->assertHasErrors(['form.strategy_id' => 'required'])
        ->set('form.strategy_id', (string) $this->strategy->id)
        ->call('generate')
        ->assertHasNoErrors();

    $l6 = app(GenerateReport::class)->handle('L6', new ReportScope($this->product->id), $this->user);
    expect(json_encode(json_decode($l6->snapshot, true)['sections']['dashboard.pending_decisions'], JSON_UNESCAPED_UNICODE))
        ->toContain($this->pending->displayCode().' · Calendrier de migration ('.$this->strategy->displayCode().') : décision en attente');
});
