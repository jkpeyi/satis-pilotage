<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use App\Support\Effort;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Architecture scenario of a refactoring strategy (L7), displayed OPT-0001.
 */
#[Fillable(['refactoring_strategy_id', 'name', 'description', 'benefits', 'drawbacks', 'effort_min_days', 'effort_max_days', 'risk_level', 'is_recommended', 'position'])]
class StrategyOption extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'OPT-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_recommended' => false,
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk_level' => RiskLevel::class,
            'is_recommended' => 'boolean',
            'effort_min_days' => 'decimal:1',
            'effort_max_days' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsTo<RefactoringStrategy, $this>
     */
    public function strategy(): BelongsTo
    {
        return $this->belongsTo(RefactoringStrategy::class, 'refactoring_strategy_id');
    }

    public function effortLabel(): ?string
    {
        return Effort::format($this->effort_min_days, $this->effort_max_days);
    }
}
