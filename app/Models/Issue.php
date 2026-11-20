<?php

namespace App\Models;

use App\Enums\Confidence;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use App\Support\Effort;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Problème", displayed as PB-0042 (RG-13). Concerns one or more implementations (RG-09).
 */
#[Fillable([
    'title', 'description', 'impact', 'type', 'severity', 'status', 'proposed_solution',
    'effort_min_days', 'effort_max_days', 'confidence', 'owner_id', 'repository_id', 'component_id', 'gitlab_issue_url',
])]
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
        'status' => 'identified',
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
            'effort_min_days' => 'decimal:1',
            'effort_max_days' => 'decimal:1',
            'confidence' => Confidence::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return BelongsTo<Component, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * "1,5 j/h" or "2 à 4 j/h"; null when not estimated.
     */
    public function effortLabel(): ?string
    {
        return Effort::format($this->effort_min_days, $this->effort_max_days);
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
