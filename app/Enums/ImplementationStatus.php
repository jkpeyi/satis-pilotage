<?php

namespace App\Enums;

enum ImplementationStatus: string
{
    case NotAssessed = 'not_assessed';
    case Operational = 'operational';
    case NeedsImprovement = 'needs_improvement';
    case Defective = 'defective';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::NotAssessed => 'Non évalué',
            self::Operational => 'Opérationnel',
            self::NeedsImprovement => 'À améliorer',
            self::Defective => 'Défaillant',
            self::Missing => 'Absent',
        };
    }

    /**
     * Badge color in the UI. Semantic: green ok, amber to improve, red defective, violet missing.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotAssessed => 'gray',
            self::Operational => 'green',
            self::NeedsImprovement => 'amber',
            self::Defective => 'red',
            self::Missing => 'violet',
        };
    }
}
