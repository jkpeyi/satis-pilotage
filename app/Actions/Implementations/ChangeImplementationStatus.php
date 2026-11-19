<?php

namespace App\Actions\Implementations;

use App\Enums\ImplementationStatus;
use App\Models\Implementation;
use App\Models\ImplementationStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes the status of one or many implementations and records the history (EF-206, RG-06).
 *
 * Reference Action: business logic lives here, Livewire components only call handle().
 */
final class ChangeImplementationStatus
{
    /**
     * Without a user (maintenance command), the change is recorded as made by the system.
     *
     * @param  list<int>  $implementationIds
     * @return int Number of implementations whose status actually changed.
     */
    public function handle(array $implementationIds, ImplementationStatus $status, ?User $user, ?string $note = null): int
    {
        return DB::transaction(function () use ($implementationIds, $status, $user, $note): int {
            $toChange = Implementation::query()
                ->whereKey($implementationIds)
                ->where('status', '!=', $status->value)
                ->lockForUpdate()
                ->get(['id', 'status']);

            if ($toChange->isEmpty()) {
                return 0;
            }

            $now = now();

            Implementation::query()
                ->whereKey($toChange->modelKeys())
                ->update([
                    'status' => $status->value,
                    'assessed_by' => $user?->id,
                    'assessed_at' => $now,
                    'updated_by' => $user?->id,
                    'updated_at' => $now,
                ]);

            ImplementationStatusChange::query()->insert(
                $toChange->map(fn (Implementation $implementation): array => [
                    'implementation_id' => $implementation->id,
                    'from_status' => $implementation->status->value,
                    'to_status' => $status->value,
                    'note' => $note,
                    'user_id' => $user?->id,
                    'created_at' => $now,
                ])->all()
            );

            return $toChange->count();
        });
    }
}
