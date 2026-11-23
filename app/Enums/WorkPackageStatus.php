<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Status of a work package of the remediation roadmap (L8).
 */
enum WorkPackageStatus: string implements HasLabel
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planifié',
            self::InProgress => 'En cours',
            self::Done => 'Livré',
            self::Cancelled => 'Annulé',
        };
    }
}
