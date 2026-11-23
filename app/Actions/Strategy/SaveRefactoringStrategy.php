<?php

namespace App\Actions\Strategy;

use App\Models\Issue;
use App\Models\RefactoringStrategy;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a refactoring strategy (L7). The key findings it answers are problems of
 * the product. A content change brings a submitted or approved strategy back to draft (RG-20).
 */
final class SaveRefactoringStrategy
{
    public function __construct(private readonly ReopenStrategy $reopenStrategy) {}

    /**
     * @param  array{product_id: int, title: string, context: ?string, migration: ?string, safety: ?string, saas_notes: ?string}  $data
     * @param  list<int>  $issueIds
     */
    public function handle(array $data, ?RefactoringStrategy $strategy = null, array $issueIds = []): RefactoringStrategy
    {
        $issueIds = array_values(array_unique($issueIds));
        if ($issueIds !== [] && Issue::query()->whereKey($issueIds)
            ->whereHas('implementations.version', fn ($query) => $query->where('product_id', $data['product_id']))->count() !== count($issueIds)) {
            throw new DomainException('Les constats retenus doivent être des problèmes de ce produit.');
        }

        if ($strategy !== null && $strategy->product_id !== $data['product_id'] && ($strategy->options()->exists() || $strategy->decisions()->exists())) {
            throw new DomainException('Cette stratégie a déjà des scénarios ou des décisions : elle reste attachée à son produit.');
        }

        return DB::transaction(function () use ($data, $strategy, $issueIds): RefactoringStrategy {
            $strategy ??= new RefactoringStrategy;
            $strategy->fill($data);
            $changed = $strategy->exists && ($strategy->isDirty() || $strategy->issues()->pluck('issues.id')->sort()->values()->all() !== collect($issueIds)->sort()->values()->all());
            $strategy->save();
            $strategy->issues()->sync($issueIds);

            if ($changed) {
                $this->reopenStrategy->handle($strategy);
            }

            return $strategy;
        });
    }
}
