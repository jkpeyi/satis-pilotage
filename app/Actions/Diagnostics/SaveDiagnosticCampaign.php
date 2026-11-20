<?php

namespace App\Actions\Diagnostics;

use App\Models\DiagnosticCampaign;
use DomainException;

/**
 * Creates or updates a diagnostic campaign (L4): a repository analyzed at an exact commit.
 */
final class SaveDiagnosticCampaign
{
    /**
     * Once results are imported, the repository and the commit they were produced on are fixed.
     *
     * @param  array{repository_id: int, commit_sha: string, title: string, analyzed_on: ?string, objective: ?string, limitations: ?string}  $data
     */
    public function handle(array $data, ?DiagnosticCampaign $campaign = null): DiagnosticCampaign
    {
        $data['commit_sha'] = strtolower($data['commit_sha']);

        if ($campaign !== null
            && ($campaign->repository_id !== $data['repository_id'] || $campaign->commit_sha !== $data['commit_sha'])
            && $campaign->toolRuns()->exists()) {
            throw new DomainException('Des résultats ont déjà été importés pour ce dépôt et ce commit : ouvrez une nouvelle campagne pour un autre commit.');
        }

        $campaign ??= new DiagnosticCampaign;
        $campaign->fill($data)->save();

        return $campaign;
    }
}
