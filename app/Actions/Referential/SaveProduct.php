<?php

namespace App\Actions\Referential;

use App\Models\Product;

final class SaveProduct
{
    /**
     * @param  array{code: string, name: string, description: ?string}  $data
     */
    public function handle(array $data, ?Product $product = null): Product
    {
        $product ??= new Product;
        $product->fill($data)->save();

        return $product;
    }
}
