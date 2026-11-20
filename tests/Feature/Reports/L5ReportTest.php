<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Reports\GenerateReport;
use App\Enums\Confidence;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\SnippetKind;
use App\Enums\VersionStatus;
use App\Models\Component;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->feature = Feature::factory()->create(['name' => 'Échéancier']);
    $this->alpha = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Alpha']))->create();
    $this->beta = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Beta']))->create();
    $this->saas = Version::factory()->for($this->product)->create(['status' => VersionStatus::Future, 'customer_id' => null]);
    app(AttachCatalogItems::class)->handle([$this->alpha->id, $this->beta->id, $this->saas->id], [], [$this->feature->id]);
    $this->repository = Repository::factory()->for($this->alpha)->create(['path' => 'satis/api', 'web_url' => 'https://gitlab.example.test/satis/api']);
    $this->component = Component::factory()->for($this->alpha)->create(['name' => 'API métier']);

    $this->implementation = fn (Version $version) => $version->implementations()->where('feature_id', $this->feature->id)->sole();
    $this->issue = fn (array $data, array $versions): Issue => app(SaveIssue::class)->handle([
        'title' => 'Problème', 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Bug,
        'severity' => IssueSeverity::Major, 'status' => IssueStatus::Identified, 'proposed_solution' => null,
        'effort_min_days' => null, 'effort_max_days' => null, 'confidence' => null, 'owner_id' => null,
        'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null, ...$data,
    ], null, array_map(fn (Version $version): int => ($this->implementation)($version)->id, $versions));

    $this->rounding = ($this->issue)([
        'title' => 'Arrondi des échéances', 'description' => 'Montants **tronqués**.', 'impact' => 'Dernière échéance fausse.',
        'severity' => IssueSeverity::Blocking, 'effort_min_days' => '2', 'effort_max_days' => '4', 'confidence' => Confidence::Medium,
        'owner_id' => $this->user->id, 'repository_id' => $this->repository->id, 'component_id' => $this->component->id,
        'gitlab_issue_url' => 'https://gitlab.example.test/satis/api/-/issues/12', 'status' => IssueStatus::Planned,
    ], [$this->alpha, $this->beta]);
    $this->rounding->codeSnippets()->create([
        'kind' => SnippetKind::Finding, 'language' => 'php', 'content' => 'floor($amount)', 'repository_id' => $this->repository->id,
        'commit_sha' => str_repeat('a', 40), 'path' => 'app/Schedule.php', 'line_start' => 10, 'line_end' => 12,
    ]);
    ($this->issue)(['title' => 'Libellé ambigu', 'type' => IssueType::Usability, 'severity' => IssueSeverity::Minor, 'effort_min_days' => '0.5'], [$this->beta]);
    ($this->issue)(['title' => 'Écran SaaS', 'severity' => IssueSeverity::Minor], [$this->saas]);
    ($this->issue)(['title' => 'Ancien défaut', 'status' => IssueStatus::Dropped], [$this->alpha]);

    $this->generate = fn (?int $customerId = null): ReportGeneration => app(GenerateReport::class)->handle('L5', new ReportScope($this->product->id, $customerId), $this->user);
    $this->sections = fn (ReportGeneration $generation): array => json_decode($generation->snapshot, true)['sections'];
});

it('lists every issue of the scope once, with the fields of the debt register', function () {
    $rows = collect(($this->sections)(($this->generate)())['debt_items.table']['rows'])->keyBy('title');

    expect($rows->keys()->all())->toBe(['Arrondi des échéances', 'Ancien défaut', 'Libellé ambigu'])
        ->and($rows['Arrondi des échéances'])->toMatchArray([
            'debt_id' => $this->rounding->displayCode(),
            'finding_type' => 'Bug',
            'client_id' => $this->alpha->customer->displayCode()." · Banque Alpha\n".$this->beta->customer->displayCode().' · Banque Beta',
            'repository_id' => $this->repository->displayCode().' · satis/api',
            'component_id' => $this->component->displayCode().' · API métier',
            'description' => 'Montants tronqués.',
            'impact' => 'Dernière échéance fausse.',
            'severity' => 'Bloquante',
            'evidence_url' => 'https://gitlab.example.test/satis/api/-/blob/'.str_repeat('a', 40).'/app/Schedule.php#L10-12',
            'effort_min_days' => '2',
            'effort_max_days' => '4',
            'confidence' => 'Moyenne',
            'owner' => 'Awa Koné',
            'status' => 'Planifié',
            'gitlab_issue_url' => 'https://gitlab.example.test/satis/api/-/issues/12',
        ])
        ->and($rows['Libellé ambigu'])->toMatchArray(['effort_min_days' => '0,5', 'effort_max_days' => '0,5', 'owner' => null, 'evidence_url' => null]);
});

it('summarizes, estimates and points out shared issues and decisions', function () {
    $sections = ($this->sections)(($this->generate)());
    $text = fn (string $variable): string => collect($sections[$variable]['blocks'])->pluck('text')->implode("\n");

    expect($text('debt.summary'))->toContain('3 problèmes recensés dans le périmètre, dont 2 encore à traiter')
        ->toContain('Bloquante : 1')
        ->toContain('Abandonné : 1')
        ->and($text('debt.estimates'))->toContain('Effort estimé des problèmes à traiter : 2,5 à 4,5 j/h, sur 2 problèmes estimés ; 0 sans estimation.')
        ->toContain('Bloquante : 2 à 4 j/h')
        ->and($text('debt.cross_cutting'))->toContain($this->rounding->displayCode().' · Arrondi des échéances (bloquante) — ')
        ->toContain($this->repository->displayCode().' · satis/api : 1')
        ->and($text('debt.decisions'))->toContain('Problèmes planifiés :')
        ->toContain('responsable : Awa Koné')
        ->toContain('Ancien défaut');
});

it('narrows the register to a customer', function () {
    $rows = collect(($this->sections)(($this->generate)($this->beta->customer_id))['debt_items.table']['rows']);

    expect($rows->pluck('title')->all())->toBe(['Arrondi des échéances', 'Libellé ambigu'])
        ->and($rows->first()['client_id'])->toBe($this->beta->customer->displayCode().' · Banque Beta');
});

it('refuses a repository or a component outside the versions of the issue', function () {
    expect(fn () => ($this->issue)(['repository_id' => Repository::factory()->create()->id], [$this->alpha]))
        ->toThrow(DomainException::class, 'Le dépôt choisi')
        ->and(fn () => ($this->issue)(['component_id' => $this->component->id], [$this->beta]))
        ->toThrow(DomainException::class, 'Le composant choisi');
});

it('keeps an effort range consistent, down to the database', function () {
    expect(fn () => $this->rounding->update(['effort_min_days' => 5, 'effort_max_days' => 1]))->toThrow(QueryException::class)
        ->and($this->rounding->refresh()->effortLabel())->toBe('2 à 4 j/h')
        ->and(IssueStatus::openValues())->toBe(['identified', 'qualified', 'planned']);
});
