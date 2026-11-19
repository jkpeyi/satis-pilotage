<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use App\Models\Concerns\Verifiable;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dated deployment of an environment: a release, a bare commit, or both.
 */
#[Fillable(['environment_id', 'release_id', 'commit_sha', 'deployed_at', 'evidence', 'notes'])]
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps, Verifiable;

    public const CODE_PREFIX = 'DPL-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'verification_status' => VerificationStatus::ToVerify->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deployed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Commit actually deployed: the one given, else the release's.
     */
    public function deployedCommit(): ?string
    {
        return $this->commit_sha ?? $this->release?->commit_sha;
    }

    /**
     * Short label, e.g. "v2.3.1 (a1b2c3d)" or "a1b2c3d".
     */
    public function reference(): string
    {
        $commit = $this->deployedCommit();
        $short = $commit !== null ? substr($commit, 0, 7) : null;

        return match (true) {
            $this->release !== null && $short !== null => $this->release->name.' ('.$short.')',
            $this->release !== null => $this->release->name,
            default => (string) $short,
        };
    }
}
