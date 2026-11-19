<?php

namespace App\Models;

use App\Enums\IntegrationProtocol;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use App\Models\Concerns\Verifiable;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Flow from a component to another component or to an external system (SPEC-001 L3).
 * Exactly one target is set (CHECK constraint, no polymorphism).
 */
#[Fillable(['source_component_id', 'target_component_id', 'external_system', 'protocol', 'description', 'evidence_url'])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps, Verifiable;

    public const CODE_PREFIX = 'INT-';

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
            'protocol' => IntegrationProtocol::class,
        ];
    }

    /**
     * @return BelongsTo<Component, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'source_component_id');
    }

    /**
     * @return BelongsTo<Component, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'target_component_id');
    }

    public function isExternal(): bool
    {
        return $this->external_system !== null;
    }

    public function targetLabel(): string
    {
        return $this->target !== null
            ? $this->target->displayCode().' · '.$this->target->name
            : 'Externe : '.$this->external_system;
    }
}
