<?php

namespace App\Livewire\Forms;

use App\Enums\VerificationStatus;
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

    public bool $is_historical_pivot = false;

    public string $technical_owner_id = '';

    public string $verification_status = 'to_verify';

    public string $notes = '';

    public function setVersion(Version $version): void
    {
        $this->version = $version;
        $this->product_id = (string) $version->product_id;
        $this->customer_id = (string) $version->customer_id;
        $this->name = $version->name;
        $this->status = $version->status->value;
        $this->is_historical_pivot = $version->is_historical_pivot;
        $this->technical_owner_id = (string) $version->technical_owner_id;
        $this->verification_status = $version->verification_status->value;
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
            'is_historical_pivot' => ['boolean'],
            'technical_owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{product_id: int, customer_id: ?int, name: string, status: VersionStatus, is_historical_pivot: bool, technical_owner_id: ?int, verification_status: VerificationStatus, notes: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'product_id' => (int) $this->product_id,
            'customer_id' => $this->customer_id !== '' ? (int) $this->customer_id : null,
            'name' => $this->name,
            'status' => VersionStatus::from($this->status),
            'is_historical_pivot' => $this->is_historical_pivot,
            'technical_owner_id' => $this->technical_owner_id !== '' ? (int) $this->technical_owner_id : null,
            'verification_status' => VerificationStatus::from($this->verification_status),
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];
    }
}
