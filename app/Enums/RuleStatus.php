<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum RuleStatus: string implements HasLabel
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
