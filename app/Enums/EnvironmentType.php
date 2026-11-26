<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Kind of environment of a version. « Autre » only exists for older entries: it is not offered.
 */
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
            self::Staging => 'Pré-production',
            self::Testing => 'Test',
            self::Other => 'Autre',
        };
    }

    /**
     * Kinds offered when entering an environment.
     *
     * @return list<self>
     */
    public static function offered(): array
    {
        return [self::Production, self::Staging, self::Testing];
    }
}
