<?php

namespace App\Actions\Issues;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an issue (EF-208). A new issue must concern at least one implementation (RG-09).
 */
final class SaveIssue
{
    /**
     * @param  array{title: string, description: string, type: IssueType, severity: IssueSeverity, status: IssueStatus, proposed_solution: ?string, effort_days: ?string}  $data
     * @param  list<int>  $implementationIds  required when creating
     */
    public function handle(array $data, ?Issue $issue = null, array $implementationIds = []): Issue
    {
        if ($issue === null && $implementationIds === []) {
            throw new DomainException('Un problème doit concerner au moins un élément d\'une version.');
        }

        return DB::transaction(function () use ($data, $issue, $implementationIds): Issue {
            $issue ??= new Issue;
            $issue->fill($data)->save();

            if ($implementationIds !== []) {
                $issue->implementations()->syncWithoutDetaching($implementationIds);
            }

            return $issue;
        });
    }
}
