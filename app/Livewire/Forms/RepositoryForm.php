<?php

namespace App\Livewire\Forms;

use App\Enums\RepositoryRole;
use App\Models\Repository;
use App\Models\Version;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RepositoryForm extends Form
{
    public ?Repository $repository = null;

    public int $versionId = 0;

    public string $path = '';

    public string $web_url = '';

    public string $role = 'backend';

    public string $production_branch = 'main';

    /** Comma-separated list. */
    public string $active_branches = '';

    /** @var list<array{component: string, version: string}> */
    public array $stack = [];

    public function forVersion(Version $version): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $version->id;
        $this->stack = [
            ['component' => 'php', 'version' => ''],
            ['component' => 'laravel', 'version' => ''],
            ['component' => 'react', 'version' => ''],
        ];
    }

    public function setRepository(Repository $repository): void
    {
        $this->resetValidation();
        $this->repository = $repository;
        $this->versionId = $repository->version_id;
        $this->path = $repository->path;
        $this->web_url = (string) $repository->web_url;
        $this->role = $repository->role->value;
        $this->production_branch = $repository->production_branch;
        $this->active_branches = implode(', ', $repository->active_branches ?? []);
        $this->stack = collect($repository->stack ?? [])
            ->map(fn (string $version, string $component): array => ['component' => $component, 'version' => $version])
            ->values()
            ->all();
    }

    public function addStackRow(): void
    {
        $this->stack[] = ['component' => '', 'version' => ''];
    }

    public function removeStackRow(int $index): void
    {
        unset($this->stack[$index]);
        $this->stack = array_values($this->stack);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'path' => [
                'required', 'string', 'max:255',
                Rule::unique('repositories', 'path')->where('version_id', $this->versionId)->ignore($this->repository),
            ],
            'web_url' => ['nullable', 'url', 'max:255'],
            'role' => ['required', Rule::enum(RepositoryRole::class)],
            'production_branch' => ['required', 'string', 'max:255'],
            'active_branches' => ['nullable', 'string', 'max:1000'],
            'stack' => ['array'],
            'stack.*.component' => ['nullable', 'string', 'max:64'],
            'stack.*.version' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'stack.*.component' => 'composant',
            'stack.*.version' => 'version',
        ];
    }

    /**
     * @return array{version_id: int, path: string, web_url: ?string, role: RepositoryRole, production_branch: string, active_branches: list<string>, stack: array<string, string>}
     */
    public function data(): array
    {
        $this->validate();

        $stack = [];
        foreach ($this->stack as $row) {
            $stack[$row['component']] = $row['version'];
        }

        return [
            'version_id' => $this->versionId,
            'path' => trim($this->path),
            'web_url' => $this->web_url !== '' ? $this->web_url : null,
            'role' => RepositoryRole::from($this->role),
            'production_branch' => trim($this->production_branch),
            'active_branches' => explode(',', $this->active_branches),
            'stack' => $stack,
        ];
    }
}
