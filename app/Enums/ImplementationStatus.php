<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * State of an implementation, on the two axes of the README d'intégration SPEC-001: presence and
 * operation. One value per valid combination, so an impossible pair (absent but operational)
 * cannot be stored and the history keeps a single column. Operation only applies to a present item.
 *
 * The order of the cases is the status index of the comparison matrix: append new cases at the end.
 */
enum ImplementationStatus: string implements HasLabel
{
    case Unknown = 'unknown';
    case Operational = 'operational';
    case Partial = 'partial';
    case Defective = 'defective';
    case Absent = 'absent';
    case NotTested = 'not_tested';
    case PresentNotApplicable = 'present_not_applicable';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Inconnu',
            self::Operational => 'Opérationnel',
            self::Partial => 'Partiel',
            self::Defective => 'Défaillant',
            self::Absent => 'Absent',
            self::NotTested => 'Présent, non testé',
            self::PresentNotApplicable => 'Présent, sans objet',
            self::NotApplicable => 'Non applicable',
        };
    }

    /**
     * Badge color in the UI: green ok, amber partial, red defective, violet absent, blue not tested.
     */
    public function color(): string
    {
        return match ($this) {
            self::Unknown, self::NotApplicable, self::PresentNotApplicable => 'gray',
            self::Operational => 'green',
            self::Partial => 'amber',
            self::Defective => 'red',
            self::Absent => 'violet',
            self::NotTested => 'blue',
        };
    }

    public function presence(): FeaturePresence
    {
        return match ($this) {
            self::Unknown => FeaturePresence::Unknown,
            self::Absent => FeaturePresence::Absent,
            self::NotApplicable => FeaturePresence::NotApplicable,
            default => FeaturePresence::Present,
        };
    }

    public function operation(): FeatureOperation
    {
        return match ($this) {
            self::Operational => FeatureOperation::Operational,
            self::Partial => FeatureOperation::Partial,
            self::Defective => FeatureOperation::Defective,
            self::Unknown, self::NotTested => FeatureOperation::NotTested,
            self::Absent, self::NotApplicable, self::PresentNotApplicable => FeatureOperation::NotApplicable,
        };
    }

    /**
     * The state for a presence and an operation; the operation is ignored unless the item is present.
     */
    public static function fromAxes(FeaturePresence $presence, FeatureOperation $operation): self
    {
        return match ($presence) {
            FeaturePresence::Unknown => self::Unknown,
            FeaturePresence::Absent => self::Absent,
            FeaturePresence::NotApplicable => self::NotApplicable,
            FeaturePresence::Present => match ($operation) {
                FeatureOperation::Operational => self::Operational,
                FeatureOperation::Partial => self::Partial,
                FeatureOperation::Defective => self::Defective,
                FeatureOperation::NotTested => self::NotTested,
                FeatureOperation::NotApplicable => self::PresentNotApplicable,
            },
        };
    }

    /**
     * Only an expected but absent item counts as a gap in reports (RG-05).
     */
    public function isGap(): bool
    {
        return $this === self::Absent;
    }
}
