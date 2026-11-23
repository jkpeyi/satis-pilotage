<?php

namespace App\Actions\Strategy;

use App\Models\RefactoringStrategy;
use App\Models\StrategyOption;
use App\Models\StrategyPhase;
use App\Models\StrategyRisk;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a scenario, a migration phase or a risk of a strategy (L7). Only one
 * scenario is recommended. The content changed: a submitted or approved strategy goes back to
 * draft (RG-20).
 */
final class SaveStrategyItem
{
    public function __construct(private readonly ReopenStrategy $reopenStrategy) {}

    /**
     * @template T of StrategyOption|StrategyPhase|StrategyRisk
     *
     * @param  T  $item  a new or existing item
     * @param  array<string, mixed>  $data
     * @return T
     */
    public function handle(RefactoringStrategy $strategy, StrategyOption|StrategyPhase|StrategyRisk $item, array $data): StrategyOption|StrategyPhase|StrategyRisk
    {
        return DB::transaction(function () use ($strategy, $item, $data): StrategyOption|StrategyPhase|StrategyRisk {
            if (! $item->exists && ! $item instanceof StrategyRisk) {
                $data['position'] = (int) $item->newQuery()->where('refactoring_strategy_id', $strategy->id)->max('position') + 1;
            }

            $item->fill([...$data, 'refactoring_strategy_id' => $strategy->id]);
            $changed = ! $item->exists || $item->isDirty();
            $item->save();

            if ($item instanceof StrategyOption && $item->is_recommended) {
                StrategyOption::query()->where('refactoring_strategy_id', $strategy->id)->whereKeyNot($item->id)->update(['is_recommended' => false]);
            }

            if ($changed) {
                $this->reopenStrategy->handle($strategy);
            }

            return $item;
        });
    }
}
