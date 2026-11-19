<?php

namespace App\Models;

use App\Enums\RepositoryRole;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GitLab project of a version. Entered manually while GitLab integration is disabled.
 *
 * @property array<string, string>|null $stack component => version, e.g. ['php' => '8.1', 'laravel' => '9.52.16']
 * @property list<string>|null $active_branches
 */
#[Fillable([
    'version_id', 'path', 'gitlab_project_id', 'web_url', 'role',
    'production_branch', 'active_branches', 'stack', 'stack_detected_at',
])]
class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps;

    public const CODE_PREFIX = 'DEP-';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => RepositoryRole::class,
            'active_branches' => 'array',
            'stack' => 'array',
            'stack_detected_at' => 'datetime',
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
     * @return HasMany<Environment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    /**
     * @return HasMany<CodeSnippet, $this>
     */
    public function codeSnippets(): HasMany
    {
        return $this->hasMany(CodeSnippet::class);
    }
}
