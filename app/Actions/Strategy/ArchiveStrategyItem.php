<?php

namespace App\Actions\Strategy;

use App\Models\StrategyDecision;
use App\Models\StrategyOption;
use App\Models\StrategyPhase;
use App\Models\StrategyRisk;

/**
 * Archives or restores an item of a strategy (RG-12). Removing a scenario, a phase or a risk
 * changes the content: a submitted or approved strategy goes back to draft (RG-20).
 */
final class ArchiveStrategyItem
{
    public function __construct(private readonly ReopenStrategy $reopenStrategy) {}

    public function handle(StrategyOption|StrategyPhase|StrategyRisk|StrategyDecision $item, bool $archive = true): void
    {
        $archive ? $item->archive() : $item->unarchive();

        if (! $item instanceof StrategyDecision) {
            $this->reopenStrategy->handle($item->strategy);
        }
    }
}
