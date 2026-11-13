<?php

namespace App\Actions\Referential;

use App\Enums\EnvironmentType;
use App\Models\Environment;
use DomainException;

final class SaveEnvironment
{
    /**
     * @param  array{version_id: int, repository_id: ?int, type: EnvironmentType, name: string, url: ?string, deployed_ref: ?string, database_engine: ?string, database_version: ?string, php_version: ?string, server: ?string, notes: ?string}  $data
     */
    public function handle(array $data, ?Environment $environment = null): Environment
    {
        $environment ??= new Environment;
        $environment->fill($data);

        if ($environment->repository_id !== null && $environment->repository?->version_id !== $environment->version_id) {
            throw new DomainException("Le dépôt choisi n'appartient pas à cette version.");
        }

        $environment->save();

        return $environment;
    }
}
