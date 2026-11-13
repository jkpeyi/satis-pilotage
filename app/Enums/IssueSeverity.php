<?php

namespace App\Enums;

enum IssueSeverity: string
{
    case Blocking = 'blocking';
    case Major = 'major';
    case Minor = 'minor';

    public function label(): string
    {
        return match ($this) {
            self::Blocking => 'Bloquante',
            self::Major => 'Majeure',
            self::Minor => 'Mineure',
        };
    }
}
