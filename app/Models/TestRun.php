<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A test suite run of a diagnostic campaign (L4): counts and line coverage, as measured outside
 * the platform. A value that was not measured stays empty.
 */
#[Fillable([
    'diagnostic_campaign_id', 'tool_name', 'tool_version', 'executed_on', 'tests_total', 'tests_passed',
    'tests_failed', 'tests_skipped', 'line_coverage', 'scope', 'source_file_name', 'source_sha256',
])]
class TestRun extends Model
{
    use Archivable, HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_on' => 'date',
            'tests_total' => 'integer',
            'tests_passed' => 'integer',
            'tests_failed' => 'integer',
            'tests_skipped' => 'integer',
            'line_coverage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<DiagnosticCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiagnosticCampaign::class, 'diagnostic_campaign_id');
    }

    public function toolLabel(): string
    {
        return trim($this->tool_name.' '.$this->tool_version);
    }
}
