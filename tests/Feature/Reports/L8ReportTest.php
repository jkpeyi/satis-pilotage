<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Remediation\SaveRemediationAction;
use App\Actions\Remediation\SaveWorkPackage;
use App\Actions\Reports\GenerateReport;
use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\WorkPackageStatus;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Product;
use App\Models\RemediationAction;
use App\Models\ReportGeneration;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 10:00:00');
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->alpha = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Alpha']))->create();
    $this->beta = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Beta']))->create();
    $feature = Feature::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->alpha->id, $this->beta->id], [], [$feature->id]);
    $implementation = fn (Version $version): int => $version->implementations()->where('feature_id', $feature->id)->sole()->id;
    $issue = fn (string $title, IssueSeverity $severity, Version $version): Issue => app(SaveIssue::class)->handle([
        'title' => $title, 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Security, 'severity' => $severity,
        'status' => IssueStatus::Qualified, 'proposed_solution' => null, 'effort_min_days' => null, 'effort_max_days' => null,
        'confidence' => null, 'owner_id' => null, 'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null,
    ], null, [$implementation($version)]);
    $this->sql = $issue('Injection SQL', IssueSeverity::Blocking, $this->alpha);
    $this->xss = $issue('XSS', IssueSeverity::Blocking, $this->beta);
    $this->minor = $issue('Libellé', IssueSeverity::Minor, $this->alpha);

    $this->package = app(SaveWorkPackage::class)->handle([
        'product_id' => $this->product->id, 'name' => 'Lot 1 — Sécurité', 'objective' => 'Fermer les failles bloquantes.',
        'starts_on' => '2026-11-01', 'due_on' => '2026-12-31', 'status' => WorkPackageStatus::InProgress,
    ]);
    $save = fn (array $data, array $dependencies = []): RemediationAction => app(SaveRemediationAction::class)->handle([
        'product_id' => $this->product->id, 'work_package_id' => $this->package->id, 'issue_id' => null, 'title' => 'Action',
        'description' => null, 'priority' => ActionPriority::P2, 'status' => ActionStatus::Todo, 'owner_id' => null,
        'starts_on' => null, 'due_on' => null, 'effort_days' => null, 'acceptance_criteria' => null, 'test_evidence' => null,
        'gitlab_issue_url' => null, ...$data,
    ], null, $dependencies);
    $this->audit = $save(['title' => 'Revue des requêtes', 'issue_id' => $this->sql->id, 'status' => ActionStatus::Done, 'owner_id' => $this->user->id,
        'starts_on' => '2026-11-02', 'due_on' => '2026-11-10', 'effort_days' => '2', 'acceptance_criteria' => 'Liste des requêtes à corriger', 'test_evidence' => 'PV de revue du 10/11']);
    $this->fix = $save(['title' => 'Requêtes préparées', 'issue_id' => $this->sql->id, 'priority' => ActionPriority::P1, 'status' => ActionStatus::InProgress,
        'starts_on' => '2026-11-11', 'due_on' => '2026-11-15', 'effort_days' => '5', 'acceptance_criteria' => 'Aucune concaténation'], [$this->audit->id]);
    $this->tests = $save(['title' => 'Tests d’injection', 'issue_id' => $this->sql->id, 'due_on' => '2026-12-20', 'effort_days' => '1.5'], [$this->fix->id]);
    $save(['title' => 'Abandonnée', 'issue_id' => $this->minor->id, 'status' => ActionStatus::Cancelled]);

    $this->generate = fn (?int $customerId = null): ReportGeneration => app(GenerateReport::class)->handle('L8', new ReportScope($this->product->id, $customerId), $this->user);
    $this->sections = fn (ReportGeneration $generation): array => json_decode($generation->snapshot, true)['sections'];
});

it('lists the actions with the fields of the roadmap, and the work packages with their milestone', function () {
    $sections = ($this->sections)(($this->generate)());
    $actions = collect($sections['actions.table']['rows'])->keyBy('title');

    // By milestone of the work package, then priority, then due date.
    expect($actions->keys()->all())->toBe(['Requêtes préparées', 'Revue des requêtes', 'Tests d’injection', 'Abandonnée'])
        ->and($actions['Requêtes préparées'])->toMatchArray([
            'action_id' => $this->fix->displayCode(),
            'debt_id' => $this->sql->displayCode(),
            'work_package' => $this->package->displayCode(),
            'priority' => 'P1 — critique',
            'owner' => null,
            'due_date' => '15/11/2026 (en retard)',
            'effort_days' => '5',
            'dependencies' => $this->audit->displayCode(),
            'status' => 'En cours',
        ])
        ->and($actions['Revue des requêtes']['test_evidence'])->toBe('PV de revue du 10/11')
        ->and($sections['work_packages.table']['rows'][0])->toMatchArray([
            'package_id' => $this->package->displayCode(), 'milestone' => '31/12/2026', 'status' => 'En cours', 'progress' => '1 / 3', 'effort' => '8,5',
        ]);
});

it('reports coverage, dependencies, charge, acceptance and risks from the plan', function () {
    $sections = ($this->sections)(($this->generate)());
    $text = fn (string $variable): string => json_encode($sections[$variable], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($text('roadmap.objectives'))->toContain('3 actions au plan, dont 2 à mener et 1 terminée')
        ->toContain('Problèmes à traiter couverts par au moins une action : 1 / 3 (33 %)')->toContain($this->xss->displayCode().' · XSS')
        ->and($text('roadmap.dependencies'))->toContain($this->tests->displayCode().' dépend de '.$this->fix->displayCode().' (en cours)')
        ->toContain('Responsable à désigner : 2 actions, 6,5 j/h')
        ->and($text('roadmap.capacity'))->toContain('Charge restante : 6,5 j/h sur 2 actions estimées')->toContain('novembre 2026 : 5 j/h (1 action)')
        ->toContain('Lot 1 — Sécurité — 31/12/2026')->toContain('capacité de l’équipe')
        ->and($text('roadmap.acceptance'))->toContain('1 action à mener sans critères de réception : '.$this->tests->displayCode())
        ->toContain('terminée le 20/11/2026 — PV de revue du 10/11')
        ->and($text('roadmap.risks'))->toContain('1 action en retard : '.$this->fix->displayCode())
        ->toContain('1 action critique sans responsable')->toContain('1 problème bloquant sans action planifiée');
});

it('restricts the roadmap to the actions treating the problems of a customer', function () {
    $sections = ($this->sections)(($this->generate)($this->beta->customer_id));

    expect($sections['actions.table']['rows'])->toBe([])
        ->and($sections['work_packages.table']['rows'])->toBe([])
        ->and(json_encode($sections['roadmap.objectives'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->toContain('0 / 1 (0 %)');
});

it('fills the action indicators of L6 from the plan', function () {
    $generation = app(GenerateReport::class)->handle('L6', new ReportScope($this->product->id), $this->user);
    $rows = collect(json_decode($generation->snapshot, true)['sections']['dashboard.executive_summary']['rows'])->keyBy('indicator');

    expect($rows['Actions ouvertes'])->toMatchArray(['value' => '2', 'basis' => 'à faire, en cours ou bloquées ; 1 en retard'])
        ->and($rows['Actions fermées']['value'])->toBe('1');
});
