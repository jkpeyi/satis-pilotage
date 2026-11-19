<?php

namespace App\Livewire\Forms;

use App\Reports\ReportScope;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ReportForm extends Form
{
    public string $code = 'L1';

    public string $product_id = '';

    /** Empty for every customer of the product. */
    public string $customer_id = '';

    /**
     * @return list<string>
     */
    public static function exportableCodes(): array
    {
        /** @var array<string, array{builder: ?string}> $deliverables */
        $deliverables = config('reports.deliverables');

        return array_keys(array_filter($deliverables, fn (array $deliverable): bool => $deliverable['builder'] !== null));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', Rule::in(self::exportableCodes())],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'customer_id' => [
                'nullable', 'integer',
                Rule::exists('versions', 'customer_id')->where('product_id', $this->product_id)->whereNull('archived_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'customer_id.exists' => 'Ce client n’a pas de version active de ce produit.',
        ];
    }

    /**
     * @return array{code: string, scope: ReportScope}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'code' => $this->code,
            'scope' => new ReportScope((int) $this->product_id, $this->customer_id !== '' ? (int) $this->customer_id : null),
        ];
    }
}
