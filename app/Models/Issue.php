<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Problème", displayed as PB-0042 (RG-13). Concerns one or more implementations (RG-09).
 */
#[Fillable(['title', 'description', 'type', 'severity', 'status', 'proposed_solution', 'effort_days'])]
class Issue extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'PB-';

    /**
     * Same defaults as the database, so that a new model is complete before refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'effort_days' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsToMany<Implementation, $this>
     */
    public function implementations(): BelongsToMany
    {
        return $this->belongsToMany(Implementation::class);
    }

    /**
     * @return HasMany<CodeSnippet, $this>
     */
    public function codeSnippets(): HasMany
    {
        return $this->hasMany(CodeSnippet::class);
    }
}
