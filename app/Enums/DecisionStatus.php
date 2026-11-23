<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Status of a decision of a refactoring strategy (L7). A proposed decision is pending.
 */
enum DecisionStatus: string implements HasLabel
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'En attente',
            self::Accepted => 'Acceptée',
            self::Rejected => 'Rejetée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Proposed => 'amber',
            self::Accepted => 'green',
            self::Rejected => 'gray',
        };
    }
}
