<?php

namespace App\Actions\Diagnostics;

use App\Enums\FindingStatus;
use App\Models\DiagnosticCampaign;
use App\Models\DiagnosticFinding;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared checks of the triage actions: the results belong to the campaign and are in the
 * expected status.
 */
final class TriageFindings
{
    /**
     * @param  list<int>  $findingIds
     * @return Builder<DiagnosticFinding>
     */
    public static function select(DiagnosticCampaign $campaign, array $findingIds, FindingStatus ...$statuses): Builder
    {
        if ($findingIds === []) {
            throw new DomainException('Sélectionnez au moins un résultat.');
        }

        $query = DiagnosticFinding::query()->where('diagnostic_campaign_id', $campaign->id)->whereKey($findingIds);
        $statusValues = array_map(fn (FindingStatus $status): string => $status->value, $statuses);

        if ((clone $query)->whereIn('status', $statusValues)->count() !== count(array_unique($findingIds))) {
            throw new DomainException($statuses === [FindingStatus::ToVerify]
                ? 'Seuls les résultats à vérifier peuvent être triés : remettez d’abord les autres à vérifier.'
                : 'Certains résultats sélectionnés sont déjà à vérifier.');
        }

        return $query;
    }
}
