<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Support status of a technology version found in a repository stack (EF-503).
 */
enum SupportStatus: string implements HasLabel
{
    case Supported = 'supported';
    case EndingSoon = 'ending_soon';
    case OutOfSupport = 'out_of_support';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Supported => 'Supporté',
            self::EndingSoon => 'Fin de support < 6 mois',
            self::OutOfSupport => 'Hors support',
            self::Unknown => 'Inconnu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Supported => 'green',
            self::EndingSoon => 'amber',
            self::OutOfSupport => 'red',
            self::Unknown => 'gray',
        };
    }
}
