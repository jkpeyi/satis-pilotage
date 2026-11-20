<?php

namespace App\Actions\Issues;

use App\Enums\Confidence;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Component;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\Repository;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an issue (EF-208). A new issue must concern at least one implementation (RG-09).
 */
final class SaveIssue
{
    /**
     * The repository and the component, when given, belong to a version the issue concerns.
     *
     * @param  array{title: string, description: string, impact: ?string, type: IssueType, severity: IssueSeverity, status: IssueStatus, proposed_solution: ?string, effort_min_days: ?string, effort_max_days: ?string, confidence: ?Confidence, owner_id: ?int, repository_id: ?int, component_id: ?int, gitlab_issue_url: ?string}  $data
     * @param  list<int>  $implementationIds  required when creating
     */
    public function handle(array $data, ?Issue $issue = null, array $implementationIds = []): Issue
    {
        if ($issue === null && $implementationIds === []) {
            throw new DomainException('Un problème doit concerner au moins un élément d\'une version.');
        }

        $versionIds = Implementation::query()
            ->whereKey($implementationIds)
            ->orWhereHas('issues', fn ($query) => $query->whereKey($issue->id ?? 0))
            ->pluck('version_id')->unique()->all();

        if ($data['repository_id'] !== null && ! Repository::query()->whereKey($data['repository_id'])->whereIn('version_id', $versionIds)->exists()) {
            throw new DomainException('Le dépôt choisi n’appartient pas à une version concernée par ce problème.');
        }

        if ($data['component_id'] !== null && ! Component::query()->whereKey($data['component_id'])->whereIn('version_id', $versionIds)->exists()) {
            throw new DomainException('Le composant choisi n’appartient pas à une version concernée par ce problème.');
        }

        // A single estimate is a range of one.
        if ($data['effort_min_days'] !== null && $data['effort_max_days'] === null) {
            $data['effort_max_days'] = $data['effort_min_days'];
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
