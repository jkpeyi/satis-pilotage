<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Level of a probability, an impact or a scenario risk (L7).
 */
enum RiskLevel: string implements HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Medium => 'Moyen',
            self::High => 'Élevé',
        };
    }

    /**
     * Feminine form, for « probabilité ».
     */
    public function feminineLabel(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Medium => 'Moyenne',
            self::High => 'Élevée',
        };
    }
}
