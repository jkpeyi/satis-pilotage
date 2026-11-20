<?php

namespace App\Actions\Diagnostics;

use App\Enums\FindingStatus;
use App\Models\DiagnosticCampaign;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * Rejects results as false positives (RG-18). The reason is required: it is the trace of the
 * verification shown in L4.
 */
final class RejectFindings
{
    /**
     * @param  list<int>  $findingIds
     */
    public function handle(DiagnosticCampaign $campaign, array $findingIds, string $reason): int
    {
        if (trim($reason) === '') {
            throw new DomainException('Indiquez pourquoi ces résultats sont des faux positifs.');
        }

        return TriageFindings::select($campaign, $findingIds, FindingStatus::ToVerify)->update([
            'status' => FindingStatus::FalsePositive->value,
            'triage_note' => trim($reason),
            'triaged_by' => Auth::id(),
            'triaged_at' => now(),
            'updated_by' => Auth::id(),
        ]);
    }
}
