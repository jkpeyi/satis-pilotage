<?php

namespace App\Enums;

enum SnippetKind: string
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
