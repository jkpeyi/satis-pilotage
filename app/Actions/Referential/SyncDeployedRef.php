<?php

namespace App\Actions\Referential;

use App\Models\Deployment;
use App\Models\Environment;

/**
 * The environment's deployed reference follows its latest active deployment. Without any
 * deployment, a reference entered by hand is kept.
 */
final class SyncDeployedRef
{
    public function handle(Environment $environment): void
    {
        /** @var Deployment|null $latest */
        $latest = $environment->deployments()->active()->with('release')->first();

        if ($latest === null) {
            return;
        }

        $environment->deployed_ref = $latest->release->name ?? $latest->commit_sha;
        if ($environment->isDirty('deployed_ref')) {
            $environment->save();
        }
    }
}
