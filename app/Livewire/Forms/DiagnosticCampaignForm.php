<?php

namespace App\Livewire\Forms;

use App\Models\DiagnosticCampaign;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DiagnosticCampaignForm extends Form
{
    public ?DiagnosticCampaign $campaign = null;

    public string $repository_id = '';

    public string $commit_sha = '';

    public string $title = '';

    public string $analyzed_on = '';

    public string $objective = '';

    public string $limitations = '';

    public function setCampaign(DiagnosticCampaign $campaign): void
    {
        $this->resetValidation();
        $this->campaign = $campaign;
        $this->repository_id = (string) $campaign->repository_id;
        $this->commit_sha = $campaign->commit_sha;
        $this->title = $campaign->title;
        $this->analyzed_on = (string) $campaign->analyzed_on?->toDateString();
        $this->objective = (string) $campaign->objective;
        $this->limitations = (string) $campaign->limitations;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'repository_id' => ['required', 'integer', Rule::exists('repositories', 'id')->whereNull('archived_at')],
            'commit_sha' => ['required', 'regex:/^[0-9a-fA-F]{7,40}$/'],
            'title' => ['required', 'string', 'max:255'],
            'analyzed_on' => ['nullable', 'date'],
            'objective' => ['nullable', 'string', 'max:20000'],
            'limitations' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['commit_sha.regex' => 'Le commit est un SHA hexadécimal de 7 à 40 caractères.'];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['repository_id' => 'dépôt', 'commit_sha' => 'commit', 'title' => 'intitulé', 'analyzed_on' => 'date d’analyse'];
    }

    /**
     * @return array{repository_id: int, commit_sha: string, title: string, analyzed_on: ?string, objective: ?string, limitations: ?string}
     */
    public function data(): array
    {
        $this->commit_sha = trim($this->commit_sha);
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? $value : null;

        return [
            'repository_id' => (int) $this->repository_id,
            'commit_sha' => $this->commit_sha,
            'title' => $this->title,
            'analyzed_on' => $nullable($this->analyzed_on),
            'objective' => $nullable($this->objective),
            'limitations' => $nullable($this->limitations),
        ];
    }
}
