<?php

namespace App\Livewire\Forms;

use App\Models\Workflow;
use Illuminate\Validation\Rule;
use Livewire\Form;

class WorkflowForm extends Form
{
    public ?Workflow $workflow = null;

    public string $product_id = '';

    public string $name = '';

    public string $description = '';

    public function setWorkflow(Workflow $workflow): void
    {
        $this->resetValidation();
        $this->workflow = $workflow;
        $this->product_id = (string) $workflow->product_id;
        $this->name = $workflow->name;
        $this->description = (string) $workflow->description;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('workflows', 'name')->where('product_id', $this->product_id)->ignore($this->workflow),
            ],
            'description' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array{product_id: int, name: string, description: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'product_id' => (int) $this->product_id,
            'name' => $this->name,
            'description' => trim($this->description) !== '' ? $this->description : null,
        ];
    }
}
