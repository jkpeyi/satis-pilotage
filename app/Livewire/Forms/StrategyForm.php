<?php

namespace App\Livewire\Forms;

use App\Models\RefactoringStrategy;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StrategyForm extends Form
{
    public ?RefactoringStrategy $strategy = null;

    public string $product_id = '';

    public string $title = '';

    public string $context = '';

    public string $migration = '';

    public string $safety = '';

    public string $saas_notes = '';

    /** @var list<string> */
    public array $issue_ids = [];

    public function setStrategy(RefactoringStrategy $strategy): void
    {
        $this->resetValidation();
        $this->strategy = $strategy;
        $this->product_id = (string) $strategy->product_id;
        $this->title = $strategy->title;
        $this->context = (string) $strategy->context;
        $this->migration = (string) $strategy->migration;
        $this->safety = (string) $strategy->safety;
        $this->saas_notes = (string) $strategy->saas_notes;
        $this->issue_ids = $strategy->issues()->pluck('issues.id')->map(fn ($id): string => (string) $id)->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:20000'],
            'migration' => ['nullable', 'string', 'max:20000'],
            'safety' => ['nullable', 'string', 'max:20000'],
            'saas_notes' => ['nullable', 'string', 'max:10000'],
            'issue_ids' => ['array'],
            'issue_ids.*' => ['integer', Rule::exists('issues', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['title' => 'intitulé', 'context' => 'contexte', 'migration' => 'stratégie de migration', 'safety' => 'tests et rollback'];
    }

    /**
     * @return array{data: array{product_id: int, title: string, context: ?string, migration: ?string, safety: ?string, saas_notes: ?string}, issue_ids: list<int>}
     */
    public function data(): array
    {
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? $value : null;

        return [
            'data' => [
                'product_id' => (int) $this->product_id,
                'title' => trim($this->title),
                'context' => $nullable($this->context),
                'migration' => $nullable($this->migration),
                'safety' => $nullable($this->safety),
                'saas_notes' => $nullable($this->saas_notes),
            ],
            'issue_ids' => array_map('intval', $this->issue_ids),
        ];
    }
}
