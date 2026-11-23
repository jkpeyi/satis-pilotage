<?php

namespace App\Livewire\Forms;

use App\Enums\WorkPackageStatus;
use App\Models\WorkPackage;
use Illuminate\Validation\Rule;
use Livewire\Form;

class WorkPackageForm extends Form
{
    public ?WorkPackage $workPackage = null;

    public string $product_id = '';

    public string $name = '';

    public string $objective = '';

    public string $starts_on = '';

    public string $due_on = '';

    public string $status = 'planned';

    public function setWorkPackage(WorkPackage $workPackage): void
    {
        $this->resetValidation();
        $this->workPackage = $workPackage;
        $this->product_id = (string) $workPackage->product_id;
        $this->name = $workPackage->name;
        $this->objective = (string) $workPackage->objective;
        $this->starts_on = (string) $workPackage->starts_on?->toDateString();
        $this->due_on = (string) $workPackage->due_on?->toDateString();
        $this->status = $workPackage->status->value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'name' => ['required', 'string', 'max:255', Rule::unique('work_packages', 'name')->where('product_id', $this->product_id)->ignore($this->workPackage)],
            'objective' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', ...($this->starts_on !== '' ? ['after_or_equal:starts_on'] : [])],
            'status' => ['required', Rule::enum(WorkPackageStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nom', 'objective' => 'objectif', 'starts_on' => 'début', 'due_on' => 'jalon'];
    }

    /**
     * @return array{product_id: int, name: string, objective: ?string, starts_on: ?string, due_on: ?string, status: WorkPackageStatus}
     */
    public function data(): array
    {
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? trim($value) : null;

        return [
            'product_id' => (int) $this->product_id,
            'name' => trim($this->name),
            'objective' => $nullable($this->objective),
            'starts_on' => $nullable($this->starts_on),
            'due_on' => $nullable($this->due_on),
            'status' => WorkPackageStatus::from($this->status),
        ];
    }
}
