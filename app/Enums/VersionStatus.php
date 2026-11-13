<?php

namespace App\Enums;

enum VersionStatus: string
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
