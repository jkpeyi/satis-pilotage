<?php

namespace App\Models\Concerns;

use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Verification status of a record's data, with who decided it and when.
 *
 * @property VerificationStatus $verification_status
 *
 * @mixin Model
 */
trait Verifiable
{
    public function initializeVerifiable(): void
    {
        $this->mergeCasts([
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ]);
    }

    /**
     * Sets the status; a decision (verified or unverifiable) records the current user and time.
     */
    public function applyVerificationStatus(VerificationStatus $status): void
    {
        if ($this->exists && $this->getAttribute('verification_status') === $status) {
            return;
        }

        $decided = $status !== VerificationStatus::ToVerify;
        $this->setAttribute('verification_status', $status);
        $this->setAttribute('verified_by', $decided ? Auth::id() : null);
        $this->setAttribute('verified_at', $decided ? now() : null);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
