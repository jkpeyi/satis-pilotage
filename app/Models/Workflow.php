<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Business workflow of a product (e.g. complaint handling), made of ordered steps.
 */
#[Fillable(['product_id', 'name', 'description'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use Archivable, HasDisplayCode, HasFactory, HasUserstamps;

    public const CODE_PREFIX = 'WF-';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position')->orderBy('id');
    }
}
