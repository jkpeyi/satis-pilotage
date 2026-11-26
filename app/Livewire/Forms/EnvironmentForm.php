<?php

namespace App\Livewire\Forms;

use App\Enums\EnvironmentType;
use App\Models\Environment;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EnvironmentForm extends Form
{
    public ?Environment $environment = null;

    public int $versionId = 0;

    public string $type = 'production';

    public string $name = '';

    public string $notes = '';

    public function forVersion(int $versionId): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $versionId;
    }

    public function setEnvironment(Environment $environment): void
    {
        $this->resetValidation();
        $this->environment = $environment;
        $this->versionId = $environment->version_id;
        $this->type = $environment->type->value;
        $this->name = $environment->name;
        $this->notes = (string) $environment->notes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EnvironmentType::class)],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('environments', 'name')->where('version_id', $this->versionId)->whereNull('archived_at')->ignore($this->environment),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nom', 'notes' => 'remarques'];
    }

    /**
     * @return array{version_id: int, type: EnvironmentType, name: string, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'version_id' => $this->versionId,
            'type' => EnvironmentType::from($this->type),
            'name' => trim($this->name),
            'notes' => trim($this->notes) !== '' ? $this->notes : null,
        ];
    }
}
