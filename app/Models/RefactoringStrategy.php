<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Refactoring strategy of a product (L7), displayed STR-0001: written and approved by people,
 * never generated from metrics (README d'intégration). Any change to an approved strategy brings
 * it back to draft (RG-20).
 */
#[Fillable(['product_id', 'title', 'context', 'migration', 'safety', 'saas_notes', 'approval_status', 'approved_by', 'approved_at', 'approval_note'])]
class RefactoringStrategy extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'STR-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'approval_status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Key findings of the debt register the strategy answers.
     *
     * @return BelongsToMany<Issue, $this>
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class);
    }

    /**
     * @return HasMany<StrategyOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(StrategyOption::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<StrategyPhase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(StrategyPhase::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<StrategyRisk, $this>
     */
    public function risks(): HasMany
    {
        return $this->hasMany(StrategyRisk::class)->orderBy('id');
    }

    /**
     * @return HasMany<StrategyDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(StrategyDecision::class)->orderBy('id');
    }
}
