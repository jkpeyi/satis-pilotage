<?php

namespace App\Livewire\Forms;

use App\Enums\ToolResultFormat;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class ToolRunImportForm extends Form
{
    public string $format = 'sarif';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public string $tool_name = '';

    public string $tool_version = '';

    public string $scanned_on = '';

    public string $path_prefix = '';

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ToolResultFormat::class)],
            // Livewire's temporary upload limit is 12 Mo.
            'file' => ['required', 'file', 'max:12288'],
            'tool_name' => ['nullable', 'string', 'max:100'],
            'tool_version' => ['nullable', 'string', 'max:64'],
            'scanned_on' => ['required', 'date', 'before_or_equal:today'],
            'path_prefix' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['file' => 'fichier', 'tool_name' => 'outil', 'tool_version' => 'version de l’outil', 'scanned_on' => 'date d’exécution', 'path_prefix' => 'préfixe'];
    }

    /**
     * @return array{format: ToolResultFormat, contents: string, file_name: string, options: array{tool_name: ?string, tool_version: ?string, scanned_on: string, path_prefix: ?string}}
     */
    public function data(): array
    {
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? trim($value) : null;
        /** @var TemporaryUploadedFile $file */
        $file = $this->file;

        return [
            'format' => ToolResultFormat::from($this->format),
            'contents' => (string) $file->get(),
            'file_name' => $file->getClientOriginalName(),
            'options' => [
                'tool_name' => $nullable($this->tool_name),
                'tool_version' => $nullable($this->tool_version),
                'scanned_on' => $this->scanned_on,
                'path_prefix' => $nullable($this->path_prefix),
            ],
        ];
    }
}
