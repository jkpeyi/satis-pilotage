<?php

namespace App\Livewire\Products;

use App\Actions\Referential\SaveProduct;
use App\Livewire\Forms\ProductForm;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    public ProductForm $form;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->form->setProduct($product);
        }
    }

    public function save(SaveProduct $saveProduct): void
    {
        $product = $saveProduct->handle($this->form->data(), $this->form->product);

        session()->flash('status', 'Produit enregistré.');
        $this->redirectRoute('products.show', $product, navigate: true);
    }

    public function render(): View
    {
        $title = $this->form->product ? 'Modifier '.$this->form->product->name : 'Nouveau produit';

        return view('livewire.products.form')->title($title);
    }
}
