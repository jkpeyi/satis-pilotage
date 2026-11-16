<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum SnippetKind: string implements HasLabel
{
    case Finding = 'finding';
    case Solution = 'solution';

    public function label(): string
    {
        return match ($this) {
            self::Finding => 'Constat',
            self::Solution => 'Solution',
        };
    }
}
