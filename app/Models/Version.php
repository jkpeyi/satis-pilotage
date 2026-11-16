<?php

namespace App\Models;

use App\Enums\VersionStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\VersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deployment of a product for a customer. A version without customer is a baseline.
 */
#[Fillable(['product_id', 'customer_id', 'name', 'status', 'notes'])]
class Version extends Model
{
    /** @use HasFactory<VersionFactory> */
    use Archivable, HasFactory, HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /**
     * @return HasMany<Environment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    /**
     * @return HasMany<Implementation, $this>
     */
    public function implementations(): HasMany
    {
        return $this->hasMany(Implementation::class);
    }

    public function isBaseline(): bool
    {
        return $this->customer_id === null;
    }

    public function isReferenceOfItsProduct(): bool
    {
        return $this->product->reference_version_id === $this->id;
    }
}
