<?php

namespace App\Models;

use App\Models\Concerns\HasUserstamps;
use Carbon\CarbonInterface;
use Database\Factories\TechnologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Support lifecycle of a technology release cycle, e.g. ('php', '8.1').
 */
#[Fillable(['component', 'cycle', 'end_of_support'])]
class Technology extends Model
{
    /** @use HasFactory<TechnologyFactory> */
    use HasFactory, HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'end_of_support' => 'date',
        ];
    }

    public function isOutOfSupport(?CarbonInterface $at = null): bool
    {
        return $this->end_of_support !== null
            && $this->end_of_support->lt(($at ?? now())->startOfDay());
    }

    /**
     * True when support ends within the given number of months (EF-503 warns at 6 months).
     */
    public function endsSupportWithin(int $months, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->end_of_support !== null
            && ! $this->isOutOfSupport($at)
            && $this->end_of_support->lte($at->copy()->addMonths($months));
    }
}
