<?php

namespace App\Livewire\Forms;

use App\Enums\SnippetKind;
use App\Models\CodeSnippet;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CodeSnippetForm extends Form
{
    public ?CodeSnippet $snippet = null;

    public string $kind = 'finding';

    public string $language = 'php';

    public string $content = '';

    public string $repository_id = '';

    public string $path = '';

    public string $line_start = '';

    public string $line_end = '';

    public function setSnippet(CodeSnippet $snippet): void
    {
        $this->resetValidation();
        $this->snippet = $snippet;
        $this->kind = $snippet->kind->value;
        $this->language = $snippet->language;
        $this->content = $snippet->content;
        $this->repository_id = (string) $snippet->repository_id;
        $this->path = (string) $snippet->path;
        $this->line_start = (string) $snippet->line_start;
        $this->line_end = (string) $snippet->line_end;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(SnippetKind::class)],
            'language' => ['required', 'string', 'max:32'],
            'content' => ['required', 'string', 'max:200000'],
            'repository_id' => ['nullable', 'integer', Rule::exists('repositories', 'id')],
            'path' => ['nullable', 'string', 'max:255'],
            'line_start' => ['nullable', 'integer', 'min:1'],
            'line_end' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'kind' => 'nature', 'language' => 'langage', 'content' => 'code', 'repository_id' => 'dépôt',
            'path' => 'fichier', 'line_start' => 'ligne de début', 'line_end' => 'ligne de fin',
        ];
    }

    /**
     * @return array{kind: SnippetKind, language: string, content: string, repository_id: ?int, path: ?string, line_start: ?int, line_end: ?int}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'kind' => SnippetKind::from($this->kind),
            'language' => strtolower(trim($this->language)),
            'content' => rtrim($this->content),
            'repository_id' => $this->repository_id !== '' ? (int) $this->repository_id : null,
            'path' => trim($this->path) !== '' ? trim($this->path) : null,
            'line_start' => $this->line_start !== '' ? (int) $this->line_start : null,
            'line_end' => $this->line_end !== '' ? (int) $this->line_end : null,
        ];
    }
}
