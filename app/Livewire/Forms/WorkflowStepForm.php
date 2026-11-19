<?php

namespace App\Livewire\Forms;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Validation\Rule;
use Livewire\Form;

class WorkflowStepForm extends Form
{
    public ?WorkflowStep $step = null;

    public int $workflowId = 0;

    public string $name = '';

    public string $actor = '';

    public string $feature_id = '';

    public string $description = '';

    /** @var list<string> */
    public array $business_rule_ids = [];

    public function forWorkflow(Workflow $workflow): void
    {
        $this->reset();
        $this->resetValidation();
        $this->workflowId = $workflow->id;
    }

    public function setStep(WorkflowStep $step): void
    {
        $this->resetValidation();
        $this->step = $step;
        $this->workflowId = $step->workflow_id;
        $this->name = $step->name;
        $this->actor = (string) $step->actor;
        $this->feature_id = (string) $step->feature_id;
        $this->description = (string) $step->description;
        $this->business_rule_ids = $step->businessRules()->pluck('business_rules.id')->map(fn ($id): string => (string) $id)->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'actor' => ['nullable', 'string', 'max:255'],
            'feature_id' => ['nullable', 'integer', Rule::exists('features', 'id')],
            'description' => ['nullable', 'string', 'max:20000'],
            'business_rule_ids' => ['array'],
            'business_rule_ids.*' => ['integer', Rule::exists('business_rules', 'id')],
        ];
    }

    /**
     * @return array{workflow_id: int, name: string, actor: ?string, feature_id: ?int, description: ?string, business_rule_ids: list<int>}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'workflow_id' => $this->workflowId,
            'name' => $this->name,
            'actor' => trim($this->actor) !== '' ? $this->actor : null,
            'feature_id' => $this->feature_id !== '' ? (int) $this->feature_id : null,
            'description' => trim($this->description) !== '' ? $this->description : null,
            'business_rule_ids' => array_map('intval', $this->business_rule_ids),
        ];
    }
}
