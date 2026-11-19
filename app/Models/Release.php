<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Software release of a repository: a tag and the commit it points to.
 */
#[Fillable(['repository_id', 'name', 'commit_sha', 'released_on', 'notes'])]
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps;

    public const CODE_PREFIX = 'REL-';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'released_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }
}
