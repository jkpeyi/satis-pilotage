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
     * GitLab path of a project from its full URL, e.g. "satis/macro-api" from
     * "https://gitlab.satisworld.com/satis/macro-api" (also from a ".git" or "/-/tree/main" URL).
     * The path is the repository's name everywhere in the application.
     */
    public static function pathFromUrl(string $url): ?string
    {
        $path = (string) parse_url(trim($url), PHP_URL_PATH);
        $path = (string) preg_replace('#/-/.*$#', '', $path);
        $path = (string) preg_replace('#\.git$#', '', trim($path, '/'));

        return $path !== '' ? $path : null;
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    /**
     * @return HasMany<CodeSnippet, $this>
     */
    public function codeSnippets(): HasMany
    {
        return $this->hasMany(CodeSnippet::class);
    }

    /**
     * @return HasMany<DiagnosticCampaign, $this>
     */
    public function diagnosticCampaigns(): HasMany
    {
        return $this->hasMany(DiagnosticCampaign::class);
    }
}
