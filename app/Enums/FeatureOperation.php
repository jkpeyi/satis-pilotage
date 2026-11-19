<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Operation axis of an implementation (README d'intégration SPEC-001: feature_operation).
 */
enum FeatureOperation: string implements HasLabel
{
    case Operational = 'operational';
    case Defective = 'defective';
    case Partial = 'partial';
    case NotTested = 'not_tested';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Opérationnel',
            self::Defective => 'Défaillant',
            self::Partial => 'Partiel',
            self::NotTested => 'Non testé',
            self::NotApplicable => 'Non applicable',
        };
    }
}
