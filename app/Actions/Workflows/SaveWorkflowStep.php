<?php

namespace App\Actions\Workflows;

use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a workflow step, with the business rules that apply to it (SPEC-001 L2).
 * A new step goes last. Linked rules must belong to a version of the workflow's product.
 */
final class SaveWorkflowStep
{
    /**
     * @param  array{workflow_id: int, name: string, actor: ?string, feature_id: ?int, description: ?string, business_rule_ids: list<int>}  $data
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

        return DB::transaction(function () use ($data, $step, $workflow, $ruleIds): WorkflowStep {
            $step ??= new WorkflowStep([
                'position' => (int) $workflow->steps()->max('position') + 1,
            ]);

            $step->fill([
                'workflow_id' => $workflow->id,
                'name' => $data['name'],
                'actor' => $data['actor'],
                'feature_id' => $data['feature_id'],
                'description' => $data['description'],
            ])->save();

            $step->businessRules()->sync($ruleIds);

            return $step;
        });
    }
}
