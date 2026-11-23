<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Risk of a refactoring strategy (L7), displayed RSK-0001.
 */
#[Fillable(['refactoring_strategy_id', 'description', 'probability', 'impact', 'mitigation', 'owner_id'])]
class StrategyRisk extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'RSK-';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'probability' => RiskLevel::class,
            'impact' => RiskLevel::class,
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
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
