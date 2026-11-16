<?php

namespace App\Observers;

use App\Models\Implementation;
use App\Models\ImplementationStatusChange;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the status history when an implementation is saved through Eloquent (RG-06).
 * Bulk updates bypass model events: ChangeImplementationStatus writes the history itself.
 */
class ImplementationObserver
{
    public function updated(Implementation $implementation): void
    {
        if (! $implementation->wasChanged('status')) {
            return;
        }

        ImplementationStatusChange::create([
            'implementation_id' => $implementation->id,
            'from_status' => $implementation->getOriginal('status'),
            'to_status' => $implementation->status,
            'user_id' => Auth::id(),
        ]);
    }
}
