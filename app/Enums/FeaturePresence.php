<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Presence axis of an implementation (README d'intégration SPEC-001: feature_presence).
 */
enum FeaturePresence: string implements HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Unknown => 'Inconnu',
            self::NotApplicable => 'Non applicable',
        };
    }
}
