<?php

namespace App\Models;

use App\Enums\EnvironmentType;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'version_id', 'repository_id', 'type', 'name', 'url', 'deployed_ref',
    'database_engine', 'database_version', 'php_version', 'server', 'notes',
])]
class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps;

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
        return $this->hasMany(Deployment::class)->orderByDesc('deployed_at')->orderByDesc('id');
    }
}
