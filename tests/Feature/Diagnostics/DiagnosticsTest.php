<?php

use App\Actions\Diagnostics\ImportToolResults;
use App\Actions\Diagnostics\RejectFindings;
use App\Actions\Diagnostics\ReopenFindings;
use App\Actions\Diagnostics\RetainFindingsInIssue;
use App\Actions\Diagnostics\RetainFindingsInNewIssue;
use App\Actions\Diagnostics\SaveDiagnosticCampaign;
use App\Actions\Diagnostics\SaveTestRun;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Issues\SaveIssue;
use App\Actions\Reports\GenerateReport;
use App\Diagnostics\InvalidToolResultFile;
use App\Diagnostics\TestReport;
use App\Diagnostics\ToolResultParser;
use App\Enums\FindingLevel;
use App\Enums\FindingStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ToolResultFormat;
use App\Livewire\Diagnostics\Show;
use App\Models\DiagnosticFinding;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function sarif(array $results, array $rules = []): string
{
    return json_encode(['version' => '2.1.0', 'runs' => [[
        'tool' => ['driver' => ['name' => 'Semgrep OSS', 'semanticVersion' => '1.95.0', 'rules' => $rules]],
        'results' => $results,
    ]]]);
}

function sarifResult(string $rule, string $message, string $uri, int $line, ?string $level = 'error'): array
{
    return array_filter([
        'ruleId' => $rule,
        'level' => $level,
        'message' => ['text' => $message],
        'locations' => [['physicalLocation' => ['artifactLocation' => ['uri' => $uri], 'region' => ['startLine' => $line, 'endLine' => $line + 2, 'snippet' => ['text' => 'SECRET=abc']]]]],
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->version = Version::factory()->create();
    $this->feature = Feature::factory()->create(['name' => 'Authentification']);
    app(AttachCatalogItems::class)->handle([$this->version->id], [], [$this->feature->id]);
    $this->implementation = $this->version->implementations()->where('feature_id', $this->feature->id)->sole();
    $this->repository = Repository::factory()->for($this->version)->create(['path' => 'satis/api', 'web_url' => 'https://gitlab.example.test/satis/api']);
    $this->campaign = app(SaveDiagnosticCampaign::class)->handle([
        'repository_id' => $this->repository->id, 'commit_sha' => str_repeat('C', 40), 'title' => 'Audit initial',
        'analyzed_on' => '2026-11-10', 'objective' => null, 'limitations' => 'Pas d’accès à la base.',
    ]);
    $this->import = fn (string $contents, ToolResultFormat $format = ToolResultFormat::Sarif, array $options = []) => app(ImportToolResults::class)->handle(
        $this->campaign, $format, $contents, 'resultats.json',
        ['tool_name' => null, 'tool_version' => null, 'scanned_on' => '2026-11-10', 'path_prefix' => '/builds/satis/api', ...$options],
    );
    $this->issueData = [
        'title' => 'Injection SQL', 'description' => 'Requête concaténée.', 'impact' => null, 'type' => IssueType::Security,
        'severity' => IssueSeverity::Blocking, 'status' => IssueStatus::Identified, 'proposed_solution' => null,
        'effort_min_days' => null, 'effort_max_days' => null, 'confidence' => null, 'owner_id' => null,
        'repository_id' => null, 'component_id' => null, 'gitlab_issue_url' => null,
    ];
});

it('stores the commit in lower case', function () {
    expect($this->campaign->commit_sha)->toBe(str_repeat('c', 40));
});

it('reads a SARIF file: tool, levels from the rules, relative paths, no code excerpt', function () {
    $report = ToolResultParser::for(ToolResultFormat::Sarif)->parse(sarif([
        sarifResult('php.sqli', 'Requête  concaténée', 'file:///builds/satis/api/app/Repo%20Client.php', 12),
        ['ruleIndex' => 0, 'message' => ['text' => 'Mot de passe en dur'], 'locations' => [['physicalLocation' => ['artifactLocation' => ['uri' => 'config/app.php']]]]],
    ], [['id' => 'secrets.hardcoded', 'defaultConfiguration' => ['level' => 'note']]]));

    expect($report->toolName)->toBe('Semgrep OSS')
        ->and($report->toolVersion)->toBe('1.95.0')
        ->and($report->results)->toHaveCount(2)
        ->and($report->results[0]->path)->toBe('/builds/satis/api/app/Repo Client.php')
        ->and($report->results[0]->message)->toBe('Requête concaténée')
        ->and([$report->results[0]->lineStart, $report->results[0]->lineEnd])->toBe([12, 14])
        ->and($report->results[1]->ruleId)->toBe('secrets.hardcoded')
        ->and($report->results[1]->level)->toBe(FindingLevel::Note)
        ->and(json_encode($report))->not->toContain('SECRET=abc');
});

it('reads PHPStan and ESLint JSON files', function () {
    $phpstan = ToolResultParser::for(ToolResultFormat::Phpstan)->parse(json_encode([
        'totals' => ['errors' => 1, 'file_errors' => 1],
        'files' => ['/builds/satis/api/app/User.php' => ['errors' => 1, 'messages' => [['message' => 'Undefined variable $x', 'line' => 8, 'identifier' => 'variable.undefined']]]],
        'errors' => ['Autoload error'],
    ]));
    $eslint = ToolResultParser::for(ToolResultFormat::Eslint)->parse(json_encode([
        ['filePath' => '/builds/satis/api/resources/js/app.js', 'messages' => [
            ['ruleId' => 'no-unused-vars', 'severity' => 1, 'message' => 'x is unused', 'line' => 3],
            ['ruleId' => 'no-undef', 'severity' => 2, 'message' => 'y is not defined', 'line' => 5, 'endLine' => 6],
        ]],
    ]));

    expect($phpstan->results)->toHaveCount(2)
        ->and($phpstan->results[0]->ruleId)->toBe('variable.undefined')
        ->and($phpstan->results[0]->level)->toBe(FindingLevel::Error)
        ->and($phpstan->results[1]->path)->toBeNull()
        ->and(array_map(fn ($result) => $result->level, $eslint->results))->toBe([FindingLevel::Warning, FindingLevel::Error])
        ->and($eslint->results[1]->lineEnd)->toBe(6);
});

it('refuses a file that does not match the chosen format', function () {
    ($this->import)('{"files": {}}', ToolResultFormat::Sarif);
})->throws(InvalidToolResultFile::class, 'format SARIF');

it('RG-18: imports a file once per campaign and does not duplicate a known result', function () {
    $first = ($this->import)(sarif([
        sarifResult('php.sqli', 'Requête concaténée', '/builds/satis/api/app/Repo.php', 12),
        sarifResult('php.sqli', 'Requête concaténée', '/builds/satis/api/app/Repo.php', 12),
        sarifResult('php.xss', 'Sortie non échappée', '/builds/satis/api/app/View.php', 4, 'warning'),
    ]));

    expect([$first->tool_name, $first->tool_version, $first->results_count, $first->new_count, $first->duplicate_count])
        ->toBe(['Semgrep OSS', '1.95.0', 3, 2, 1]);

    expect(fn () => ($this->import)(sarif([
        sarifResult('php.sqli', 'Requête concaténée', '/builds/satis/api/app/Repo.php', 12),
        sarifResult('php.sqli', 'Requête concaténée', '/builds/satis/api/app/Repo.php', 12),
        sarifResult('php.xss', 'Sortie non échappée', '/builds/satis/api/app/View.php', 4, 'warning'),
    ])))->toThrow(DomainException::class, 'déjà été importé');

    $second = ($this->import)(sarif([
        sarifResult('php.sqli', 'Requête concaténée', '/builds/satis/api/app/Repo.php', 12),
        sarifResult('php.eval', 'Usage de eval', '/builds/satis/api/app/Legacy.php', 30),
    ]));

    $sqli = DiagnosticFinding::query()->where('rule_id', 'php.sqli')->sole();
    expect([$second->new_count, $second->duplicate_count])->toBe([1, 1])
        ->and(DiagnosticFinding::query()->count())->toBe(3)
        ->and($sqli->occurrences)->toBe(3)
        ->and($sqli->tool_run_id)->toBe($first->id)
        ->and($sqli->path)->toBe('app/Repo.php');
});

it('RG-18: an imported result arrives to verify and never becomes a problem by itself', function () {
    ($this->import)(sarif([sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12)]));

    expect(DiagnosticFinding::query()->sole()->status)->toBe(FindingStatus::ToVerify)
        ->and(Issue::query()->count())->toBe(0);
});

it('RG-18: rejects results as false positives with a reason and the person who decided', function () {
    ($this->import)(sarif([sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12)]));
    $finding = DiagnosticFinding::query()->sole();

    expect(fn () => app(RejectFindings::class)->handle($this->campaign, [$finding->id], ' '))->toThrow(DomainException::class);

    app(RejectFindings::class)->handle($this->campaign, [$finding->id], 'Code de test.');

    expect($finding->refresh())
        ->status->toBe(FindingStatus::FalsePositive)
        ->triage_note->toBe('Code de test.')
        ->triaged_by->toBe($this->user->id)
        ->and($finding->triaged_at)->not->toBeNull()
        ->and(fn () => app(RejectFindings::class)->handle($this->campaign, [$finding->id], 'Encore'))->toThrow(DomainException::class, 'Seuls les résultats à vérifier');
});

it('RG-18: creates a problem from results and retains them in it', function () {
    ($this->import)(sarif([
        sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12),
        sarifResult('php.sqli', 'Requête concaténée', 'app/Other.php', 7),
    ]));
    $ids = DiagnosticFinding::query()->pluck('id')->all();

    expect(fn () => app(RetainFindingsInNewIssue::class)->handle($this->campaign, $ids, $this->issueData, []))
        ->toThrow(DomainException::class, 'élément de la version analysée');

    $issue = app(RetainFindingsInNewIssue::class)->handle($this->campaign, $ids, $this->issueData, [$this->implementation->id]);

    expect($issue->repository_id)->toBe($this->repository->id)
        ->and($issue->implementations->pluck('id')->all())->toBe([$this->implementation->id])
        ->and($issue->diagnosticFindings()->pluck('status')->unique()->all())->toBe([FindingStatus::Retained]);
});

it('RG-18: retains results in an existing problem of the analyzed version only', function () {
    ($this->import)(sarif([sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12)]));
    $finding = DiagnosticFinding::query()->sole();
    $other = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$other->id], [], [$this->feature->id]);
    $elsewhere = app(SaveIssue::class)->handle($this->issueData, null, [$other->implementations()->where('feature_id', $this->feature->id)->sole()->id]);
    $here = app(SaveIssue::class)->handle($this->issueData, null, [$this->implementation->id]);

    expect(fn () => app(RetainFindingsInIssue::class)->handle($this->campaign, [$finding->id], $elsewhere))
        ->toThrow(DomainException::class, 'ne concerne pas la version');

    app(RetainFindingsInIssue::class)->handle($this->campaign, [$finding->id], $here, 'Même faille');

    expect($finding->refresh())->status->toBe(FindingStatus::Retained)->issue_id->toBe($here->id)->triage_note->toBe('Même faille');

    app(ReopenFindings::class)->handle($this->campaign, [$finding->id]);

    expect($finding->refresh())->status->toBe(FindingStatus::ToVerify)->issue_id->toBeNull()->triaged_by->toBeNull()
        ->and($here->refresh()->exists)->toBeTrue();
});

it('RG-18: a result is retained if and only if it is linked to a problem', function () {
    ($this->import)(sarif([sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12)]));

    DiagnosticFinding::query()->update(['status' => 'retained']);
})->throws(QueryException::class);

it('fixes the repository and the commit once results are imported', function () {
    ($this->import)(sarif([sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12)]));

    app(SaveDiagnosticCampaign::class)->handle([
        'repository_id' => $this->repository->id, 'commit_sha' => str_repeat('d', 40), 'title' => 'Audit',
        'analyzed_on' => null, 'objective' => null, 'limitations' => null,
    ], $this->campaign);
})->throws(DomainException::class, 'nouvelle campagne');

it('reads JUnit counts and Clover or Cobertura coverage', function () {
    $junit = TestReport::junit('<?xml version="1.0"?><testsuites><testsuite name="a" tests="10" failures="1" errors="1" skipped="2"/><testsuite name="b" tests="5" failures="0" errors="0" skipped="0"/></testsuites>');
    $clover = TestReport::coverage('<?xml version="1.0"?><coverage><project><metrics statements="200" coveredstatements="123"/></project></coverage>');
    $cobertura = TestReport::coverage('<?xml version="1.0"?><coverage line-rate="0.4567"></coverage>');

    expect($junit)->toBe(['tests_total' => 15, 'tests_passed' => 11, 'tests_failed' => 2, 'tests_skipped' => 2])
        ->and($clover)->toBe(61.5)
        ->and($cobertura)->toBe(45.67)
        ->and(fn () => TestReport::junit('<html/>'))->toThrow(InvalidToolResultFile::class);
});

it('refuses test counts above the total', function () {
    app(SaveTestRun::class)->handle($this->campaign, [
        'tool_name' => 'Pest', 'tool_version' => null, 'executed_on' => '2026-11-10', 'tests_total' => 3, 'tests_passed' => 3,
        'tests_failed' => 1, 'tests_skipped' => null, 'line_coverage' => null, 'scope' => null, 'source_file_name' => null, 'source_sha256' => null,
    ]);
})->throws(DomainException::class);

it('imports and triages results from the campaign page', function () {
    $file = UploadedFile::fake()->createWithContent('semgrep.sarif', sarif([
        sarifResult('php.sqli', 'Requête concaténée', '/builds/satis/api/app/Repo.php', 12),
        sarifResult('php.xss', 'Sortie non échappée', '/builds/satis/api/app/View.php', 4, 'warning'),
    ]));

    $page = Livewire::test(Show::class, ['campaign' => $this->campaign])
        ->call('openImport')
        ->set('importForm.format', 'sarif')
        ->set('importForm.file', $file)
        ->set('importForm.path_prefix', '/builds/satis/api')
        ->call('import')
        ->assertHasNoErrors()
        ->assertSee('Sortie non échappée')
        ->assertSee('https://gitlab.example.test/satis/api/-/blob/'.str_repeat('c', 40).'/app/Repo.php#L12-14');

    $sqli = DiagnosticFinding::query()->where('rule_id', 'php.sqli')->sole();
    $xss = DiagnosticFinding::query()->where('rule_id', 'php.xss')->sole();

    $page->set('selected', [(string) $xss->id])
        ->call('openReject')
        ->set('rejectReason', 'Sortie déjà échappée par le moteur de vues.')
        ->call('reject')
        ->assertHasNoErrors()
        ->set('selected', [(string) $sqli->id])
        ->call('openCreateIssue')
        ->assertSet('issueForm.type', 'security')
        ->assertSet('issueForm.severity', 'major')
        ->set('implementationIds', [(string) $this->implementation->id])
        ->call('createIssue')
        ->assertHasNoErrors();

    expect($xss->refresh()->status)->toBe(FindingStatus::FalsePositive)
        ->and($sqli->refresh()->issue->title)->toBe('php.sqli : Requête concaténée');

    $this->get(route('issues.show', $sqli->issue))->assertOk()->assertSee('Résultats d’outils retenus')->assertSee($sqli->displayCode());
    $this->get(route('diagnostics.index'))->assertOk()->assertSee('Audit initial');
});

it('fills a test run from a JUnit report on the campaign page', function () {
    Livewire::test(Show::class, ['campaign' => $this->campaign])
        ->call('createTestRun')
        ->set('testForm.tool_name', 'Pest')
        ->set('testForm.junit_file', UploadedFile::fake()->createWithContent('junit.xml', '<testsuites tests="4" failures="1" errors="0" skipped="0"><testsuite name="a" tests="4"/></testsuites>'))
        ->assertSet('testForm.tests_total', '4')
        ->assertSet('testForm.tests_passed', '3')
        ->call('saveTestRun')
        ->assertHasNoErrors();

    expect($this->campaign->testRuns()->sole())
        ->tests_failed->toBe(1)
        ->source_file_name->toBe('junit.xml')
        ->line_coverage->toBeNull();
});

it('uses a retained tool result as evidence of a problem in L5', function () {
    Storage::fake('local');
    ($this->import)(sarif([sarifResult('php.sqli', 'Requête concaténée', 'app/Repo.php', 12)]));
    app(RetainFindingsInNewIssue::class)->handle($this->campaign, DiagnosticFinding::query()->pluck('id')->all(), $this->issueData, [$this->implementation->id]);

    $generation = app(GenerateReport::class)->handle('L5', new ReportScope($this->version->product_id), $this->user);
    $row = json_decode($generation->snapshot, true)['sections']['debt_items.table']['rows'][0];

    expect($row['evidence_url'])->toBe('https://gitlab.example.test/satis/api/-/blob/'.str_repeat('c', 40).'/app/Repo.php#L12-14');
});
