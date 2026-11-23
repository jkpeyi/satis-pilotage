<?php

namespace App\Models;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasDisplayCode;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Action of the remediation roadmap (L8), displayed ACT-0001. It treats a problem of the debt
 * register; it is done only with its acceptance criteria and test evidence (RG-19).
 */
#[Fillable([
    'product_id', 'work_package_id', 'issue_id', 'title', 'description', 'priority', 'status', 'owner_id',
    'starts_on', 'due_on', 'completed_on', 'effort_days', 'acceptance_criteria', 'test_evidence', 'gitlab_issue_url',
])]
class RemediationAction extends Model
{
    use Archivable, HasDisplayCode, HasUserstamps;

    public const CODE_PREFIX = 'ACT-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 'p2',
        'status' => 'todo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => ActionPriority::class,
            'status' => ActionStatus::class,
            'starts_on' => 'date',
            'due_on' => 'date',
            'completed_on' => 'date',
            'effort_days' => 'decimal:1',
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
     * @return BelongsTo<WorkPackage, $this>
     */
    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Actions that must be done before this one.
     *
     * @return BelongsToMany<RemediationAction, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'remediation_action_dependencies', 'action_id', 'depends_on_id');
    }

    /**
     * @return BelongsToMany<RemediationAction, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'remediation_action_dependencies', 'depends_on_id', 'action_id');
    }

    /**
     * Open and past its due date.
     */
    public function isLate(): bool
    {
        return $this->status->isOpen() && $this->due_on !== null && $this->due_on->isBefore(today());
    }
}
