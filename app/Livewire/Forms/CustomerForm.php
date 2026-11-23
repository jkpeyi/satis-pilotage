<?php

namespace App\Livewire\Forms;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CustomerForm extends Form
{
    public ?Customer $customer = null;

    public string $name = '';

    public string $type = '';

    public string $country = '';

    public string $notes = '';

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
        $this->name = $customer->name;
        $this->type = (string) $customer->type?->value;
        $this->country = (string) $customer->country;
        $this->notes = (string) $customer->notes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('customers', 'name')->ignore($this->customer)],
            'type' => ['nullable', Rule::enum(CustomerType::class)],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{name: string, type: ?CustomerType, country: ?string, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'name' => $this->name,
            'type' => $this->type !== '' ? CustomerType::from($this->type) : null,
            'country' => $this->country !== '' ? $this->country : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];
    }
}
