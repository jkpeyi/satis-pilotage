<?php

namespace App\Models;

use App\Contracts\CatalogItem;
use App\Enums\ImplementationStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use App\Observers\ImplementationObserver;
use Database\Factories\ImplementationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Rattachement": how a version implements exactly one module or one feature (RG-03).
 */
#[Fillable([
    'version_id', 'module_id', 'feature_id', 'status', 'deviation',
    'assessment_note', 'assessed_by', 'assessed_at',
])]
#[ObservedBy(ImplementationObserver::class)]
class Implementation extends Model
{
    /** @use HasFactory<ImplementationFactory> */
    use Archivable, HasFactory, HasUserstamps;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'not_assessed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImplementationStatus::class,
            'assessed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * The module or the feature this implementation targets.
     */
    public function catalogItem(): CatalogItem
    {
        /** @var Module|Feature $item */
        $item = $this->module_id !== null ? $this->module : $this->feature;

        return $item;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /**
     * @return HasMany<ImplementationStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ImplementationStatusChange::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<BusinessRule, $this>
     */
    public function businessRules(): HasMany
    {
        return $this->hasMany(BusinessRule::class);
    }

    /**
     * @return BelongsToMany<Issue, $this>
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class);
    }
}
