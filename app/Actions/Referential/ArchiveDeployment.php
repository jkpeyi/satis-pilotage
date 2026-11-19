<?php

namespace App\Actions\Referential;

use App\Models\Deployment;

final class ArchiveDeployment
{
    public function __construct(private readonly SyncDeployedRef $syncDeployedRef) {}

    public function handle(Deployment $deployment, bool $archive = true): void
    {
        $archive ? $deployment->archive() : $deployment->unarchive();
        $this->syncDeployedRef->handle($deployment->environment);
    }
}
