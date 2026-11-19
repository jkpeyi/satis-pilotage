<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Verification of a piece of data (README d'intégration SPEC-001): distinct from document approval.
 */
enum VerificationStatus: string implements HasLabel
{
    case ToVerify = 'to_verify';
    case Verified = 'verified';
    case Unverifiable = 'unverifiable';

    public function label(): string
    {
        return match ($this) {
            self::ToVerify => 'À vérifier',
            self::Verified => 'Vérifié',
            self::Unverifiable => 'Non vérifiable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ToVerify => 'amber',
            self::Verified => 'green',
            self::Unverifiable => 'gray',
        };
    }
}
