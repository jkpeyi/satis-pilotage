<?php

namespace App\Livewire\Forms;

use App\Enums\EnvironmentType;
use App\Models\Environment;
use App\Models\Version;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EnvironmentForm extends Form
{
    public ?Environment $environment = null;

    public int $versionId = 0;

    public string $type = 'production';

    public string $name = '';

    public string $url = '';

    public string $repository_id = '';

    public string $deployed_ref = '';

    public string $database_engine = '';

    public string $database_version = '';

    public string $php_version = '';

    public string $server = '';

    public string $notes = '';

    public function forVersion(Version $version): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $version->id;
    }

    public function setEnvironment(Environment $environment): void
    {
        $this->resetValidation();
        $this->environment = $environment;
        $this->versionId = $environment->version_id;
        $this->type = $environment->type->value;
        $this->name = $environment->name;
        $this->url = (string) $environment->url;
        $this->repository_id = (string) $environment->repository_id;
        $this->deployed_ref = (string) $environment->deployed_ref;
        $this->database_engine = (string) $environment->database_engine;
        $this->database_version = (string) $environment->database_version;
        $this->php_version = (string) $environment->php_version;
        $this->server = (string) $environment->server;
        $this->notes = (string) $environment->notes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EnvironmentType::class)],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'repository_id' => ['nullable', 'integer', Rule::exists('repositories', 'id')->where('version_id', $this->versionId)],
            'deployed_ref' => ['nullable', 'string', 'max:255'],
            'database_engine' => ['nullable', 'string', 'max:255'],
            'database_version' => ['nullable', 'string', 'max:64'],
            'php_version' => ['nullable', 'string', 'max:64'],
            'server' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{version_id: int, repository_id: ?int, type: EnvironmentType, name: string, url: ?string, deployed_ref: ?string, database_engine: ?string, database_version: ?string, php_version: ?string, server: ?string, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        $nullable = fn (string $value): ?string => $value !== '' ? $value : null;

        return [
            'version_id' => $this->versionId,
            'repository_id' => $this->repository_id !== '' ? (int) $this->repository_id : null,
            'type' => EnvironmentType::from($this->type),
            'name' => $this->name,
            'url' => $nullable($this->url),
            'deployed_ref' => $nullable($this->deployed_ref),
            'database_engine' => $nullable($this->database_engine),
            'database_version' => $nullable($this->database_version),
            'php_version' => $nullable($this->php_version),
            'server' => $nullable($this->server),
            'notes' => $nullable($this->notes),
        ];
    }
}
