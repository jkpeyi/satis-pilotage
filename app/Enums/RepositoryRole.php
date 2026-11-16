<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum RepositoryRole: string implements HasLabel
{
    case Backend = 'backend';
    case Frontend = 'frontend';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Backend => 'Backend',
            self::Frontend => 'Frontend',
            self::Other => 'Autre',
        };
    }
}
