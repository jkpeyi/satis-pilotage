<?php

use App\Actions\Audit\SaveAuditPeriod;
use App\Actions\Diagnostics\ImportToolResults;
use App\Actions\Diagnostics\SaveDiagnosticCampaign;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Reports\GenerateReport;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ToolResultFormat;
use App\Enums\VersionStatus;
use App\Livewire\Audit\Index;
use App\Models\AuditPeriod;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->alpha = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Alpha']))->create(['name' => 'Macro Alpha']);
    $this->beta = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Beta']))->create(['name' => 'Macro Beta']);
    Version::factory()->for($this->product)->create(['status' => VersionStatus::Future, 'customer_id' => null]);
    $features = Feature::factory()->count(2)->create();
    app(AttachCatalogItems::class)->handle([$this->alpha->id, $this->beta->id], [], $features->pluck('id')->all());
    $this->alpha->implementations()->whereNotNull('feature_id')->first()->update(['status' => ImplementationStatus::Operational]);
    $this->alpha->implementations()->whereNotNull('feature_id')->orderByDesc('id')->first()->update(['status' => ImplementationStatus::Absent]);

    $analyzed = Repository::factory()->for($this->alpha)->create();
    Repository::factory()->for($this->alpha)->create();
    Repository::factory()->for($this->beta)->create();
    $campaign = app(SaveDiagnosticCampaign::class)->handle(['repository_id' => $analyzed->id, 'commit_sha' => str_repeat('a', 40), 'title' => 'Audit', 'analyzed_on' => null, 'objective' => null, 'limitations' => null]);
    app(ImportToolResults::class)->handle($campaign, ToolResultFormat::Eslint, json_encode([['filePath' => 'a.js', 'messages' => [['ruleId' => 'x', 'severity' => 2, 'message' => 'm', 'line' => 1]]]]), 'eslint.json', ['tool_name' => null, 'tool_version' => null, 'scanned_on' => '2026-11-10', 'path_prefix' => null]);

    $implementation = $this->alpha->implementations()->whereNotNull('feature_id')->first()->id;
    $issue = fn (IssueSeverity $severity, IssueStatus $status, ?string $effort = null) => app(SaveIssue::class)->handle([
        'title' => 'Problème', 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Bug, 'severity' => $severity, 'status' => $status,
        'proposed_solution' => null, 'effort_min_days' => $effort, 'effort_max_days' => $effort, 'confidence' => null, 'owner_id' => null,
        'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null,
    ], null, [$implementation]);
    $this->blocking = $issue(IssueSeverity::Blocking, IssueStatus::Qualified, '3');
    $issue(IssueSeverity::Minor, IssueStatus::Treated);
    $issue(IssueSeverity::Blocking, IssueStatus::Treated);

    $period = fn (string $start, string $end, string $planned, ?string $actual, ?string $support = null) => app(SaveAuditPeriod::class)->handle([
        'product_id' => $this->product->id, 'starts_on' => $start, 'ends_on' => $end, 'hours_planned' => $planned, 'hours_actual' => $actual,
        'support_hours' => $support, 'support_interruptions' => $support !== null ? 3 : null, 'notes' => null,
    ]);
    $period('2026-11-02', '2026-11-06', '30', '24', '6');
    $period('2026-11-09', '2026-11-13', '30', null);
    $period('2026-11-30', '2026-12-04', '30', null);

    $this->generate = fn (?int $customerId = null): ReportGeneration => app(GenerateReport::class)->handle('L6', new ReportScope($this->product->id, $customerId), $this->user);
    $this->sections = fn (ReportGeneration $generation): array => json_decode($generation->snapshot, true)['sections'];
});

it('shows each indicator with its denominator and leaves actions to confirm', function () {
    $generation = ($this->generate)();
    $rows = collect(($this->sections)($generation)['dashboard.executive_summary']['rows'])->keyBy('indicator');

    expect($rows['Date de la photographie']['value'])->toBe('20/11/2026')
        ->and($rows['Applications du périmètre'])->toMatchArray(['value' => '2', 'basis' => 'hors 1 perspective future (ex. Satis_SaaS)'])
        ->and($rows['Dépôts inventoriés']['value'])->toBe('3')
        ->and($rows['Dépôts analysés']['value'])->toBe('1 / 3 (33 %)')
        ->and($rows['Fonctionnalités évaluées']['value'])->toBe('2 / 4 (50 %)')
        ->and($rows['Constats critiques à traiter'])->toMatchArray(['value' => '1', 'basis' => 'problèmes bloquants, sur 1 problème à traiter et 3 recensés'])
        ->and($rows['Résultats d’outils à vérifier']['value'])->toBe('1')
        ->and($rows['Actions ouvertes']['value'])->toBeNull()
        ->and($rows['Actions fermées']['value'])->toBeNull()
        ->and($rows['Heures d’audit prévues'])->toMatchArray(['value' => '60 h', 'basis' => '2 périodes commencées à cette date'])
        ->and($rows['Heures d’audit réalisées'])->toMatchArray(['value' => '24 h', 'basis' => 'pour 30 h prévues sur les mêmes périodes ; 1 période sans réalisé'])
        ->and($generation->missing_count)->toBe(2);
});

it('summarizes coverage, risks, capacity and the points to arbitrate', function () {
    $sections = ($this->sections)(($this->generate)());
    $text = fn (string $variable): string => json_encode($sections[$variable], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($text('dashboard.coverage'))->toContain('Macro Alpha — dépôts 1 / 2 (50 %) — fonctionnalités 2 / 2 (100 %)')->toContain('Macro Beta — dépôts 0 / 1 (0 %)')
        ->and($text('dashboard.risks'))->toContain('Bloquante : 1 — effort estimé 3 j/h')->toContain('1 élément attendu mais absent')
        ->and($text('dashboard.actions'))->toContain('Problèmes traités ou vérifiés : 2 / 3 (67 %)')
        ->and($text('dashboard.capacity'))->toContain('Réalisé : 24 h pour 30 h prévues sur les périodes renseignées (80 %)')->toContain('Interruptions de support : 6 h en 3 interruptions')
        ->and($text('dashboard.pending_decisions'))->toContain('1 problème qualifié à planifier ou abandonner : '.$this->blocking->displayCode())
        ->toContain('1 problème bloquant sans responsable')->toContain('1 résultat d’outils à vérifier')
        ->and($text('dashboard.methodology'))->toContain('Photographie au 20/11/2026');
});

it('restricts the dashboard to a customer and says hours are not split by customer', function () {
    $sections = ($this->sections)(($this->generate)($this->beta->customer_id));
    $rows = collect($sections['dashboard.executive_summary']['rows'])->keyBy('indicator');

    expect($rows['Applications du périmètre']['value'])->toBe('1')
        ->and($rows['Constats critiques à traiter']['value'])->toBe('0')
        ->and(json_encode($sections['dashboard.capacity'], JSON_UNESCAPED_UNICODE))->toContain('ne sont pas filtrées par client');
});

it('refuses overlapping audit periods and inconsistent dates', function () {
    expect(fn () => app(SaveAuditPeriod::class)->handle([
        'product_id' => $this->product->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-10', 'hours_planned' => '10',
        'hours_actual' => null, 'support_hours' => null, 'support_interruptions' => null, 'notes' => null,
    ]))->toThrow(DomainException::class, 'chevauche')
        ->and(fn () => AuditPeriod::query()->create(['product_id' => $this->product->id, 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-01', 'hours_planned' => 1]))
        ->toThrow(QueryException::class);
});

it('records and edits a period from the audit follow-up page', function () {
    $this->get(route('audit.index'))->assertOk()->assertSee('Suivi de l’audit')->assertSee('À saisir');

    Livewire::test(Index::class)
        ->set('product', (string) $this->product->id)
        ->call('create')
        ->assertSet('form.starts_on', '2026-12-05')
        ->set('form.hours_planned', '12,5')
        ->call('save')
        ->assertHasNoErrors();

    $period = AuditPeriod::query()->latest('id')->first();
    expect($period->hours_planned)->toBe('12.5');

    Livewire::test(Index::class)
        ->call('edit', $period->id)
        ->assertSet('form.hours_planned', '12.5')
        ->set('form.hours_actual', '10')
        ->call('save')
        ->assertHasNoErrors();

    expect($period->refresh()->hours_actual)->toBe('10.0');
});
