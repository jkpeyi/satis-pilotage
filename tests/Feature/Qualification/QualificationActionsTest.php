<?php

use App\Actions\BusinessRules\CopyBusinessRuleToVersions;
use App\Actions\BusinessRules\SaveBusinessRule;
use App\Actions\CodeSnippets\SaveCodeSnippet;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Implementations\InitializeVersionFrom;
use App\Actions\Implementations\UpdateImplementationAssessment;
use App\Actions\Issues\SaveIssue;
use App\Actions\Issues\UnlinkIssueFromImplementation;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Enums\SnippetKind;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Repository;
use App\Models\User;
use App\Models\Version;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->feature = Feature::factory()->create(['name' => 'Simulation']);
    $this->source = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$this->source->id], [], [$this->feature->id]);
    $this->implementation = $this->source->implementations()->where('feature_id', $this->feature->id)->sole();
});

function makeRule(Implementation $implementation, string $title = 'Taux plafond'): BusinessRule
{
    return app(SaveBusinessRule::class)->handle([
        'implementation_id' => $implementation->id,
        'title' => $title,
        'description' => 'Le taux ne peut pas dépasser le taux d\'usure.',
        'source' => RuleSource::Code,
        'status' => RuleStatus::Pending,
    ]);
}

function issueData(array $overrides = []): array
{
    return [
        'title' => 'Arrondi des échéances',
        'description' => 'Les centimes sont tronqués.',
        'type' => IssueType::Bug,
        'severity' => IssueSeverity::Major,
        'status' => IssueStatus::Open,
        'proposed_solution' => null,
        'effort_days' => '1.5',
        ...$overrides,
    ];
}

it('RG-06 / RG-07: saves the assessment and records the status change with its note', function () {
    app(UpdateImplementationAssessment::class)->handle($this->implementation, [
        'status' => ImplementationStatus::Partial,
        'deviation' => 'Le **taux** est saisi en mensuel au lieu d\'annuel.',
        'assessment_note' => 'Vu avec le métier',
    ], $this->user);

    $this->implementation->refresh();
    expect($this->implementation->status)->toBe(ImplementationStatus::Partial)
        ->and($this->implementation->deviation)->toContain('mensuel')
        ->and($this->implementation->assessed_by)->toBe($this->user->id)
        ->and($this->implementation->statusChanges()->sole()->note)->toBe('Vu avec le métier');
});

it('RG-11: copies a rule to other versions with its snippets and a link to its origin', function () {
    $rule = makeRule($this->implementation);
    app(SaveCodeSnippet::class)->handle($rule, [
        'kind' => SnippetKind::Finding, 'language' => 'php', 'content' => 'return min($rate, $usury);',
        'repository_id' => null, 'path' => 'app/Loan.php', 'line_start' => 10, 'line_end' => 12,
    ]);
    [$withItem, $withoutItem] = Version::factory()->count(2)->create();
    app(AttachCatalogItems::class)->handle([$withItem->id], [], [$this->feature->id]);

    $count = app(CopyBusinessRuleToVersions::class)->handle($rule, [$withItem->id, $withoutItem->id]);

    $copy = BusinessRule::query()->where('origin_rule_id', $rule->id)->sole();
    expect($count)->toBe(1)
        ->and($copy->implementation->version_id)->toBe($withItem->id)
        ->and($copy->title)->toBe('Taux plafond')
        ->and($copy->codeSnippets()->sole()->location())->toBe('app/Loan.php:10-12');
});

it('RG-11: attaches the item when asked, points copies of copies to the root and skips existing copies', function () {
    $rule = makeRule($this->implementation);
    [$second, $third] = Version::factory()->count(2)->create();

    app(CopyBusinessRuleToVersions::class)->handle($rule, [$second->id], attachMissing: true);
    $copy = BusinessRule::query()->where('origin_rule_id', $rule->id)->sole();
    app(CopyBusinessRuleToVersions::class)->handle($copy, [$third->id, $this->source->id], attachMissing: true);
    $again = app(CopyBusinessRuleToVersions::class)->handle($rule, [$second->id, $third->id]);

    expect(BusinessRule::query()->where('origin_rule_id', $rule->id)->count())->toBe(2)
        ->and($again)->toBe(0)
        ->and($second->implementations()->where('feature_id', $this->feature->id)->exists())->toBeTrue();
});

it('RG-09: refuses to create an issue that concerns no implementation', function () {
    app(SaveIssue::class)->handle(issueData());
})->throws(DomainException::class);

it('RG-09: links an issue to several versions and keeps at least one implementation', function () {
    $other = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$other->id], [], [$this->feature->id]);
    $otherImplementation = $other->implementations()->where('feature_id', $this->feature->id)->sole();

    $issue = app(SaveIssue::class)->handle(issueData(), null, [$this->implementation->id, $otherImplementation->id]);
    expect($issue->implementations()->count())->toBe(2);

    app(UnlinkIssueFromImplementation::class)->handle($issue, $otherImplementation);
    expect(fn () => app(UnlinkIssueFromImplementation::class)->handle($issue, $this->implementation))
        ->toThrow(DomainException::class);
    expect($issue->implementations()->count())->toBe(1);
});

it('refuses a snippet anchored in a repository of another version', function () {
    $rule = makeRule($this->implementation);
    $foreignRepository = Repository::factory()->create();

    app(SaveCodeSnippet::class)->handle($rule, [
        'kind' => SnippetKind::Finding, 'language' => 'php', 'content' => '…',
        'repository_id' => $foreignRepository->id, 'path' => null, 'line_start' => null, 'line_end' => null,
    ]);
})->throws(DomainException::class);

it('accepts a snippet anchored in a repository of a version linked to the issue', function () {
    $repository = Repository::factory()->for($this->source)->create();
    $issue = app(SaveIssue::class)->handle(issueData(), null, [$this->implementation->id]);

    $snippet = app(SaveCodeSnippet::class)->handle($issue, [
        'kind' => SnippetKind::Solution, 'language' => 'php', 'content' => 'round($amount, 2);',
        'repository_id' => $repository->id, 'path' => 'app/Schedule.php', 'line_start' => 5, 'line_end' => null,
    ]);

    expect($snippet->issue_id)->toBe($issue->id)
        ->and($snippet->business_rule_id)->toBeNull()
        ->and($snippet->location())->toBe('app/Schedule.php:5');
});

it('EF-108: initializes a version from another with statuses, deviations and rules', function () {
    $this->implementation->update(['status' => ImplementationStatus::Defective, 'deviation' => 'Écart A']);
    $rule = makeRule($this->implementation);
    $target = Version::factory()->create();

    $count = app(InitializeVersionFrom::class)->handle($this->source, $target, ['statuses' => true, 'deviations' => true, 'rules' => true]);

    $copy = $target->implementations()->where('feature_id', $this->feature->id)->sole();
    expect($count)->toBe(2)
        ->and($copy->status)->toBe(ImplementationStatus::Defective)
        ->and($copy->deviation)->toBe('Écart A')
        ->and($copy->statusChanges()->sole()->note)->toContain('Initialisé depuis')
        ->and($copy->businessRules()->sole()->origin_rule_id)->toBe($rule->id);
});

it('EF-108: copies only the structure by default and keeps items already attached', function () {
    $this->implementation->update(['status' => ImplementationStatus::Defective, 'deviation' => 'Écart A']);
    makeRule($this->implementation);
    $target = Version::factory()->create();
    app(AttachCatalogItems::class)->handle([$target->id], [], [$this->feature->id]);
    $existing = $target->implementations()->where('feature_id', $this->feature->id)->sole();

    $count = app(InitializeVersionFrom::class)->handle($this->source, $target, ['statuses' => false, 'deviations' => false, 'rules' => false]);

    expect($count)->toBe(0)
        ->and($existing->refresh()->status)->toBe(ImplementationStatus::Unknown)
        ->and($existing->businessRules()->count())->toBe(0);
});

it('EF-108: refuses to initialize a version from itself', function () {
    app(InitializeVersionFrom::class)->handle($this->source, $this->source, ['statuses' => true, 'deviations' => true, 'rules' => true]);
})->throws(DomainException::class);
