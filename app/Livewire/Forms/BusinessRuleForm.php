<?php

namespace App\Livewire\Forms;

use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Models\BusinessRule;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BusinessRuleForm extends Form
{
    public ?BusinessRule $rule = null;

    public string $title = '';

    public string $description = '';

    public string $source = 'code';

    public string $status = 'pending';

    public function setRule(BusinessRule $rule): void
    {
        $this->resetValidation();
        $this->rule = $rule;
        $this->title = $rule->title;
        $this->description = $rule->description;
        $this->source = $rule->source->value;
        $this->status = $rule->status->value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'source' => ['required', Rule::enum(RuleSource::class)],
            'status' => ['required', Rule::enum(RuleStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['title' => 'intitulé', 'source' => 'source', 'status' => 'statut'];
    }

    /**
     * @return array{implementation_id: int, title: string, description: string, source: RuleSource, status: RuleStatus}
     */
    public function data(int $implementationId): array
    {
        $this->validate();

        return [
            'implementation_id' => $implementationId,
            'title' => $this->title,
            'description' => $this->description,
            'source' => RuleSource::from($this->source),
            'status' => RuleStatus::from($this->status),
        ];
    }
}
