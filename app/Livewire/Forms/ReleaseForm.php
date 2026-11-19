<?php

namespace App\Livewire\Forms;

use App\Models\Release;
use App\Models\Version;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ReleaseForm extends Form
{
    public ?Release $release = null;

    public int $versionId = 0;

    public string $repository_id = '';

    public string $name = '';

    public string $commit_sha = '';

    public string $released_on = '';

    public string $notes = '';

    public function forVersion(Version $version, ?int $repositoryId = null): void
    {
        $this->reset();
        $this->resetValidation();
        $this->versionId = $version->id;
        $this->repository_id = (string) $repositoryId;
    }

    public function setRelease(Release $release): void
    {
        $this->resetValidation();
        $this->release = $release;
        $this->versionId = $release->repository->version_id;
        $this->repository_id = (string) $release->repository_id;
        $this->name = $release->name;
        $this->commit_sha = (string) $release->commit_sha;
        $this->released_on = (string) $release->released_on?->format('Y-m-d');
        $this->notes = (string) $release->notes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'repository_id' => ['required', 'integer', Rule::exists('repositories', 'id')->where('version_id', $this->versionId)],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('releases', 'name')->where('repository_id', $this->repository_id)->ignore($this->release),
            ],
            'commit_sha' => ['nullable', 'regex:/^[0-9a-fA-F]{7,40}$/'],
            'released_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['commit_sha.regex' => 'Le commit doit compter de 7 à 40 caractères hexadécimaux.'];
    }

    /**
     * @return array{repository_id: int, name: string, commit_sha: ?string, released_on: ?string, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'repository_id' => (int) $this->repository_id,
            'name' => $this->name,
            'commit_sha' => $this->commit_sha !== '' ? strtolower($this->commit_sha) : null,
            'released_on' => $this->released_on !== '' ? $this->released_on : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];
    }
}
