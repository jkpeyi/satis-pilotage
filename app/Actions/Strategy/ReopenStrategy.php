<?php

namespace App\Actions\Strategy;

use App\Enums\ApprovalStatus;
use App\Models\RefactoringStrategy;

/**
 * RG-20: the content of a strategy under review, approved or rejected changed; the approval no
 * longer applies to it, so it goes back to draft.
 */
final class ReopenStrategy
{
    public function handle(RefactoringStrategy $strategy): void
    {
        if ($strategy->approval_status === ApprovalStatus::Draft) {
            return;
        }

        $strategy->forceFill([
            'approval_status' => ApprovalStatus::Draft,
            'approved_by' => null,
            'approved_at' => null,
            'approval_note' => null,
        ])->save();
    }
}
