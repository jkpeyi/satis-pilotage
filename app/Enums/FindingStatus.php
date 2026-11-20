<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Human triage of an imported tool result (RG-18): it arrives to verify, then a person retains it
 * in a problem or rejects it as a false positive.
 */
enum FindingStatus: string implements HasLabel
{
    case ToVerify = 'to_verify';
    case Retained = 'retained';
    case FalsePositive = 'false_positive';

    public function label(): string
    {
        return match ($this) {
            self::ToVerify => 'À vérifier',
            self::Retained => 'Retenu',
            self::FalsePositive => 'Faux positif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ToVerify => 'amber',
            self::Retained => 'blue',
            self::FalsePositive => 'gray',
        };
    }

    /**
     * README verification status: a triaged result has been verified by a person.
     */
    public function verificationStatus(): VerificationStatus
    {
        return $this === self::ToVerify ? VerificationStatus::ToVerify : VerificationStatus::Verified;
    }
}
