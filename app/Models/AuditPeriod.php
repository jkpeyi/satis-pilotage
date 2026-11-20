<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit effort of a product over a period (L6): planned and actual hours, support interruptions.
 * A value not known yet stays empty.
 */
#[Fillable(['product_id', 'starts_on', 'ends_on', 'hours_planned', 'hours_actual', 'support_hours', 'support_interruptions', 'notes'])]
class AuditPeriod extends Model
{
    use Archivable, HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'hours_planned' => 'decimal:1',
            'hours_actual' => 'decimal:1',
            'support_hours' => 'decimal:1',
            'support_interruptions' => 'integer',
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
     * "du 03/11/2026 au 14/11/2026".
     */
    public function label(): string
    {
        return 'du '.$this->starts_on->format('d/m/Y').' au '.$this->ends_on->format('d/m/Y');
    }
}
