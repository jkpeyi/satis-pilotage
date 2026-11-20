<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Level given by the tool to an imported result (SARIF levels). It is the tool's opinion, not
 * the gravity of a problem: that is decided by a person when the result is retained.
 */
enum FindingLevel: string implements HasLabel
{
    case Error = 'error';
    case Warning = 'warning';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'Erreur',
            self::Warning => 'Avertissement',
            self::Note => 'Remarque',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Error => 'red',
            self::Warning => 'amber',
            self::Note => 'gray',
        };
    }
}
