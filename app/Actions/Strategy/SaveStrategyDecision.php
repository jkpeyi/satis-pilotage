<?php

namespace App\Actions\Strategy;

use App\Enums\DecisionStatus;
use App\Models\RefactoringStrategy;
use App\Models\StrategyDecision;
use App\Models\StrategyOption;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * Records a decision of a strategy (L7). Accepting or rejecting it records who decided and when;
 * a proposed decision is pending. Decisions are the outcome of the strategy: they do not change
 * its approval.
 */
final class SaveStrategyDecision
{
    /**
     * @param  array{strategy_option_id: ?int, title: string, rationale: ?string, status: DecisionStatus, decided_on: ?string}  $data
     */
    public function handle(RefactoringStrategy $strategy, array $data, ?StrategyDecision $decision = null): StrategyDecision
    {
        if ($data['strategy_option_id'] !== null && ! StrategyOption::query()->whereKey($data['strategy_option_id'])->where('refactoring_strategy_id', $strategy->id)->exists()) {
            throw new DomainException('Le scénario choisi appartient à une autre stratégie.');
        }

        if ($data['status'] !== DecisionStatus::Proposed && blank($data['rationale'])) {
            throw new DomainException('Une décision prise indique sa justification.');
        }

        $decided = $data['status'] !== DecisionStatus::Proposed;
        $decision ??= new StrategyDecision(['refactoring_strategy_id' => $strategy->id]);
        $decision->fill([
            ...$data,
            'decided_by' => $decided ? ($decision->status === $data['status'] && $decision->decided_by !== null ? $decision->decided_by : Auth::id()) : null,
            'decided_on' => $decided ? ($data['decided_on'] ?? today()->toDateString()) : null,
        ])->save();

        return $decision;
    }
}
