<?php

namespace App\Livewire\Concerns;

use App\Actions\Referential\ArchiveDeployment;
use App\Actions\Referential\ArchiveRecord;
use App\Actions\Referential\SaveDeployment;
use App\Actions\Referential\SaveRelease;
use App\Livewire\Forms\DeploymentForm;
use App\Livewire\Forms\ReleaseForm;
use App\Models\Deployment;
use App\Models\Release;
use App\Models\Version;
use DomainException;

/**
 * Releases of the version's repositories and deployments of its environments (SPEC-001 L1).
 *
 * @property Version $version
 */
trait ManagesReleasesAndDeployments
{
    public ReleaseForm $releaseForm;

    public DeploymentForm $deploymentForm;

    public bool $showReleaseForm = false;

    public bool $showDeploymentForm = false;

    public function createRelease(?int $repositoryId = null): void
    {
        $this->releaseForm->forVersion($this->version, $repositoryId);
        $this->showReleaseForm = true;
    }

    public function editRelease(int $releaseId): void
    {
        $this->releaseForm->setRelease($this->findRelease($releaseId));
        $this->showReleaseForm = true;
    }

    public function saveRelease(SaveRelease $saveRelease): void
    {
        $saveRelease->handle($this->releaseForm->data(), $this->releaseForm->release);
        $this->showReleaseForm = false;
        $this->dispatch('notify', message: 'Release enregistrée.');
    }

    public function toggleReleaseArchive(int $releaseId, ArchiveRecord $archiveRecord): void
    {
        $release = $this->findRelease($releaseId);
        $archiveRecord->handle($release, ! $release->isArchived());
    }

    public function createDeployment(int $environmentId): void
    {
        $this->deploymentForm->forEnvironment($this->version->environments()->findOrFail($environmentId));
        $this->showDeploymentForm = true;
    }

    public function editDeployment(int $deploymentId): void
    {
        $this->deploymentForm->setDeployment($this->findDeployment($deploymentId));
        $this->showDeploymentForm = true;
    }

    public function saveDeployment(SaveDeployment $saveDeployment): void
    {
        try {
            $saveDeployment->handle($this->deploymentForm->data(), $this->deploymentForm->deployment);
        } catch (DomainException $exception) {
            $this->addError('deploymentForm.release_id', $exception->getMessage());

            return;
        }

        $this->showDeploymentForm = false;
        $this->dispatch('notify', message: 'Déploiement enregistré.');
    }

    public function toggleDeploymentArchive(int $deploymentId, ArchiveDeployment $archiveDeployment): void
    {
        $deployment = $this->findDeployment($deploymentId);
        $archiveDeployment->handle($deployment, ! $deployment->isArchived());
    }

    private function findRelease(int $releaseId): Release
    {
        return Release::query()
            ->whereHas('repository', fn ($query) => $query->where('version_id', $this->version->id))
            ->findOrFail($releaseId);
    }

    private function findDeployment(int $deploymentId): Deployment
    {
        return Deployment::query()
            ->whereHas('environment', fn ($query) => $query->where('version_id', $this->version->id))
            ->findOrFail($deploymentId);
    }
}
