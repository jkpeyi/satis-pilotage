<?php

namespace App\Livewire\Forms;

use App\Enums\VersionStatus;
use App\Models\Version;
use Illuminate\Validation\Rule;
use Livewire\Form;

class VersionForm extends Form
{
    public ?Version $version = null;

    public string $product_id = '';

    /** Empty for a baseline version without customer. */
    public string $customer_id = '';

    public string $name = '';

    public string $status = 'in_production';

    public string $notes = '';

    public function setVersion(Version $version): void
    {
        $this->version = $version;
        $this->product_id = (string) $version->product_id;
        $this->customer_id = (string) $version->customer_id;
        $this->name = $version->name;
        $this->status = $version->status->value;
        $this->notes = (string) $version->notes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('versions', 'name')->where('product_id', $this->product_id)->ignore($this->version),
            ],
            'status' => ['required', Rule::enum(VersionStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{product_id: int, customer_id: ?int, name: string, status: VersionStatus, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'product_id' => (int) $this->product_id,
            'customer_id' => $this->customer_id !== '' ? (int) $this->customer_id : null,
            'name' => $this->name,
            'status' => VersionStatus::from($this->status),
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];
    }
}
