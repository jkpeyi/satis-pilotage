<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Finding status of the README d'intégration SPEC-001, plus « abandonné » for what is decided
 * not to be treated.
 */
enum IssueStatus: string implements HasLabel
{
    case Identified = 'identified';
    case Qualified = 'qualified';
    case Planned = 'planned';
    case Treated = 'treated';
    case Verified = 'verified';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Identified => 'Identifié',
            self::Qualified => 'Qualifié',
            self::Planned => 'Planifié',
            self::Treated => 'Traité',
            self::Verified => 'Vérifié',
            self::Dropped => 'Abandonné',
        };
    }

    /**
     * Still to be dealt with: counted as open debt.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Identified, self::Qualified, self::Planned], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(fn (self $status): string => $status->value, array_filter(self::cases(), fn (self $status): bool => $status->isOpen())));
    }
}
