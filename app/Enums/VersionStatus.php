<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum VersionStatus: string implements HasLabel
{
    case InProduction = 'in_production';
    case InTesting = 'in_testing';
    // A future perspective (e.g. Satis_SaaS): never counted as a production application.
    case Future = 'future';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::InProduction => 'En production',
            self::InTesting => 'En recette',
            self::Future => 'Perspective future',
            self::Retired => 'Retirée',
        };
    }
}
