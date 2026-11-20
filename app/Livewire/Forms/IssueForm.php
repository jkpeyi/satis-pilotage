<?php

namespace App\Livewire\Forms;

use App\Enums\Confidence;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use Illuminate\Validation\Rule;
use Livewire\Form;

class IssueForm extends Form
{
    public ?Issue $issue = null;

    public string $title = '';

    public string $description = '';

    public string $type = 'bug';

    public string $severity = 'major';

    public string $status = 'identified';

    public string $impact = '';

    public string $proposed_solution = '';

    public string $effort_min_days = '';

    public string $effort_max_days = '';

    public string $confidence = '';

    public string $owner_id = '';

    public string $repository_id = '';

    public string $component_id = '';

    public string $gitlab_issue_url = '';

    public function setIssue(Issue $issue): void
    {
        $this->resetValidation();
        $this->issue = $issue;
        $this->title = $issue->title;
        $this->description = $issue->description;
        $this->type = $issue->type->value;
        $this->severity = $issue->severity->value;
        $this->status = $issue->status->value;
        $this->proposed_solution = (string) $issue->proposed_solution;
        $this->impact = (string) $issue->impact;
        $this->effort_min_days = (string) $issue->effort_min_days;
        $this->effort_max_days = (string) $issue->effort_max_days;
        $this->confidence = (string) $issue->confidence?->value;
        $this->owner_id = (string) $issue->owner_id;
        $this->repository_id = (string) $issue->repository_id;
        $this->component_id = (string) $issue->component_id;
        $this->gitlab_issue_url = (string) $issue->gitlab_issue_url;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'type' => ['required', Rule::enum(IssueType::class)],
            'severity' => ['required', Rule::enum(IssueSeverity::class)],
            'status' => ['required', Rule::enum(IssueStatus::class)],
            'proposed_solution' => ['nullable', 'string', 'max:20000'],
            'impact' => ['nullable', 'string', 'max:5000'],
            'effort_min_days' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'effort_max_days' => ['nullable', 'numeric', 'min:0', 'max:99999', ...($this->effort_min_days !== '' ? ['gte:effort_min_days'] : [])],
            'confidence' => ['nullable', Rule::enum(Confidence::class)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'repository_id' => ['nullable', 'integer', Rule::exists('repositories', 'id')],
            'component_id' => ['nullable', 'integer', Rule::exists('components', 'id')],
            'gitlab_issue_url' => ['nullable', 'url', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => 'titre', 'severity' => 'gravité', 'proposed_solution' => 'solution proposée',
            'effort_min_days' => 'effort minimum', 'effort_max_days' => 'effort maximum',
            'gitlab_issue_url' => 'ticket GitLab',
        ];
    }

    /**
     * @return array{title: string, description: string, impact: ?string, type: IssueType, severity: IssueSeverity, status: IssueStatus, proposed_solution: ?string, effort_min_days: ?string, effort_max_days: ?string, confidence: ?Confidence, owner_id: ?int, repository_id: ?int, component_id: ?int, gitlab_issue_url: ?string}
     */
    public function data(): array
    {
        $this->effort_min_days = str_replace(',', '.', trim($this->effort_min_days));
        $this->effort_max_days = str_replace(',', '.', trim($this->effort_max_days));
        // A single value is a range of one.
        if ($this->effort_min_days !== '' && $this->effort_max_days === '') {
            $this->effort_max_days = $this->effort_min_days;
        }
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? $value : null;
        $id = fn (string $value): ?int => $value !== '' ? (int) $value : null;

        return [
            'title' => $this->title,
            'description' => $this->description,
            'type' => IssueType::from($this->type),
            'severity' => IssueSeverity::from($this->severity),
            'status' => IssueStatus::from($this->status),
            'proposed_solution' => trim($this->proposed_solution) !== '' ? $this->proposed_solution : null,
            'impact' => $nullable($this->impact),
            'effort_min_days' => $nullable($this->effort_min_days),
            'effort_max_days' => $nullable($this->effort_max_days),
            'confidence' => $this->confidence !== '' ? Confidence::from($this->confidence) : null,
            'owner_id' => $id($this->owner_id),
            'repository_id' => $id($this->repository_id),
            'component_id' => $id($this->component_id),
            'gitlab_issue_url' => $nullable($this->gitlab_issue_url),
        ];
    }
}
