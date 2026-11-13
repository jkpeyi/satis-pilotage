<?php

namespace App\Models;

use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Règle métier", displayed as RM-0042 (RG-13).
 */
#[Fillable(['implementation_id', 'origin_rule_id', 'title', 'description', 'source', 'status'])]
class BusinessRule extends Model
{
    use Archivable, HasUserstamps;

    public const CODE_PREFIX = 'RM-';

    /**
     * Same defaults as the database, so that a new model is complete before refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RuleSource::class,
            'status' => RuleStatus::class,
        ];
    }

    /**
     * Code shown to users, e.g. RM-0042 (RG-13).
     */
    public function displayCode(): string
    {
        return sprintf('%s%04d', self::CODE_PREFIX, $this->id);
    }

    /**
     * @return BelongsTo<Implementation, $this>
     */
    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    /**
     * Rule this one was copied from (RG-11).
     *
     * @return BelongsTo<BusinessRule, $this>
     */
    public function originRule(): BelongsTo
    {
        return $this->belongsTo(BusinessRule::class, 'origin_rule_id');
    }

    /**
     * @return HasMany<BusinessRule, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(BusinessRule::class, 'origin_rule_id');
    }

    /**
     * @return HasMany<CodeSnippet, $this>
     */
    public function codeSnippets(): HasMany
    {
        return $this->hasMany(CodeSnippet::class);
    }
}
