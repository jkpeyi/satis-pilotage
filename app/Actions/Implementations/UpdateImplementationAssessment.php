<?php

namespace App\Actions\Implementations;

use App\Enums\ImplementationStatus;
use App\Enums\VerificationStatus;
use App\Models\Implementation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves the assessment of an implementation from its sheet (EF-205): status, deviation, note,
 * and the verification of that assessment (SPEC-001 L2: verified_by).
 *
 * The status goes through ChangeImplementationStatus so that the history carries the note (RG-06).
 * The deviation is written relative to the catalog standard description (RG-07).
 */
final class UpdateImplementationAssessment
{
    public function __construct(private readonly ChangeImplementationStatus $changeStatus) {}

    /**
     * @param  array{status: ImplementationStatus, deviation: ?string, assessment_note: ?string, verification_status: VerificationStatus}  $data
     */
    public function handle(Implementation $implementation, array $data, User $user): void
    {
        DB::transaction(function () use ($implementation, $data, $user): void {
            $this->changeStatus->handle([$implementation->id], $data['status'], $user, $data['assessment_note']);

            $implementation->refresh()->fill([
                'deviation' => $data['deviation'],
                'assessment_note' => $data['assessment_note'],
            ]);

            if ($implementation->isDirty()) {
                $implementation->forceFill(['assessed_by' => $user->id, 'assessed_at' => now()]);
            }

            $implementation->applyVerificationStatus($data['verification_status'], $user->id);

            if ($implementation->isDirty()) {
                $implementation->save();
            }
        });
    }
}
