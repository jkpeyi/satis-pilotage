<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Diagnostic of a repository at an exact commit (SPEC-001 L4), displayed DIA-0001.
 */
#[Fillable(['repository_id', 'commit_sha', 'title', 'analyzed_on', 'objective', 'limitations'])]
class DiagnosticCampaign extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'DIA-';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'analyzed_on' => 'date',
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
     * @return HasMany<ToolRun, $this>
     */
    public function toolRuns(): HasMany
    {
        return $this->hasMany(ToolRun::class)->orderBy('id');
    }

    /**
     * @return HasMany<DiagnosticFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(DiagnosticFinding::class);
    }

    /**
     * @return HasMany<TestRun, $this>
     */
    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class)->orderBy('executed_on')->orderBy('id');
    }

    public function shortCommit(): string
    {
        return substr($this->commit_sha, 0, 8);
    }

    /**
     * GitLab link of a file at the campaign's commit, when the repository has a web URL.
     */
    public function fileUrl(?string $path, ?int $lineStart = null, ?int $lineEnd = null): ?string
    {
        $webUrl = $this->repository->web_url ?? null;
        if ($webUrl === null || $path === null || $path === '') {
            return null;
        }

        $lines = $lineStart !== null ? '#L'.$lineStart.($lineEnd !== null && $lineEnd !== $lineStart ? '-'.$lineEnd : '') : '';

        return rtrim($webUrl, '/').'/-/blob/'.$this->commit_sha.'/'.ltrim($path, '/').$lines;
    }

    /**
     * "DIA-0001 · Audit sécurité (DEP-0003 · satis/macro-api @ 1a2b3c4d)".
     */
    public function label(): string
    {
        return sprintf('%s · %s (%s @ %s)', $this->displayCode(), $this->title, $this->repository->path, $this->shortCommit());
    }
}
