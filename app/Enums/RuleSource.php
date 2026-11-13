<?php

namespace App\Enums;

enum RuleSource: string
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
