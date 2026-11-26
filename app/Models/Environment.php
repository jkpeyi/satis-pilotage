<?php

namespace App\Models;

use App\Enums\EnvironmentType;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Environment of a version (production, pre-production, test), displayed ENV-0001: its kind, its
 * name and notes. Releases and deployments are not tracked.
 */
#[Fillable(['version_id', 'type', 'name', 'notes'])]
class Environment extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'ENV-';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EnvironmentType::class,
        ];
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }
}
