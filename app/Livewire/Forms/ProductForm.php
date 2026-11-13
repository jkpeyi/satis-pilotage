<?php

namespace App\Livewire\Forms;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProductForm extends Form
{
    public ?Product $product = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public function setProduct(Product $product): void
    {
        $this->product = $product;
        $this->code = $product->code;
        $this->name = $product->name;
        $this->description = (string) $product->description;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('products', 'code')->ignore($this->product)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{code: string, name: string, description: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
        ];
    }
}
