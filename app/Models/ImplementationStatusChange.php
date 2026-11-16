<?php

namespace App\Models;

use App\Enums\ImplementationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only history of implementation status changes (RG-06).
 */
#[Fillable(['implementation_id', 'from_status', 'to_status', 'note', 'user_id'])]
class ImplementationStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Status history is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Status history is append-only.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ImplementationStatus::class,
            'to_status' => ImplementationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Implementation, $this>
     */
    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
