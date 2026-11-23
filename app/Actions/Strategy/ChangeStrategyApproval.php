<?php

namespace App\Actions\Strategy;

use App\Enums\ApprovalStatus;
use App\Models\RefactoringStrategy;
use App\Models\User;
use DomainException;

/**
 * Approval circuit of a strategy (L7, README d'intégration): a draft is submitted for review, then
 * approved or rejected (a rejection says why); an approved or rejected strategy can be reopened as a
 * draft. The approval records who decided and when.
 */
final class ChangeStrategyApproval
{
    public function handle(RefactoringStrategy $strategy, ApprovalStatus $target, User $user, ?string $note = null): void
    {
        $current = $strategy->approval_status;
        $allowed = match ($target) {
            ApprovalStatus::InReview => $current === ApprovalStatus::Draft,
            ApprovalStatus::Approved, ApprovalStatus::Rejected => $current === ApprovalStatus::InReview,
            ApprovalStatus::Draft => in_array($current, [ApprovalStatus::InReview, ApprovalStatus::Approved, ApprovalStatus::Rejected], true),
        };
        if (! $allowed) {
            throw new DomainException("Passage impossible de « {$current->label()} » à « {$target->label()} ».");
        }

        if ($target === ApprovalStatus::InReview) {
            if (blank($strategy->context) || ! $strategy->options()->whereNull('archived_at')->exists()) {
                throw new DomainException('Avant la revue, décrivez le contexte et au moins un scénario.');
            }
        }

        if ($target === ApprovalStatus::Rejected && blank($note)) {
            throw new DomainException('Indiquez pourquoi la stratégie est rejetée.');
        }

        $decided = in_array($target, [ApprovalStatus::Approved, ApprovalStatus::Rejected], true);
        $strategy->forceFill([
            'approval_status' => $target,
            'approved_by' => $decided ? $user->id : null,
            'approved_at' => $decided ? now() : null,
            'approval_note' => $decided && filled($note) ? trim((string) $note) : null,
        ])->save();
    }
}
