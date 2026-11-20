<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Confidence in an estimate (SPEC-001 L5).
 */
enum Confidence: string implements HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Medium => 'Moyenne',
            self::High => 'Élevée',
        };
    }
}
