<?php

namespace App\Contracts;

use App\Models\Implementation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A module or a feature of the catalog ("élément").
 */
interface CatalogItem
{
    /**
     * @return HasMany<Implementation, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function implementations(): HasMany;

    /**
     * French label of the item type: Module, Sous-module or Fonctionnalité.
     */
    public function typeLabel(): string;
}
