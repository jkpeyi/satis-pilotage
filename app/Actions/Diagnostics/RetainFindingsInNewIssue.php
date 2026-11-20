<?php

namespace App\Actions\Diagnostics;

use App\Actions\Issues\SaveIssue;
use App\Enums\Confidence;
use App\Enums\FindingStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\DiagnosticCampaign;
use App\Models\Implementation;
use App\Models\Issue;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a problem from results and retains them in it (RG-18). The problem concerns elements of
 * the analyzed version (RG-09); its repository is the analyzed one unless another is chosen.
 */
final class RetainFindingsInNewIssue
{
    public function __construct(
        private readonly SaveIssue $saveIssue,
        private readonly RetainFindingsInIssue $retainFindingsInIssue,
    ) {}

    /**
     * @param  list<int>  $findingIds
     * @param  array{title: string, description: string, impact: ?string, type: IssueType, severity: IssueSeverity, status: IssueStatus, proposed_solution: ?string, effort_min_days: ?string, effort_max_days: ?string, confidence: ?Confidence, owner_id: ?int, repository_id: ?int, component_id: ?int, gitlab_issue_url: ?string}  $issueData
     * @param  list<int>  $implementationIds
     */
    public function handle(DiagnosticCampaign $campaign, array $findingIds, array $issueData, array $implementationIds): Issue
    {
        TriageFindings::select($campaign, $findingIds, FindingStatus::ToVerify);

        $versionId = $campaign->repository->version_id;
        if ($implementationIds === [] || Implementation::query()->whereKey($implementationIds)->where('version_id', '!=', $versionId)->exists()) {
            throw new DomainException('Choisissez au moins un élément de la version analysée.');
        }

        $issueData['repository_id'] ??= $campaign->repository_id;

        return DB::transaction(function () use ($campaign, $findingIds, $issueData, $implementationIds): Issue {
            $issue = $this->saveIssue->handle($issueData, null, $implementationIds);
            $this->retainFindingsInIssue->handle($campaign, $findingIds, $issue);

            return $issue;
        });
    }
}
