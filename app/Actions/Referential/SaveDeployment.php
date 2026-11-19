<?php

namespace App\Actions\Referential;

use App\Enums\VerificationStatus;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\Release;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Records or corrects a deployment of an environment (SPEC-001 L1), then refreshes the
 * environment's deployed reference from its latest deployment.
 */
final class SaveDeployment
{
    public function __construct(private readonly SyncDeployedRef $syncDeployedRef) {}

    /**
     * @param  array{environment_id: int, release_id: ?int, commit_sha: ?string, deployed_at: string, verification_status: VerificationStatus, evidence: ?string, notes: ?string}  $data
     */
    public function handle(array $data, ?Deployment $deployment = null): Deployment
    {
        $environment = Environment::query()->findOrFail($data['environment_id']);

        if ($data['release_id'] === null && $data['commit_sha'] === null) {
            throw new DomainException('Indiquez la release ou le commit déployé.');
        }

        if ($data['release_id'] !== null) {
            $release = Release::query()->with('repository')->findOrFail($data['release_id']);
            $belongs = $release->repository->version_id === $environment->version_id
                && ($environment->repository_id === null || $environment->repository_id === $release->repository_id);

            if (! $belongs) {
                throw new DomainException('Cette release ne provient pas du dépôt de cet environnement.');
            }
        }

        return DB::transaction(function () use ($data, $deployment, $environment): Deployment {
            $deployment ??= new Deployment;
            $deployment->fill(collect($data)->except('verification_status')->all());
            $deployment->applyVerificationStatus($data['verification_status']);
            $deployment->save();

            $this->syncDeployedRef->handle($environment);

            return $deployment;
        });
    }
}
