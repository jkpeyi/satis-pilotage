<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Enums\VerificationStatus;
use App\Livewire\BusinessRules;
use App\Livewire\Implementations;
use App\Livewire\Issues;
use App\Livewire\Versions\Scope;
use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->feature = Feature::factory()->create(['name' => 'Simulation', 'standard_description' => 'Calcule les **échéances**.']);
    $this->version = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->version->id], [], [$this->feature->id]);
    $this->implementation = $this->version->implementations()->where('feature_id', $this->feature->id)->sole();
});

function createIssueFor($implementation, array $overrides = []): Issue
{
    $issue = Issue::query()->create([
        'title' => 'Arrondi', 'description' => 'Centimes tronqués.', 'type' => IssueType::Bug,
        'severity' => IssueSeverity::Major, 'effort_min_days' => 2, 'effort_max_days' => 2, ...$overrides,
    ]);
    $issue->implementations()->attach($implementation);

    return $issue;
}

it('renders the S3 pages', function () {
    $rule = BusinessRule::query()->create(['implementation_id' => $this->implementation->id, 'title' => 'Plafond', 'description' => 'Texte', 'source' => RuleSource::Code]);
    $issue = createIssueFor($this->implementation);

    foreach ([
        route('implementations.show', $this->implementation),
        route('business-rules.index'), route('business-rules.show', $rule),
        route('issues.index'), route('issues.show', $issue),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('EF-205: shows the standard description and saves the assessment', function () {
    Livewire::test(Implementations\Show::class, ['implementation' => $this->implementation])
        ->assertSeeHtml('<strong>échéances</strong>')
        ->set('assessment.presence', 'present')
        ->set('assessment.operation', 'defective')
        ->set('assessment.deviation', 'Le taux est mensuel.')
        ->set('assessment.assessment_note', 'Atelier du 13/11')
        ->set('assessment.verification_status', 'verified')
        ->call('saveAssessment')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSee('Description fonctionnelle enregistrée')
        ->assertDontSee('Écart');

    expect($this->implementation->refresh()->status)->toBe(ImplementationStatus::Defective)
        ->and($this->implementation->verification_status)->toBe(VerificationStatus::Verified)
        ->and($this->implementation->verified_by)->not->toBeNull()
        ->and($this->implementation->deviation)->toBe('Le taux est mensuel.')
        ->and($this->implementation->statusChanges()->sole()->note)->toBe('Atelier du 13/11');
});

it('ignores the operation of an item that is not present', function () {
    Livewire::test(Implementations\Show::class, ['implementation' => $this->implementation])
        ->set('assessment.presence', 'present')
        ->assertSet('assessment.operation', 'not_tested')
        ->set('assessment.operation', 'operational')
        ->set('assessment.presence', 'absent')
        ->assertSet('assessment.operation', 'not_applicable')
        ->call('saveAssessment')
        ->assertHasNoErrors();

    expect($this->implementation->refresh()->status)->toBe(ImplementationStatus::Absent);
});

it('EF-207: creates a business rule from the sheet', function () {
    Livewire::test(Implementations\Show::class, ['implementation' => $this->implementation])
        ->call('createRule')
        ->set('ruleForm.title', 'Plafond du taux')
        ->set('ruleForm.description', 'Le taux est plafonné.')
        ->call('saveRule')
        ->assertHasNoErrors()
        ->assertSee('Plafond du taux');

    expect($this->implementation->businessRules()->sole()->created_by)->toBe($this->user->id);
});

it('EF-208: creates an issue linked to the implementation', function () {
    Livewire::test(Implementations\Show::class, ['implementation' => $this->implementation])
        ->call('createIssue')
        ->set('issueForm.title', 'Arrondi des échéances')
        ->set('issueForm.description', 'Les centimes sont tronqués.')
        ->set('issueForm.severity', 'blocking')
        ->set('issueForm.effort_min_days', '1,5')
        ->call('saveIssue')
        ->assertHasNoErrors();

    $issue = $this->implementation->issues()->sole();
    expect($issue->severity)->toBe(IssueSeverity::Blocking)
        ->and((string) $issue->effort_min_days)->toBe('1.5')
        ->and((string) $issue->effort_max_days)->toBe('1.5');
});

it('links an existing issue by its code and suggests issues known on the same item elsewhere', function () {
    $other = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$other->id], [], [$this->feature->id]);
    $issue = createIssueFor($other->implementations()->where('feature_id', $this->feature->id)->sole());

    $component = Livewire::test(Implementations\Show::class, ['implementation' => $this->implementation])
        ->assertSee('Problèmes connus sur cet élément')
        ->set('issueCode', $issue->displayCode())
        ->call('linkIssueByCode')
        ->assertHasNoErrors();

    expect($this->implementation->issues()->sole()->is($issue))->toBeTrue();

    $component->set('issueCode', 'PB-9999')->call('linkIssueByCode')->assertHasErrors('issueCode');
});

it('adds, edits and archives a code snippet on a business rule', function () {
    $repository = Repository::factory()->for($this->version)->create();
    $rule = BusinessRule::query()->create(['implementation_id' => $this->implementation->id, 'title' => 'Plafond', 'description' => 'Texte', 'source' => RuleSource::Code]);

    $component = Livewire::test(BusinessRules\Show::class, ['rule' => $rule])
        ->call('createSnippet')
        ->set('snippetForm.content', "if (\$rate > \$usury) {\n    throw new RateTooHigh();\n}")
        ->set('snippetForm.repository_id', (string) $repository->id)
        ->set('snippetForm.path', 'app/Services/Loan.php')
        ->set('snippetForm.line_start', '42')
        ->call('saveSnippet')
        ->assertHasNoErrors()
        ->assertSee('app/Services/Loan.php:42');

    $snippet = $rule->codeSnippets()->sole();
    $component->call('toggleSnippetArchive', $snippet->id);

    expect($snippet->refresh()->isArchived())->toBeTrue();
});

it('shows an error for a snippet anchored in a repository of another version', function () {
    $rule = BusinessRule::query()->create(['implementation_id' => $this->implementation->id, 'title' => 'Plafond', 'description' => 'Texte', 'source' => RuleSource::Code]);

    Livewire::test(BusinessRules\Show::class, ['rule' => $rule])
        ->call('createSnippet')
        ->set('snippetForm.content', 'code')
        ->set('snippetForm.repository_id', (string) Repository::factory()->create()->id)
        ->call('saveSnippet')
        ->assertHasErrors('snippetForm.repository_id');

    expect(CodeSnippet::query()->count())->toBe(0);
});

it('RG-11: copies a rule from its page to another version', function () {
    $rule = BusinessRule::query()->create(['implementation_id' => $this->implementation->id, 'title' => 'Plafond', 'description' => 'Texte', 'source' => RuleSource::Code]);
    $target = Version::factory()->create();

    Livewire::test(BusinessRules\Show::class, ['rule' => $rule])
        ->set('copyVersionIds', [(string) $target->id])
        ->call('copy')
        ->assertHasNoErrors()
        ->assertSee($target->name);

    expect(BusinessRule::query()->where('origin_rule_id', $rule->id)->sole()->implementation->version_id)->toBe($target->id);
});

it('RG-09: links the issue to the same item elsewhere and refuses to remove the last version', function () {
    $other = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$other->id], [], [$this->feature->id]);
    $otherImplementation = $other->implementations()->where('feature_id', $this->feature->id)->sole();
    $issue = createIssueFor($this->implementation);

    $component = Livewire::test(Issues\Show::class, ['issue' => $issue])
        ->assertSee($other->name)
        ->call('linkSameItem', $otherImplementation->id);
    expect($issue->implementations()->count())->toBe(2);

    $component->call('unlink', $otherImplementation->id)->assertHasNoErrors()
        ->call('unlink', $this->implementation->id)->assertHasErrors('unlink');
    expect($issue->implementations()->count())->toBe(1);
});

it('EF-209: filters issues and sums their effort', function () {
    createIssueFor($this->implementation, ['title' => 'Bloquant A', 'severity' => IssueSeverity::Blocking, 'effort_min_days' => 3, 'effort_max_days' => 5]);
    createIssueFor($this->implementation, ['title' => 'Mineur B', 'severity' => IssueSeverity::Minor, 'effort_min_days' => 0.5, 'effort_max_days' => 0.5]);
    createIssueFor($this->implementation, ['title' => 'Corrigé C', 'status' => 'treated', 'effort_min_days' => 10, 'effort_max_days' => 10]);

    Livewire::test(Issues\Index::class)
        ->assertSee('Bloquant A')->assertSee('Mineur B')->assertDontSee('Corrigé C')
        ->assertViewHas('totalEffort', '3,5 à 5,5 j/h')
        ->set('severity', 'blocking')
        ->assertSee('Bloquant A')->assertDontSee('Mineur B')
        ->set('severity', '')->set('status', '')
        ->assertSee('Corrigé C');
});

it('finds an issue and a rule by their code', function () {
    $issue = createIssueFor($this->implementation, ['title' => 'Cible']);
    createIssueFor($this->implementation, ['title' => 'Autre']);
    $rule = BusinessRule::query()->create(['implementation_id' => $this->implementation->id, 'title' => 'Règle cible', 'description' => 'x', 'source' => RuleSource::Code]);

    Livewire::test(Issues\Index::class)->set('search', $issue->displayCode())->assertSee('Cible')->assertDontSee('Autre');
    Livewire::test(BusinessRules\Index::class)->set('search', $rule->displayCode())->assertSee('Règle cible');
});

it('EF-108: initializes the version scope from another version', function () {
    $target = Version::factory()->create();

    Livewire::test(Scope::class, ['version' => $target])
        ->set('initSourceId', (string) $this->version->id)
        ->call('initialize')
        ->assertHasNoErrors()
        ->assertSee('Simulation');

    expect($target->implementations()->count())->toBe(2);
});
