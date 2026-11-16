<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;

it('RG-13: derives business rule and issue codes from their id', function () {
    $rule = BusinessRule::query()->create([
        'implementation_id' => Implementation::factory()->create()->id,
        'title' => 'Plafond de découvert',
        'description' => 'Le découvert est plafonné à 3 mois de salaire.',
        'source' => RuleSource::Code,
    ]);
    $issue = Issue::query()->create([
        'title' => 'Requête N+1 sur la liste des prêts',
        'description' => 'Chaque ligne charge son client.',
        'type' => IssueType::Performance,
        'severity' => IssueSeverity::Major,
    ]);

    expect($rule->displayCode())->toBe('RM-'.str_pad((string) $rule->id, 4, '0', STR_PAD_LEFT))
        ->and($issue->displayCode())->toBe('PB-'.str_pad((string) $issue->id, 4, '0', STR_PAD_LEFT));
});

it('fills created_by and updated_by from the authenticated user', function () {
    [$author, $editor] = User::factory()->count(2)->create();

    $this->actingAs($author);
    $product = Product::factory()->create();

    $this->actingAs($editor);
    $product->update(['name' => 'Satis Macro']);

    expect($product->created_by)->toBe($author->id)
        ->and($product->updated_by)->toBe($editor->id);
});

it('RG-12: archives and restores records instead of deleting them', function () {
    $product = Product::factory()->create();

    $product->archive();
    expect($product->isArchived())->toBeTrue()
        ->and(Product::query()->active()->count())->toBe(0)
        ->and(Product::query()->archived()->count())->toBe(1);

    $product->unarchive();
    expect(Product::query()->active()->count())->toBe(1);
});

it('rejects a code snippet owned by both a rule and an issue', function () {
    $rule = BusinessRule::query()->create([
        'implementation_id' => Implementation::factory()->create()->id,
        'title' => 'Règle',
        'description' => 'Description',
        'source' => RuleSource::Code,
    ]);
    $issue = Issue::query()->create([
        'title' => 'Problème',
        'description' => 'Description',
        'type' => IssueType::Bug,
        'severity' => IssueSeverity::Minor,
    ]);

    CodeSnippet::query()->create([
        'business_rule_id' => $rule->id,
        'issue_id' => $issue->id,
        'language' => 'php',
        'content' => '<?php echo 1;',
    ]);
})->throws(QueryException::class);
