<?php

namespace App\Livewire\Forms;

use App\Enums\VerificationStatus;
use App\Models\Deployment;
use App\Models\Environment;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DeploymentForm extends Form
{
    public ?Deployment $deployment = null;

    public int $versionId = 0;

    public string $environment_id = '';

    public string $release_id = '';

    public string $commit_sha = '';

    public string $deployed_at = '';

    public string $verification_status = 'to_verify';

    public string $evidence = '';

    public string $notes = '';

    public function forEnvironment(Environment $environment): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $environment->version_id;
        $this->environment_id = (string) $environment->id;
        $this->deployed_at = now()->format('Y-m-d\TH:i');
    }

    public function setDeployment(Deployment $deployment): void
    {
        $this->resetValidation();
        $this->deployment = $deployment;
        $this->versionId = $deployment->environment->version_id;
        $this->environment_id = (string) $deployment->environment_id;
        $this->release_id = (string) $deployment->release_id;
        $this->commit_sha = (string) $deployment->commit_sha;
        $this->deployed_at = $deployment->deployed_at->format('Y-m-d\TH:i');
        $this->verification_status = $deployment->verification_status->value;
        $this->evidence = (string) $deployment->evidence;
        $this->notes = (string) $deployment->notes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'environment_id' => ['required', 'integer', Rule::exists('environments', 'id')->where('version_id', $this->versionId)],
            'release_id' => ['nullable', 'required_without:commit_sha', 'integer', Rule::exists('releases', 'id')],
            'commit_sha' => ['nullable', 'required_without:release_id', 'regex:/^[0-9a-fA-F]{7,40}$/'],
            'deployed_at' => ['required', 'date', 'before_or_equal:now'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'evidence' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'release_id.required_without' => 'Indiquez la release ou le commit déployé.',
            'commit_sha.required_without' => 'Indiquez la release ou le commit déployé.',
            'commit_sha.regex' => 'Le commit doit compter de 7 à 40 caractères hexadécimaux.',
            'deployed_at.before_or_equal' => 'Un déploiement s’enregistre une fois réalisé.',
        ];
    }

    /**
     * @return array{environment_id: int, release_id: ?int, commit_sha: ?string, deployed_at: string, verification_status: VerificationStatus, evidence: ?string, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'environment_id' => (int) $this->environment_id,
            'release_id' => $this->release_id !== '' ? (int) $this->release_id : null,
            'commit_sha' => $this->commit_sha !== '' ? strtolower($this->commit_sha) : null,
            'deployed_at' => $this->deployed_at,
            'verification_status' => VerificationStatus::from($this->verification_status),
            'evidence' => $this->evidence !== '' ? $this->evidence : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];
    }
}
