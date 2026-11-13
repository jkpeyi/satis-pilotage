<?php

namespace App\Livewire\Forms;

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

    public string $status = 'open';

    public string $proposed_solution = '';

    public string $effort_days = '';

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
        $this->effort_days = (string) $issue->effort_days;
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
            'effort_days' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => 'titre', 'severity' => 'gravité', 'proposed_solution' => 'solution proposée',
            'effort_days' => 'effort (j/h)',
        ];
    }

    /**
     * @return array{title: string, description: string, type: IssueType, severity: IssueSeverity, status: IssueStatus, proposed_solution: ?string, effort_days: ?string}
     */
    public function data(): array
    {
        $this->effort_days = str_replace(',', '.', trim($this->effort_days));
        $this->validate();

        return [
            'title' => $this->title,
            'description' => $this->description,
            'type' => IssueType::from($this->type),
            'severity' => IssueSeverity::from($this->severity),
            'status' => IssueStatus::from($this->status),
            'proposed_solution' => trim($this->proposed_solution) !== '' ? $this->proposed_solution : null,
            'effort_days' => $this->effort_days !== '' ? $this->effort_days : null,
        ];
    }
}
