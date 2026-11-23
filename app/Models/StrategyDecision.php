<?php

namespace App\Models;

use App\Enums\DecisionStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decision of a refactoring strategy (L7), displayed DEC-0001. A proposed decision is pending
 * (shown in L6); accepting or rejecting it records who decided and when.
 */
#[Fillable(['refactoring_strategy_id', 'strategy_option_id', 'title', 'rationale', 'status', 'decided_by', 'decided_on'])]
class StrategyDecision extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'DEC-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'proposed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DecisionStatus::class,
            'decided_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<RefactoringStrategy, $this>
     */
    public function strategy(): BelongsTo
    {
        return $this->belongsTo(RefactoringStrategy::class, 'refactoring_strategy_id');
    }

    /**
     * @return BelongsTo<StrategyOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(StrategyOption::class, 'strategy_option_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
