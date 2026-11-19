<?php

namespace App\Livewire\Forms;

use App\Enums\IntegrationProtocol;
use App\Enums\VerificationStatus;
use App\Models\Integration;
use Illuminate\Validation\Rule;
use Livewire\Form;

class IntegrationForm extends Form
{
    public ?Integration $integration = null;

    public int $versionId = 0;

    public string $source_component_id = '';

    /** "component" or "external". */
    public string $target_kind = 'component';

    public string $target_component_id = '';

    public string $external_system = '';

    public string $protocol = 'rest';

    public string $description = '';

    public string $evidence_url = '';

    public string $verification_status = 'to_verify';

    public function forVersion(int $versionId): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $versionId;
    }

    public function setIntegration(Integration $integration, int $versionId): void
    {
        $this->resetValidation();
        $this->integration = $integration;
        $this->versionId = $versionId;
        $this->source_component_id = (string) $integration->source_component_id;
        $this->target_kind = $integration->isExternal() ? 'external' : 'component';
        $this->target_component_id = (string) $integration->target_component_id;
        $this->external_system = (string) $integration->external_system;
        $this->protocol = $integration->protocol->value;
        $this->description = (string) $integration->description;
        $this->evidence_url = (string) $integration->evidence_url;
        $this->verification_status = $integration->verification_status->value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'source_component_id' => ['required', 'integer', Rule::exists('components', 'id')->where('version_id', $this->versionId)->whereNull('archived_at')],
            'target_kind' => ['required', Rule::in(['component', 'external'])],
            'target_component_id' => ['nullable', 'required_if:target_kind,component', 'integer', 'different:source_component_id', Rule::exists('components', 'id')->whereNull('archived_at')],
            'external_system' => ['nullable', 'required_if:target_kind,external', 'string', 'max:255'],
            'protocol' => ['required', Rule::enum(IntegrationProtocol::class)],
            'description' => ['nullable', 'string', 'max:20000'],
            'evidence_url' => ['nullable', 'url', 'max:1000'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'target_component_id.required_if' => 'Choisissez le composant cible.',
            'target_component_id.different' => 'Un composant ne peut pas s’intégrer avec lui-même.',
            'external_system.required_if' => 'Indiquez le système externe.',
        ];
    }

    /**
     * @return array{source_component_id: int, target_component_id: ?int, external_system: ?string, protocol: IntegrationProtocol, description: ?string, evidence_url: ?string, verification_status: VerificationStatus}
     */
    public function data(): array
    {
        $this->validate();
        $external = $this->target_kind === 'external';

        return [
            'source_component_id' => (int) $this->source_component_id,
            'target_component_id' => $external ? null : (int) $this->target_component_id,
            'external_system' => $external ? trim($this->external_system) : null,
            'protocol' => IntegrationProtocol::from($this->protocol),
            'description' => trim($this->description) !== '' ? $this->description : null,
            'evidence_url' => trim($this->evidence_url) !== '' ? $this->evidence_url : null,
            'verification_status' => VerificationStatus::from($this->verification_status),
        ];
    }
}
