<?php

namespace App\Enums;

enum RuleStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À valider',
            self::Validated => 'Validée',
        };
    }
}
