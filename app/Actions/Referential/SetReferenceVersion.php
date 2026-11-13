<?php

namespace App\Actions\Referential;

use App\Models\Product;
use App\Models\Version;
use DomainException;

/**
 * Designates the default pivot of the comparison matrix (RG-08).
 * It may be a baseline version without customer, but it must belong to the product.
 */
final class SetReferenceVersion
{
    public function handle(Product $product, ?Version $version): void
    {
        if ($version !== null && $version->product_id !== $product->id) {
            throw new DomainException("La version {$version->name} n'appartient pas au produit {$product->name}.");
        }

        if ($version?->isArchived()) {
            throw new DomainException('Une version archivée ne peut pas être la référence.');
        }

        $product->referenceVersion()->associate($version);
        $product->save();
    }
}
