<?php

namespace App\Livewire\Forms;

use App\Enums\WorkflowStepKind;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Validation\Rule;
use Livewire\Form;

class WorkflowStepForm extends Form
{
    public ?WorkflowStep $step = null;

    public int $workflowId = 0;

    public string $kind = 'action';

    public string $name = '';

    public string $actor = '';

    public string $feature_id = '';

    public string $description = '';

    /** @var list<string> */
    public array $business_rule_ids = [];

    /**
     * Rows bound to the form; the "next step" select of an action only sets target_step_id.
     *
     * @var list<array{label?: string, target_step_id?: string}>
     */
    public array $transitions = [];

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
        $this->kind = $step->kind->value;
        $this->name = $step->name;
        $this->transitions = $step->transitions->map(fn ($transition): array => [
            'label' => (string) $transition->label,
            'target_step_id' => (string) $transition->target_step_id,
        ])->values()->all();
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
            'kind' => ['required', Rule::enum(WorkflowStepKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'actor' => ['nullable', 'string', 'max:255'],
            'feature_id' => ['nullable', 'integer', Rule::exists('features', 'id')],
            'description' => ['nullable', 'string', 'max:20000'],
            'business_rule_ids' => ['array'],
            'business_rule_ids.*' => ['integer', Rule::exists('business_rules', 'id')],
            'transitions' => ['array', 'max:20'],
            'transitions.*.label' => [$this->kind === 'decision' ? 'required' : 'nullable', 'string', 'max:150'],
            'transitions.*.target_step_id' => ['required', 'integer', Rule::exists('workflow_steps', 'id')->where('workflow_id', $this->workflowId)->whereNull('archived_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'transitions.*.label.required' => 'Indiquez la condition de cette sortie (ex. « Oui »).',
            'transitions.*.target_step_id.required' => 'Choisissez l’étape où mène cette sortie.',
        ];
    }

    /**
     * An end has no exit; an action keeps at most its first one, and an empty "next step" means
     * "the next one in the order".
     */
    public function normalizeTransitions(): void
    {
        $rows = array_map(fn (array $transition): array => [
            'label' => (string) ($transition['label'] ?? ''),
            'target_step_id' => (string) ($transition['target_step_id'] ?? ''),
        ], $this->transitions);

        $this->transitions = match ($this->kind) {
            'end' => [],
            'action' => array_slice(array_values(array_filter($rows, fn (array $row): bool => $row['target_step_id'] !== '')), 0, 1),
            default => $rows,
        };
    }

    public function addTransition(): void
    {
        $this->transitions[] = ['label' => '', 'target_step_id' => ''];
    }

    public function removeTransition(int $index): void
    {
        unset($this->transitions[$index]);
        $this->transitions = array_values($this->transitions);
    }

    /**
     * @return array{workflow_id: int, kind: WorkflowStepKind, name: string, actor: ?string, feature_id: ?int, description: ?string, business_rule_ids: list<int>, transitions: list<array{label: ?string, target_step_id: int}>}
     */
    public function data(): array
    {
        $this->normalizeTransitions();
        $this->validate();

        return [
            'workflow_id' => $this->workflowId,
            'kind' => $kind = WorkflowStepKind::from($this->kind),
            'name' => $this->name,
            'actor' => trim($this->actor) !== '' ? $this->actor : null,
            'feature_id' => $this->feature_id !== '' ? (int) $this->feature_id : null,
            'description' => trim($this->description) !== '' ? $this->description : null,
            'business_rule_ids' => array_map('intval', $this->business_rule_ids),
            'transitions' => $kind === WorkflowStepKind::End ? [] : array_map(function (array $transition): array {
                $label = trim($transition['label'] ?? '');

                return ['label' => $label !== '' ? $label : null, 'target_step_id' => (int) ($transition['target_step_id'] ?? 0)];
            }, $this->transitions),
        ];
    }
}
