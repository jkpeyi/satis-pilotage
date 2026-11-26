<?php

namespace App\Livewire\Forms;

use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\Version;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ComponentForm extends Form
{
    public ?Component $editedComponent = null;

    public int $versionId = 0;

    public string $name = '';

    public string $type = 'api';

    public string $repository_id = '';

    public string $technology = '';

    public string $technology_version = '';

    public string $description = '';

    public function forVersion(Version $version): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $version->id;
    }

    public function setComponent(Component $component): void
    {
        $this->resetValidation();
        $this->editedComponent = $component;
        $this->versionId = $component->version_id;
        $this->name = $component->name;
        $this->type = $component->type->value;
        $this->repository_id = (string) $component->repository_id;
        $this->technology = (string) $component->technology;
        $this->technology_version = (string) $component->technology_version;
        $this->description = (string) $component->description;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('components', 'name')->where('version_id', $this->versionId)->ignore($this->editedComponent)],
            'type' => ['required', Rule::enum(ComponentType::class)],
            'repository_id' => ['nullable', 'integer', Rule::exists('repositories', 'id')->where('version_id', $this->versionId)],
            'technology' => ['nullable', 'string', 'max:255'],
            'technology_version' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array{version_id: int, repository_id: ?int, name: string, type: ComponentType, technology: ?string, technology_version: ?string, description: ?string}
     */
    public function data(): array
    {
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? $value : null;

        return [
            'version_id' => $this->versionId,
            'repository_id' => $this->repository_id !== '' ? (int) $this->repository_id : null,
            'name' => $this->name,
            'type' => ComponentType::from($this->type),
            'technology' => $nullable($this->technology),
            'technology_version' => $nullable($this->technology_version),
            'description' => $nullable($this->description),
        ];
    }
}
