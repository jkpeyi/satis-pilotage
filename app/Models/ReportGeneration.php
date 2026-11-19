<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One generated deliverable. Immutable once its files are written: a new export is a new row.
 */
#[Fillable([
    'code', 'title', 'scope', 'scope_hash', 'scope_label', 'template_file', 'template_version', 'status',
    'version_number', 'completeness', 'missing_count', 'snapshot', 'data_sha256',
    'docx_path', 'docx_sha256', 'xlsx_path', 'xlsx_sha256', 'created_by',
])]
class ReportGeneration extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::updating(function (ReportGeneration $generation): void {
            if ($generation->getOriginal('docx_path') !== null) {
                throw new LogicException('A generated report is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('A generated report is immutable.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'completeness' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Document identifier printed in the control block, e.g. L1-0007.
     */
    public function documentId(): string
    {
        return sprintf('%s-%04d', $this->code, $this->id);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Brouillon',
            default => $this->status,
        };
    }
}
