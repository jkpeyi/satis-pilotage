<?php

namespace App\Models;

use App\Contracts\CatalogItem;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog module. A module with a parent is a sub-module; depth is unlimited (RG-01).
 */
#[Fillable(['parent_id', 'code', 'name', 'standard_description', 'position'])]
class Module extends Model implements CatalogItem
{
    /** @use HasFactory<ModuleFactory> */
    use Archivable, HasFactory, HasUserstamps;

    /**
     * @return BelongsTo<Module, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Module::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return HasMany<Feature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class)->orderBy('position');
    }

    /**
     * @return HasMany<Implementation, $this>
     */
    public function implementations(): HasMany
    {
        return $this->hasMany(Implementation::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function isSubmodule(): bool
    {
        return $this->parent_id !== null;
    }

    public function typeLabel(): string
    {
        return $this->isSubmodule() ? 'Sous-module' : 'Module';
    }
}
