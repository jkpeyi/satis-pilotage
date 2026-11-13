<?php

namespace App\Actions\Issues;

use App\Models\Implementation;
use App\Models\Issue;
use DomainException;

/**
 * An issue must keep at least one implementation (RG-09).
 */
final class UnlinkIssueFromImplementation
{
    public function handle(Issue $issue, Implementation $implementation): void
    {
        if ($issue->implementations()->count() <= 1) {
            throw new DomainException('Un problème doit concerner au moins un élément : liez-le à un autre élément avant de retirer celui-ci.');
        }

        $issue->implementations()->detach($implementation->id);
    }
}
