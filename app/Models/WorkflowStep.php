<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['workflow_id', 'position', 'name', 'actor', 'feature_id', 'description'])]
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use Archivable, HasFactory, HasUserstamps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * @return BelongsToMany<BusinessRule, $this>
     */
    public function businessRules(): BelongsToMany
    {
        return $this->belongsToMany(BusinessRule::class);
    }

    /**
     * Stable label, e.g. "WF-0003 · étape 2".
     */
    public function displayCode(): string
    {
        return $this->workflow->displayCode().' · étape '.$this->position;
    }
}
