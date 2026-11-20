<?php

namespace App\Models;

use App\Enums\ToolResultFormat;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported results file of an analysis tool, run outside the platform. Immutable.
 */
#[Fillable([
    'diagnostic_campaign_id', 'tool_name', 'tool_version', 'format', 'scanned_on',
    'file_name', 'file_sha256', 'results_count', 'new_count', 'duplicate_count',
])]
class ToolRun extends Model
{
    use HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ToolResultFormat::class,
            'scanned_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<DiagnosticCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiagnosticCampaign::class, 'diagnostic_campaign_id');
    }

    /**
     * @return HasMany<DiagnosticFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(DiagnosticFinding::class);
    }

    public function toolLabel(): string
    {
        return trim($this->tool_name.' '.$this->tool_version);
    }
}
