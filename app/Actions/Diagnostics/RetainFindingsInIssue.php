<?php

namespace App\Actions\Diagnostics;

use App\Enums\FindingStatus;
use App\Models\DiagnosticCampaign;
use App\Models\Issue;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * Retains results in an existing problem (RG-18), e.g. the same flaw found in several files.
 * The problem must concern the version of the analyzed repository.
 */
final class RetainFindingsInIssue
{
    /**
     * @param  list<int>  $findingIds
     */
    public function handle(DiagnosticCampaign $campaign, array $findingIds, Issue $issue, ?string $note = null): int
    {
        if ($issue->isArchived()) {
            throw new DomainException("Le problème {$issue->displayCode()} est archivé.");
        }

        if (! $issue->implementations()->where('version_id', $campaign->repository->version_id)->exists()) {
            throw new DomainException("Le problème {$issue->displayCode()} ne concerne pas la version de ce dépôt : liez-le d’abord à un élément de cette version, ou créez un problème.");
        }

        $query = TriageFindings::select($campaign, $findingIds, FindingStatus::ToVerify);

        return $query->update([
            'status' => FindingStatus::Retained->value,
            'issue_id' => $issue->id,
            'triage_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'triaged_by' => Auth::id(),
            'triaged_at' => now(),
            'updated_by' => Auth::id(),
        ]);
    }
}
