<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum IssueStatus: string implements HasLabel
{
    case Open = 'open';
    case Analyzed = 'analyzed';
    case SolutionProposed = 'solution_proposed';
    case Fixed = 'fixed';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::Analyzed => 'Analysé',
            self::SolutionProposed => 'Solution proposée',
            self::Fixed => 'Corrigé',
            self::Dropped => 'Abandonné',
        };
    }
}
