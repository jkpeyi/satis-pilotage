<?php

namespace App\Actions\Workflows;

use App\Models\Workflow;

final class SaveWorkflow
{
    /**
     * @param  array{product_id: int, name: string, description: ?string}  $data
     */
    public function handle(array $data, ?Workflow $workflow = null): Workflow
    {
        $workflow ??= new Workflow;
        $workflow->fill($data)->save();

        return $workflow;
    }
}
