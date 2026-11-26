<?php

namespace App\Models;

use App\Enums\ComponentType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use App\Models\Concerns\Verifiable;
use Database\Factories\ComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Technical component of an application (SPEC-001 L3): an API, a front end, a batch…
 */
#[Fillable(['version_id', 'repository_id', 'name', 'type', 'technology', 'technology_version', 'description', 'evidence_url'])]
class Component extends Model
{
    /** @use HasFactory<ComponentFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps, Verifiable;

    public const CODE_PREFIX = 'CMP-';

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
            'type' => ComponentType::class,
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
     * "Laravel 9.52.16", or null when the technology is unknown.
     */
    public function technologyLabel(): ?string
    {
        return $this->technology !== null ? trim($this->technology.' '.$this->technology_version) : null;
    }
}
