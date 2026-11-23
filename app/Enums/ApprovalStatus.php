<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Approval of a decision document (README d'intégration: brouillon, en revue, validé, rejeté).
 */
enum ApprovalStatus: string implements HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::InReview => 'En revue',
            self::Approved => 'Validé',
            self::Rejected => 'Rejeté',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
