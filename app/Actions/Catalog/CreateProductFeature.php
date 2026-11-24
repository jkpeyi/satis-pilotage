<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Creates a feature in the shared catalog from a product's screen (e.g. a workflow step) and tags
 * it with the product, so that it is offered among the product's features. The name must be new
 * in the whole catalog (RG-15).
 */
final class CreateProductFeature
{
    public function __construct(private readonly CreateFeature $createFeature) {}

    public function handle(Product $product, string $name, int $moduleId): Feature
    {
        return DB::transaction(function () use ($product, $name, $moduleId): Feature {
            $feature = $this->createFeature->handle(['name' => $name, 'module_id' => $moduleId]);
            $feature->products()->syncWithoutDetaching([$product->id]);

            return $feature;
        });
    }
}
