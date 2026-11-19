<?php

namespace App\Models;

use App\Contracts\CatalogItem;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasNameKey;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog feature. Always belongs to a module or a sub-module (RG-01).
 *
 * @property string $name_key Comparison key of the name (App\Support\CatalogName), kept in sync on save.
 */
#[Fillable(['module_id', 'code', 'name', 'standard_description', 'position'])]
class Feature extends Model implements CatalogItem
{
    /** @use HasFactory<FeatureFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasNameKey, HasUserstamps;

    public const CODE_PREFIX = 'FCT-';

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
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

    public function typeLabel(): string
    {
        return 'Fonctionnalité';
    }
}
