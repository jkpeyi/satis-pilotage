<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum VersionStatus: string implements HasLabel
{
    case InProduction = 'in_production';
    case InTesting = 'in_testing';

    public function label(): string
    {
        return match ($this) {
            self::InProduction => 'En production',
            self::InTesting => 'En recette',
        };
    }
}
