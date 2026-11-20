<?php

namespace App\Models;

use App\Enums\FindingLevel;
use App\Enums\FindingStatus;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A result imported from a tool (L4), displayed RES-0001. It becomes part of a problem only once
 * a person has retained it (RG-18).
 */
#[Fillable([
    'diagnostic_campaign_id', 'tool_run_id', 'fingerprint', 'rule_id', 'level', 'message',
    'path', 'line_start', 'line_end', 'occurrences', 'status', 'issue_id', 'triage_note', 'triaged_by', 'triaged_at',
])]
class DiagnosticFinding extends Model
{
    use HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'RES-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'occurrences' => 1,
        'status' => 'to_verify',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => FindingLevel::class,
            'status' => FindingStatus::class,
            'line_start' => 'integer',
            'line_end' => 'integer',
            'triaged_at' => 'datetime',
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
     * @return BelongsTo<ToolRun, $this>
     */
    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    /**
     * "app/Http/Kernel.php:42-45", or null without location.
     */
    public function location(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        if ($this->line_start === null) {
            return $this->path;
        }

        return $this->path.':'.$this->line_start.($this->line_end !== null && $this->line_end !== $this->line_start ? '-'.$this->line_end : '');
    }
}
