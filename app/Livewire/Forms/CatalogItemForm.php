<?php

namespace App\Livewire\Forms;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CatalogItemForm extends Form
{
    public string $name = '';

    public string $code = '';

    public string $standard_description = '';

    /** @var list<string> */
    public array $product_ids = [];

    private ?string $table = null;

    private ?int $itemId = null;

    public function setItem(Module|Feature $item): void
    {
        $this->resetValidation();
        $this->name = $item->name;
        $this->code = (string) $item->code;
        $this->standard_description = (string) $item->standard_description;
        $this->product_ids = $item->products()->pluck('products.id')->map(fn ($id): string => (string) $id)->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => array_filter([
                'nullable', 'string', 'max:64', 'alpha_dash',
                $this->table !== null ? Rule::unique($this->table, 'code')->ignore($this->itemId) : null,
            ]),
            'standard_description' => ['nullable', 'string', 'max:20000'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['standard_description' => 'description standard', 'product_ids' => 'produits'];
    }

    /**
     * @return array{name: string, code: ?string, standard_description: ?string, product_ids: list<int>}
     */
    public function data(Module|Feature $item): array
    {
        $this->table = $item->getTable();
        $this->itemId = $item->id;
        $this->validate();

        return [
            'name' => $this->name,
            'code' => $this->code !== '' ? strtoupper($this->code) : null,
            'standard_description' => $this->standard_description !== '' ? $this->standard_description : null,
            'product_ids' => array_map('intval', $this->product_ids),
        ];
    }
}
