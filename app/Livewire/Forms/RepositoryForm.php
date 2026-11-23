<?php

namespace App\Livewire\Forms;

use App\Enums\RepositoryRole;
use App\Models\Repository;
use App\Models\Version;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RepositoryForm extends Form
{
    public ?Repository $repository = null;

    public int $versionId = 0;

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
            'web_url' => [
                'required', 'url', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $path = Repository::pathFromUrl((string) $value);
                    if ($path === null) {
                        $fail('L’URL doit contenir le chemin du projet, ex. https://gitlab.satisworld.com/satis/macro-api.');

                        return;
                    }
                    $taken = Repository::query()->where('version_id', $this->versionId)->where('path', $path)
                        ->when($this->repository !== null, fn ($query) => $query->whereKeyNot($this->repository?->id))->exists();
                    if ($taken) {
                        $fail("Le dépôt {$path} est déjà déclaré pour cette version.");
                    }
                },
            ],
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
            'web_url' => 'URL GitLab',
        ];
    }

    /**
     * @return array{version_id: int, web_url: string, role: RepositoryRole, production_branch: string, active_branches: list<string>, stack: array<string, string>}
     */
    public function data(): array
    {
        $this->web_url = rtrim(trim($this->web_url), '/');
        $this->validate();

        $stack = [];
        foreach ($this->stack as $row) {
            $stack[$row['component']] = $row['version'];
        }

        return [
            'version_id' => $this->versionId,
            'web_url' => $this->web_url,
            'role' => RepositoryRole::from($this->role),
            'production_branch' => trim($this->production_branch),
            'active_branches' => explode(',', $this->active_branches),
            'stack' => $stack,
        ];
    }
}
