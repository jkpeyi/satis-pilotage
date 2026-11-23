<?php

namespace App\Livewire\Forms;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Models\RemediationAction;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RemediationActionForm extends Form
{
    public ?RemediationAction $action = null;

    public string $product_id = '';

    public string $work_package_id = '';

    public string $issue_id = '';

    public string $title = '';

    public string $description = '';

    public string $priority = 'p2';

    public string $status = 'todo';

    public string $owner_id = '';

    public string $starts_on = '';

    public string $due_on = '';

    public string $effort_days = '';

    public string $acceptance_criteria = '';

    public string $test_evidence = '';

    public string $gitlab_issue_url = '';

    /** @var list<string> */
    public array $dependency_ids = [];

    public function setAction(RemediationAction $action): void
    {
        $this->resetValidation();
        $this->action = $action;
        $this->product_id = (string) $action->product_id;
        $this->work_package_id = (string) $action->work_package_id;
        $this->issue_id = (string) $action->issue_id;
        $this->title = $action->title;
        $this->description = (string) $action->description;
        $this->priority = $action->priority->value;
        $this->status = $action->status->value;
        $this->owner_id = (string) $action->owner_id;
        $this->starts_on = (string) $action->starts_on?->toDateString();
        $this->due_on = (string) $action->due_on?->toDateString();
        $this->effort_days = $action->effort_days !== null ? rtrim(rtrim($action->effort_days, '0'), '.') : '';
        $this->acceptance_criteria = (string) $action->acceptance_criteria;
        $this->test_evidence = (string) $action->test_evidence;
        $this->gitlab_issue_url = (string) $action->gitlab_issue_url;
        $this->dependency_ids = $action->dependencies()->pluck('remediation_actions.id')->map(fn ($id): string => (string) $id)->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'work_package_id' => ['nullable', 'integer', Rule::exists('work_packages', 'id')],
            'issue_id' => ['nullable', 'integer', Rule::exists('issues', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(ActionPriority::class)],
            'status' => ['required', Rule::enum(ActionStatus::class)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', ...($this->starts_on !== '' ? ['after_or_equal:starts_on'] : [])],
            'effort_days' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'acceptance_criteria' => [Rule::requiredIf($this->status === 'done'), 'nullable', 'string', 'max:10000'],
            'test_evidence' => [Rule::requiredIf($this->status === 'done'), 'nullable', 'string', 'max:5000'],
            'gitlab_issue_url' => ['nullable', 'url', 'max:1000'],
            'dependency_ids' => ['array'],
            'dependency_ids.*' => ['integer', Rule::exists('remediation_actions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'acceptance_criteria.required' => 'Une action terminée indique ses critères de réception.',
            'test_evidence.required' => 'Une action terminée indique la preuve de test qui vérifie ses critères.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => 'intitulé', 'priority' => 'priorité', 'starts_on' => 'début', 'due_on' => 'échéance',
            'effort_days' => 'effort', 'gitlab_issue_url' => 'ticket GitLab',
        ];
    }

    /**
     * @return array{data: array{product_id: int, work_package_id: ?int, issue_id: ?int, title: string, description: ?string, priority: ActionPriority, status: ActionStatus, owner_id: ?int, starts_on: ?string, due_on: ?string, effort_days: ?string, acceptance_criteria: ?string, test_evidence: ?string, gitlab_issue_url: ?string}, dependency_ids: list<int>}
     */
    public function data(): array
    {
        $this->effort_days = str_replace(',', '.', trim($this->effort_days));
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? trim($value) : null;
        $id = fn (string $value): ?int => $value !== '' ? (int) $value : null;

        return [
            'data' => [
                'product_id' => (int) $this->product_id,
                'work_package_id' => $id($this->work_package_id),
                'issue_id' => $id($this->issue_id),
                'title' => trim($this->title),
                'description' => $nullable($this->description),
                'priority' => ActionPriority::from($this->priority),
                'status' => ActionStatus::from($this->status),
                'owner_id' => $id($this->owner_id),
                'starts_on' => $nullable($this->starts_on),
                'due_on' => $nullable($this->due_on),
                'effort_days' => $nullable($this->effort_days),
                'acceptance_criteria' => $nullable($this->acceptance_criteria),
                'test_evidence' => $nullable($this->test_evidence),
                'gitlab_issue_url' => $nullable($this->gitlab_issue_url),
            ],
            'dependency_ids' => array_map('intval', $this->dependency_ids),
        ];
    }
}
