<?php

namespace App\Actions\Workflows;

use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

/**
 * Moves a step one place up or down among the active steps of its workflow, then renumbers
 * them 1..n so that "étape 3" always means the third step.
 */
final class MoveWorkflowStep
{
    public function handle(WorkflowStep $step, int $direction): void
    {
        DB::transaction(function () use ($step, $direction): void {
            $steps = $step->workflow->steps()->active()->lockForUpdate()->get()->values();
            $index = $steps->search(fn (WorkflowStep $candidate): bool => $candidate->id === $step->id);

            if ($index === false) {
                return;
            }

            $target = $index + ($direction < 0 ? -1 : 1);
            if ($target >= 0 && $target < $steps->count()) {
                $ordered = $steps->all();
                [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
                $steps = collect($ordered);
            }

            $steps->each(function (WorkflowStep $candidate, int $position): void {
                if ($candidate->position !== $position + 1) {
                    $candidate->update(['position' => $position + 1]);
                }
            });
        });
    }
}
