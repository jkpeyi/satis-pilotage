<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum RuleSource: string implements HasLabel
{
    case Code = 'code';
    case Documentation = 'documentation';
    case Interview = 'interview';
    case Observation = 'observation';

    public function label(): string
    {
        return match ($this) {
            self::Code => 'Code',
            self::Documentation => 'Documentation',
            self::Interview => 'Entretien',
            self::Observation => 'Observation',
        };
    }
}
