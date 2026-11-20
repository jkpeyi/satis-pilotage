<?php

namespace App\Actions\Audit;

use App\Models\AuditPeriod;
use DomainException;

/**
 * Records the audit effort of a product over a period (L6). Periods of a product do not overlap,
 * so that hours are never counted twice.
 */
final class SaveAuditPeriod
{
    /**
     * @param  array{product_id: int, starts_on: string, ends_on: string, hours_planned: string, hours_actual: ?string, support_hours: ?string, support_interruptions: ?int, notes: ?string}  $data
     */
    public function handle(array $data, ?AuditPeriod $period = null): AuditPeriod
    {
        if ($data['ends_on'] < $data['starts_on']) {
            throw new DomainException('La fin de la période précède son début.');
        }

        $overlap = AuditPeriod::query()
            ->active()
            ->where('product_id', $data['product_id'])
            ->where('starts_on', '<=', $data['ends_on'])
            ->where('ends_on', '>=', $data['starts_on'])
            ->when($period !== null, fn ($query) => $query->whereKeyNot($period?->id))
            ->first();
        if ($overlap !== null) {
            throw new DomainException("Cette période chevauche celle {$overlap->label()} : les heures seraient comptées deux fois.");
        }

        $period ??= new AuditPeriod;
        $period->fill($data)->save();

        return $period;
    }
}
