<?php

namespace App\Models;

use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exit of a workflow step towards another step, with its condition for a decision.
 * Part of the step's definition: saved and replaced with the step.
 */
#[Fillable(['workflow_step_id', 'target_step_id', 'label', 'position'])]
class WorkflowTransition extends Model
{
    use HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    /**
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'target_step_id');
    }
}
