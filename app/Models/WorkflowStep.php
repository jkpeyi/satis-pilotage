<?php

namespace App\Models;

use App\Enums\WorkflowStepKind;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workflow_id', 'position', 'kind', 'name', 'actor', 'feature_id', 'description'])]
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
            'kind' => WorkflowStepKind::class,
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'action',
    ];

    /**
     * Exits towards other steps, in order (RG-17).
     *
     * @return HasMany<WorkflowTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)->orderBy('position')->orderBy('id');
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
