<?php

use App\Actions\Diagnostics\ImportToolResults;
use App\Actions\Diagnostics\RejectFindings;
use App\Actions\Diagnostics\RetainFindingsInNewIssue;
use App\Actions\Diagnostics\SaveDiagnosticCampaign;
use App\Actions\Diagnostics\SaveTestRun;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Reports\GenerateReport;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ToolResultFormat;
use App\Livewire\Reports\Index;
use App\Models\Customer;
use App\Models\DiagnosticFinding;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->version = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Alpha']))->create();
    $feature = Feature::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->version->id], [], [$feature->id]);
    $this->repository = Repository::factory()->for($this->version)->create(['path' => 'satis/api', 'web_url' => 'https://gitlab.example.test/satis/api']);
    $this->campaign = app(SaveDiagnosticCampaign::class)->handle([
        'repository_id' => $this->repository->id, 'commit_sha' => str_repeat('a', 40), 'title' => 'Audit initial',
        'analyzed_on' => '2026-11-10', 'objective' => 'Mesurer la **dette** avant refactoring.', 'limitations' => 'Dossier `legacy/` exclu.',
    ]);

    $result = fn (string $rule, string $path, int $line, string $level) => [
        'ruleId' => $rule, 'level' => $level, 'message' => ['text' => "Message {$rule}"],
        'locations' => [['physicalLocation' => ['artifactLocation' => ['uri' => $path], 'region' => ['startLine' => $line]]]],
    ];
    app(ImportToolResults::class)->handle($this->campaign, ToolResultFormat::Sarif, json_encode(['version' => '2.1.0', 'runs' => [[
        'tool' => ['driver' => ['name' => 'Semgrep', 'semanticVersion' => '1.95.0']],
        'results' => [$result('note.rule', 'app/A.php', 1, 'note'), $result('sqli', 'app/Repo.php', 12, 'error'), $result('xss', 'app/View.php', 4, 'warning'), $result('minor.rule', 'app/B.php', 2, 'warning')],
    ]]]), 'semgrep.sarif', ['tool_name' => null, 'tool_version' => null, 'scanned_on' => '2026-11-10', 'path_prefix' => null]);
    app(ImportToolResults::class)->handle($this->campaign, ToolResultFormat::Phpstan, json_encode([
        'files' => ['app/User.php' => ['messages' => [['message' => 'Undefined variable', 'line' => 8]]]],
    ]), 'phpstan.json', ['tool_name' => null, 'tool_version' => null, 'scanned_on' => '2026-11-11', 'path_prefix' => null]);

    $finding = fn (string $rule): int => DiagnosticFinding::query()->where('rule_id', $rule)->sole()->id;
    $issueData = fn (string $title, IssueSeverity $severity): array => [
        'title' => $title, 'description' => 'Constat', 'impact' => null, 'type' => IssueType::Security, 'severity' => $severity,
        'status' => IssueStatus::Identified, 'proposed_solution' => 'Requêtes préparées.', 'effort_min_days' => '1', 'effort_max_days' => '2',
        'confidence' => null, 'owner_id' => null, 'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null,
    ];
    $implementation = $this->version->implementations()->where('feature_id', $feature->id)->sole()->id;
    $this->minorIssue = app(RetainFindingsInNewIssue::class)->handle($this->campaign, [$finding('minor.rule')], $issueData('Règle mineure', IssueSeverity::Minor), [$implementation]);
    $this->sqlIssue = app(RetainFindingsInNewIssue::class)->handle($this->campaign, [$finding('sqli')], $issueData('Injection SQL', IssueSeverity::Blocking), [$implementation]);
    app(RejectFindings::class)->handle($this->campaign, [$finding('xss')], 'Échappé par Blade.');

    $this->generate = fn (): ReportGeneration => app(GenerateReport::class)->handle('L4', new ReportScope($this->product->id, null, $this->campaign->id), $this->user);
    $this->sections = fn (ReportGeneration $generation): array => json_decode($generation->snapshot, true)['sections'];
});

it('reports one campaign: repository, commit, tools and results with their triage', function () {
    $generation = ($this->generate)();
    $sections = ($this->sections)($generation);
    $text = fn (string $variable): string => json_encode($sections[$variable], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($generation->scope)->toBe(['product_id' => $this->product->id, 'customer_id' => null, 'campaign_id' => $this->campaign->id])
        ->and($generation->scope_label)->toContain('DIA-')
        ->and($text('diagnostic.scope'))->toContain('au commit '.str_repeat('a', 40))->toContain('Mesurer la dette')
        ->and($text('diagnostic.tools'))->toContain('Semgrep 1.95.0 — exécuté le 10/11/2026')->toContain('PHPStan (version à confirmer)')
        ->and($text('diagnostic.results'))->toContain('5 résultats distincts importés ; 3 vérifiés (60 %)')
        ->and($text('diagnostic.tests'))->toContain('ne sont pas mesurés')->toContain('Dossier legacy/ exclu.')
        ->and($text('diagnostic.recommendations'))->toContain('Injection SQL — bloquante')->toContain('Vérifier les 2 résultats restants')
        ->toContain('Confirmer la version de : PHPStan')->toContain('Exécuter les tests')
        ->and($text('diagnostic.evidence'))->toContain('https://gitlab.example.test/satis/api/-/commit/'.str_repeat('a', 40))->toContain('semgrep.sarif');
});

it('prioritizes retained results by gravity, then results to verify, and keeps false positives for the annex', function () {
    $sections = ($this->sections)(($this->generate)());
    $rows = $sections['findings.table']['rows'];

    expect(array_column($rows, 'rule'))->toBe(['sqli', 'minor.rule', null, 'note.rule'])
        ->and($rows[0])->toMatchArray([
            'severity' => 'Bloquante',
            'issue_id' => $this->sqlIssue->displayCode(),
            'verification_status' => 'Vérifié — retenu',
            'evidence_url' => 'https://gitlab.example.test/satis/api/-/blob/'.str_repeat('a', 40).'/app/Repo.php#L12',
            'tool_name' => 'Semgrep',
            'scan_date' => '10/11/2026',
            'commit' => str_repeat('a', 12),
        ])
        ->and($rows[2])->toMatchArray(['severity' => 'Niveau outil : erreur', 'verification_status' => 'À vérifier', 'tool_version' => null, 'limitations' => 'Non vérifié : ne constitue pas un constat'])
        ->and($sections['annex.false_positives']['rows'][0])->toMatchArray(['rule' => 'xss', 'reason' => 'Échappé par Blade.', 'decided' => 'Awa Koné, '.now()->format('d/m/Y')]);
});

it('reports measured tests and coverage, and writes the Word document and its annex', function () {
    app(SaveTestRun::class)->handle($this->campaign, [
        'tool_name' => 'Pest', 'tool_version' => '4.1', 'executed_on' => '2026-11-12', 'tests_total' => 120, 'tests_passed' => 118,
        'tests_failed' => 2, 'tests_skipped' => 0, 'line_coverage' => '41.5', 'scope' => 'Suite Feature uniquement.', 'source_file_name' => 'junit.xml', 'source_sha256' => str_repeat('f', 64),
    ]);

    $generation = ($this->generate)();
    $tests = json_encode(($this->sections)($generation)['diagnostic.tests'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($tests)->toContain('Pest 4.1 — exécuté le 12/11/2026 — 120 tests : 118 réussis, 2 en échec, 0 ignorés — couverture des lignes : 41,5 %')
        ->and(Storage::disk('local')->exists($generation->docx_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($generation->xlsx_path))->toBeTrue();

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($generation->docx_path));
    $document = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    $zip->open(Storage::disk('local')->path($generation->xlsx_path));
    $annex = '';
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $annex .= (string) $zip->getFromIndex($index);
    }
    $zip->close();

    // Columns identical on every row stay in the annex, so that the Word table remains readable.
    expect($document)->not->toContain('{{')->toContain('Injection SQL')->toContain('satis/api')->not->toContain('>Campagne</w:t>')
        ->and($annex)->toContain('Campagne')->toContain('Version de l’outil')->toContain('Faux positifs écartés')->toContain('Échappé par Blade.');
});

it('keeps the scope of the other deliverables unchanged', function () {
    expect((new ReportScope($this->product->id))->toArray())->toBe(['product_id' => $this->product->id, 'customer_id' => null]);
});

it('requires a campaign of the product to generate L4 from the reports page', function () {
    $otherCampaign = app(SaveDiagnosticCampaign::class)->handle([
        'repository_id' => Repository::factory()->create()->id, 'commit_sha' => str_repeat('b', 40), 'title' => 'Autre',
        'analyzed_on' => null, 'objective' => null, 'limitations' => null,
    ]);

    Livewire::test(Index::class)
        ->set('form.code', 'L4')
        ->set('form.product_id', (string) $this->product->id)
        ->assertSee('Audit initial')
        ->call('generate')
        ->assertHasErrors(['form.campaign_id' => 'required'])
        ->set('form.campaign_id', (string) $otherCampaign->id)
        ->call('generate')
        ->assertHasErrors(['form.campaign_id'])
        ->set('form.campaign_id', (string) $this->campaign->id)
        ->call('generate')
        ->assertHasNoErrors();

    expect(ReportGeneration::query()->sole()->scope)->toMatchArray(['campaign_id' => $this->campaign->id]);
});
