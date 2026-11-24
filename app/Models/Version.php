<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use App\Models\Concerns\Verifiable;
use Database\Factories\VersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deployment of a product for a customer. A version without customer is a baseline.
 */
#[Fillable(['product_id', 'customer_id', 'name', 'status', 'is_historical_pivot', 'technical_owner_id', 'notes'])]
class Version extends Model
{
    /** @use HasFactory<VersionFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps, Verifiable;

    public const CODE_PREFIX = 'APP-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_production',
        'is_historical_pivot' => false,
        'verification_status' => VerificationStatus::ToVerify->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
            'is_historical_pivot' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function technicalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_owner_id');
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /**
     * @return HasMany<Component, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * @return HasMany<Implementation, $this>
     */
    public function implementations(): HasMany
    {
        return $this->hasMany(Implementation::class);
    }

    /**
     * Versions counted as applications of the estate: a future perspective (e.g. Satis_SaaS) is not (SPEC-001, scenario 9).
     *
     * @param  Builder<Version>  $query
     * @return Builder<Version>
     */
    public function scopeExcludingFuture(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), '!=', VersionStatus::Future->value);
    }

    public function isFuture(): bool
    {
        return $this->status === VersionStatus::Future;
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
