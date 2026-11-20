<?php

namespace App\Actions\Diagnostics;

use App\Enums\FindingStatus;
use App\Models\DiagnosticCampaign;
use Illuminate\Support\Facades\Auth;

/**
 * Puts triaged results back to verify, e.g. after a wrong decision. A problem created from them
 * is kept: it is archived from its own page if needed.
 */
final class ReopenFindings
{
    /**
     * @param  list<int>  $findingIds
     */
    public function handle(DiagnosticCampaign $campaign, array $findingIds): int
    {
        return TriageFindings::select($campaign, $findingIds, FindingStatus::Retained, FindingStatus::FalsePositive)->update([
            'status' => FindingStatus::ToVerify->value,
            'issue_id' => null,
            'triage_note' => null,
            'triaged_by' => null,
            'triaged_at' => null,
            'updated_by' => Auth::id(),
        ]);
    }
}
