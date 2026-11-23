<?php

namespace App\Actions\Remediation;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Models\Issue;
use App\Models\RemediationAction;
use App\Models\WorkPackage;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a remediation action (L8). The problem it treats concerns a version of its
 * product; its work package and its dependencies belong to the same product, without cycle.
 * An action is done only with acceptance criteria and test evidence (RG-19); its completion date
 * is recorded then.
 */
final class SaveRemediationAction
{
    /**
     * @param  array{product_id: int, work_package_id: ?int, issue_id: ?int, title: string, description: ?string, priority: ActionPriority, status: ActionStatus, owner_id: ?int, starts_on: ?string, due_on: ?string, effort_days: ?string, acceptance_criteria: ?string, test_evidence: ?string, gitlab_issue_url: ?string}  $data
     * @param  list<int>  $dependencyIds
     */
    public function handle(array $data, ?RemediationAction $action = null, array $dependencyIds = []): RemediationAction
    {
        if ($data['starts_on'] !== null && $data['due_on'] !== null && $data['due_on'] < $data['starts_on']) {
            throw new DomainException('L’échéance précède le début de l’action.');
        }

        if ($data['issue_id'] !== null && ! Issue::query()->whereKey($data['issue_id'])
            ->whereHas('implementations.version', fn ($query) => $query->where('product_id', $data['product_id']))->exists()) {
            throw new DomainException('Ce problème ne concerne aucune version de ce produit.');
        }

        if ($data['work_package_id'] !== null && ! WorkPackage::query()->whereKey($data['work_package_id'])->where('product_id', $data['product_id'])->exists()) {
            throw new DomainException('Ce lot appartient à un autre produit.');
        }

        if ($data['status'] === ActionStatus::Done && (blank($data['acceptance_criteria']) || blank($data['test_evidence']))) {
            throw new DomainException('Une action n’est terminée qu’avec ses critères de réception et la preuve de test qui les vérifie.');
        }

        $dependencyIds = array_values(array_unique($dependencyIds));
        $this->checkDependencies($data['product_id'], $action, $dependencyIds);

        $data['completed_on'] = match (true) {
            $data['status'] !== ActionStatus::Done => null,
            $action?->status === ActionStatus::Done => $action->completed_on?->toDateString(),
            default => today()->toDateString(),
        };

        return DB::transaction(function () use ($data, $action, $dependencyIds): RemediationAction {
            $action ??= new RemediationAction;
            $action->fill($data)->save();
            $action->dependencies()->syncWithPivotValues($dependencyIds, ['created_by' => Auth::id(), 'created_at' => now()]);

            return $action;
        });
    }

    /**
     * Same product, not itself, and no cycle: none of the dependencies may (transitively) depend
     * on this action.
     *
     * @param  list<int>  $dependencyIds
     */
    private function checkDependencies(int $productId, ?RemediationAction $action, array $dependencyIds): void
    {
        if ($dependencyIds === []) {
            return;
        }

        if ($action !== null && in_array($action->id, $dependencyIds, true)) {
            throw new DomainException('Une action ne peut pas dépendre d’elle-même.');
        }

        if (RemediationAction::query()->whereKey($dependencyIds)->where('product_id', $productId)->count() !== count($dependencyIds)) {
            throw new DomainException('Une action ne dépend que d’actions du même produit.');
        }

        if ($action === null) {
            return;
        }

        $edges = DB::table('remediation_action_dependencies')->get(['action_id', 'depends_on_id'])
            ->groupBy('action_id')->map(fn ($rows) => $rows->pluck('depends_on_id')->map(fn ($id): int => (int) $id)->all());
        $stack = $dependencyIds;
        $seen = [];
        while ($stack !== []) {
            $current = array_pop($stack);
            if ($current === $action->id) {
                throw new DomainException('Ces dépendances créeraient un cycle : une des actions dépend déjà de celle-ci.');
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            array_push($stack, ...($edges[$current] ?? []));
        }
    }
}
