<?php

namespace App\Actions\Workflows;

use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

/**
 * Archives a step and renumbers the remaining ones; a restored step goes last (RG-12).
 */
final class ArchiveWorkflowStep
{
    public function handle(WorkflowStep $step, bool $archive = true): void
    {
        DB::transaction(function () use ($step, $archive): void {
            $workflow = $step->workflow;

            if ($archive) {
                $step->archive();
            } else {
                $step->position = (int) $workflow->steps()->active()->max('position') + 1;
                $step->unarchive();
            }

            $workflow->steps()->active()->get()->values()->each(function (WorkflowStep $candidate, int $index): void {
                if ($candidate->position !== $index + 1) {
                    $candidate->update(['position' => $index + 1]);
                }
            });
        });
    }
}
