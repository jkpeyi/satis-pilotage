<?php

namespace App\Enums;

enum EnvironmentType: string
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
