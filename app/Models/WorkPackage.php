<?php

namespace App\Models;

use App\Enums\WorkPackageStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work package (« lot ») of the remediation roadmap, with its milestone (L8), displayed LOT-0001.
 */
#[Fillable(['product_id', 'name', 'objective', 'starts_on', 'due_on', 'status', 'position'])]
class WorkPackage extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'LOT-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkPackageStatus::class,
            'starts_on' => 'date',
            'due_on' => 'date',
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
     * @return HasMany<RemediationAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(RemediationAction::class);
    }
}
