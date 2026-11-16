<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum EnvironmentType: string implements HasLabel
{
    case Production = 'production';
    case Staging = 'staging';
    case Testing = 'testing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Staging => 'Préproduction',
            self::Testing => 'Recette',
            self::Other => 'Autre',
        };
    }
}
