<?php

namespace App\Actions\Referential;

use App\Enums\EnvironmentType;
use App\Models\Environment;

/**
 * Creates or updates an environment of a version: its kind, name and notes.
 */
final class SaveEnvironment
{
    /**
     * @param  array{version_id: int, type: EnvironmentType, name: string, notes: ?string}  $data
     */
    public function handle(array $data, ?Environment $environment = null): Environment
    {
        $environment ??= new Environment;
        $environment->fill($data)->save();

        return $environment;
    }
}
