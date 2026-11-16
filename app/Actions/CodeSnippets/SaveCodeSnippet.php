<?php

namespace App\Actions\CodeSnippets;

use App\Enums\SnippetKind;
use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Issue;
use App\Models\Repository;
use DomainException;

/**
 * Saves a code snippet pasted on a business rule or an issue.
 *
 * The optional repository must belong to a version the owner concerns. A snippet anchored
 * on a commit (RG-10) keeps its content even when the code changes later.
 */
final class SaveCodeSnippet
{
    /**
     * @param  array{kind: SnippetKind, language: string, content: string, repository_id: ?int, path: ?string, line_start: ?int, line_end: ?int}  $data
     */
    public function handle(BusinessRule|Issue $owner, array $data, ?CodeSnippet $snippet = null): CodeSnippet
    {
        if ($data['repository_id'] !== null) {
            $versionIds = $owner instanceof BusinessRule
                ? [$owner->implementation->version_id]
                : $owner->implementations()->pluck('version_id')->all();

            $repository = Repository::query()->findOrFail($data['repository_id']);

            if (! in_array($repository->version_id, $versionIds, true)) {
                throw new DomainException("Le dépôt {$repository->path} n'appartient à aucune des versions concernées.");
            }
        }

        if ($data['line_start'] !== null && $data['line_end'] !== null && $data['line_end'] < $data['line_start']) {
            throw new DomainException('La ligne de fin doit être après la ligne de début.');
        }

        $snippet ??= new CodeSnippet;
        $snippet->fill([
            ...$data,
            'business_rule_id' => $owner instanceof BusinessRule ? $owner->id : null,
            'issue_id' => $owner instanceof Issue ? $owner->id : null,
        ])->save();

        return $snippet;
    }
}
