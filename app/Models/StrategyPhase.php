<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Migration phase of a refactoring strategy (L7), with its prerequisites and rollback.
 */
#[Fillable(['refactoring_strategy_id', 'name', 'description', 'prerequisites', 'rollback', 'position'])]
class StrategyPhase extends Model
{
    use Archivable, HasUserstamps;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    /**
     * @return BelongsTo<RefactoringStrategy, $this>
     */
    public function strategy(): BelongsTo
    {
        return $this->belongsTo(RefactoringStrategy::class, 'refactoring_strategy_id');
    }
}
