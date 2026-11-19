<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Updates the catalog fields of a module or a feature: name, code, standard description, products.
 */
final class SaveCatalogItem
{
    public function __construct(private readonly EnsureUniqueCatalogName $ensureUniqueName) {}

    /**
     * @param  array{name: string, code: ?string, standard_description: ?string, product_ids: list<int>}  $data
     */
    public function handle(Module|Feature $item, array $data): void
    {
        if (! $item->isArchived()) {
            $this->ensureUniqueName->handle($data['name'], [$item]);
        }

        DB::transaction(function () use ($item, $data): void {
            $item->fill([
                'name' => trim($data['name']),
                'code' => $data['code'],
                'standard_description' => $data['standard_description'],
            ])->save();

            $item->products()->sync($data['product_ids']);
        });
    }
}
