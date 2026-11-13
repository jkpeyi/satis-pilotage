<?php

namespace App\Models;

use App\Enums\SnippetKind;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Code excerpt owned by exactly one business rule or one issue, optionally anchored in a repository.
 */
#[Fillable([
    'business_rule_id', 'issue_id', 'kind', 'language', 'content',
    'repository_id', 'commit_sha', 'path', 'line_start', 'line_end',
])]
class CodeSnippet extends Model
{
    use Archivable, HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SnippetKind::class,
        ];
    }

    /**
     * @return BelongsTo<BusinessRule, $this>
     */
    public function businessRule(): BelongsTo
    {
        return $this->belongsTo(BusinessRule::class);
    }

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * True when the snippet points to a precise commit (RG-10).
     */
    public function isAnchored(): bool
    {
        return $this->commit_sha !== null;
    }

    /**
     * Human readable location, e.g. app/Http/Controllers/LoanController.php:42-57.
     */
    public function location(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return match (true) {
            $this->line_start === null => $this->path,
            $this->line_end === null || $this->line_end === $this->line_start => "{$this->path}:{$this->line_start}",
            default => "{$this->path}:{$this->line_start}-{$this->line_end}",
        };
    }
}
