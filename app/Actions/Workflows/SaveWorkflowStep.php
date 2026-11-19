<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowStepKind;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a workflow step, with the business rules that apply to it (SPEC-001 L2) and
 * its exits (RG-17). A new step goes last. Linked rules must belong to a version of the
 * workflow's product. Exits lead to active steps of the same workflow:
 * - an action has at most one (« ensuite »); without it, it goes on to the next step;
 * - every exit of a decision carries its condition (« Oui », « Non », « Montant > 1 M »);
 * - an end has none.
 */
final class SaveWorkflowStep
{
    /**
     * @param  array{workflow_id: int, kind: WorkflowStepKind, name: string, actor: ?string, feature_id: ?int, description: ?string, business_rule_ids: list<int>, transitions: list<array{label: ?string, target_step_id: int}>}  $data
     */
    public function handle(array $data, ?WorkflowStep $step = null): WorkflowStep
    {
        $workflow = Workflow::query()->findOrFail($data['workflow_id']);

        if ($data['feature_id'] !== null && ! Feature::query()->whereKey($data['feature_id'])->exists()) {
            throw new DomainException('Fonctionnalité introuvable.');
        }

        $ruleIds = array_values(array_unique($data['business_rule_ids']));
        $validRules = BusinessRule::query()
            ->whereKey($ruleIds)
            ->whereHas('implementation.version', fn ($query) => $query->where('product_id', $workflow->product_id))
            ->count();

        if ($validRules !== count($ruleIds)) {
            throw new DomainException('Une règle liée n’appartient pas à une version de ce produit.');
        }

        $this->checkTransitions($data['kind'], $data['transitions'], $workflow, $step);

        return DB::transaction(function () use ($data, $step, $workflow, $ruleIds): WorkflowStep {
            $step ??= new WorkflowStep([
                'position' => (int) $workflow->steps()->max('position') + 1,
            ]);

            $step->fill([
                'workflow_id' => $workflow->id,
                'kind' => $data['kind'],
                'name' => $data['name'],
                'actor' => $data['actor'],
                'feature_id' => $data['feature_id'],
                'description' => $data['description'],
            ])->save();

            $step->businessRules()->sync($ruleIds);

            $step->transitions()->delete();
            foreach ($data['transitions'] as $position => $transition) {
                $step->transitions()->create([
                    'target_step_id' => $transition['target_step_id'],
                    'label' => $data['kind'] === WorkflowStepKind::Decision ? $transition['label'] : null,
                    'position' => $position,
                ]);
            }

            return $step;
        });
    }

    /**
     * @param  list<array{label: ?string, target_step_id: int}>  $transitions
     */
    private function checkTransitions(WorkflowStepKind $kind, array $transitions, Workflow $workflow, ?WorkflowStep $step): void
    {
        if ($kind === WorkflowStepKind::End && $transitions !== []) {
            throw new DomainException('Une étape de fin n’a pas de sortie.');
        }

        if ($kind === WorkflowStepKind::Action && count($transitions) > 1) {
            throw new DomainException('Une action n’a qu’une étape suivante : utilisez une décision pour plusieurs sorties.');
        }

        $targetIds = array_map(fn (array $transition): int => $transition['target_step_id'], $transitions);
        $valid = $workflow->steps()->active()->whereKey($targetIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($transitions as $transition) {
            if (! in_array($transition['target_step_id'], $valid, true)) {
                throw new DomainException('Une sortie mène à une étape qui n’appartient pas à ce workflow ou qui est archivée.');
            }

            if ($kind === WorkflowStepKind::Action && $step !== null && $transition['target_step_id'] === $step->id) {
                throw new DomainException('Une action ne peut pas mener à elle-même.');
            }

            if ($kind === WorkflowStepKind::Decision && trim((string) $transition['label']) === '') {
                throw new DomainException('Chaque sortie d’une décision indique sa condition (ex. « Oui », « Non »).');
            }
        }
    }
}
