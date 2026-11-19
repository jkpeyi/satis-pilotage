<?php

namespace App\Livewire\Versions;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Referential\ArchiveVersion;
use App\Actions\Referential\ResolveStackSupport;
use App\Actions\Referential\SaveEnvironment;
use App\Actions\Referential\SaveRepository;
use App\Actions\Referential\SetReferenceVersion;
use App\Enums\EnvironmentType;
use App\Enums\RepositoryRole;
use App\Enums\VerificationStatus;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Concerns\ManagesReleasesAndDeployments;
use App\Livewire\Forms\EnvironmentForm;
use App\Livewire\Forms\RepositoryForm;
use App\Models\Deployment;
use App\Models\Release;
use App\Models\Repository;
use App\Models\Version;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "Fiche version": details, repositories with their stack and releases, environments and their deployments.
 */
class Show extends Component
{
    use EnumOptions, ManagesReleasesAndDeployments;

    public Version $version;

    public RepositoryForm $repositoryForm;

    public EnvironmentForm $environmentForm;

    public bool $showRepositoryForm = false;

    public bool $showEnvironmentForm = false;

    public bool $showArchivedItems = false;

    public function makeReference(SetReferenceVersion $setReferenceVersion): void
    {
        try {
            $setReferenceVersion->handle($this->version->product, $this->version);
        } catch (DomainException $exception) {
            $this->addError('reference', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Cette version est maintenant la référence du produit.');
        $this->redirectRoute('versions.show', $this->version, navigate: true);
    }

    public function toggleArchive(ArchiveVersion $archiveVersion): void
    {
        if ($this->version->isArchived()) {
            $this->version->unarchive();
            session()->flash('status', 'Version restaurée.');
        } else {
            $archiveVersion->handle($this->version);
            session()->flash('status', 'Version archivée.');
        }

        $this->redirectRoute('versions.show', $this->version, navigate: true);
    }

    public function createRepository(): void
    {
        $this->repositoryForm->forVersion($this->version);
        $this->showRepositoryForm = true;
    }

    public function editRepository(int $repositoryId): void
    {
        $this->repositoryForm->setRepository($this->version->repositories()->findOrFail($repositoryId));
        $this->showRepositoryForm = true;
    }

    public function addStackRow(): void
    {
        $this->repositoryForm->addStackRow();
    }

    public function removeStackRow(int $index): void
    {
        $this->repositoryForm->removeStackRow($index);
    }

    public function saveRepository(SaveRepository $saveRepository): void
    {
        $saveRepository->handle($this->repositoryForm->data(), $this->repositoryForm->repository);
        $this->showRepositoryForm = false;
        $this->dispatch('notify', message: 'Dépôt enregistré.');
    }

    public function toggleRepositoryArchive(int $repositoryId, ArchiveRecord $archiveRecord): void
    {
        $repository = $this->version->repositories()->findOrFail($repositoryId);
        $archiveRecord->handle($repository, ! $repository->isArchived());
    }

    public function createEnvironment(): void
    {
        $this->environmentForm->forVersion($this->version);
        $this->showEnvironmentForm = true;
    }

    public function editEnvironment(int $environmentId): void
    {
        $this->environmentForm->setEnvironment($this->version->environments()->findOrFail($environmentId));
        $this->showEnvironmentForm = true;
    }

    public function saveEnvironment(SaveEnvironment $saveEnvironment): void
    {
        $saveEnvironment->handle($this->environmentForm->data(), $this->environmentForm->environment);
        $this->showEnvironmentForm = false;
        $this->dispatch('notify', message: 'Environnement enregistré.');
    }

    public function toggleEnvironmentArchive(int $environmentId, ArchiveRecord $archiveRecord): void
    {
        $environment = $this->version->environments()->findOrFail($environmentId);
        $archiveRecord->handle($environment, ! $environment->isArchived());
    }

    public function render(ResolveStackSupport $resolveStackSupport): View
    {
        $this->version->load(['product', 'customer', 'technicalOwner', 'verifier', 'creator', 'editor']);

        $repositories = $this->version->repositories()
            ->when(! $this->showArchivedItems, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('role')->orderBy('path')
            ->get();

        $environments = $this->version->environments()
            ->with(['repository', 'deployments' => fn ($query) => $query->active()->with('release')->limit(1)])
            ->when(! $this->showArchivedItems, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('type')->orderBy('name')
            ->get();

        $releases = Release::query()
            ->with('repository')
            ->whereHas('repository', fn ($query) => $query->where('version_id', $this->version->id))
            ->when(! $this->showArchivedItems, fn ($query) => $query->whereNull('archived_at'))
            ->orderByDesc('released_on')->orderByDesc('id')
            ->get();

        $deployments = Deployment::query()
            ->with(['environment', 'release', 'verifier'])
            ->whereHas('environment', fn ($query) => $query->where('version_id', $this->version->id))
            ->when(! $this->showArchivedItems, fn ($query) => $query->whereNull('archived_at'))
            ->orderByDesc('deployed_at')->orderByDesc('id')
            ->get();

        $deploymentEnvironmentId = (int) $this->deploymentForm->environment_id;
        $deploymentRepositoryId = $environments->firstWhere('id', $deploymentEnvironmentId)?->repository_id;

        return view('livewire.versions.show', [
            'repositories' => $repositories,
            'environments' => $environments,
            'releases' => $releases,
            'deployments' => $deployments,
            'releaseOptions' => $releases
                ->filter(fn (Release $release): bool => ! $release->isArchived()
                    && ($deploymentRepositoryId === null || $release->repository_id === $deploymentRepositoryId))
                ->mapWithKeys(fn (Release $release): array => [$release->id => $release->name.' — '.$release->repository->path]),
            'environmentOptions' => $environments->filter(fn ($environment): bool => ! $environment->isArchived())->pluck('name', 'id'),
            'verificationStatuses' => $this->enumOptions(VerificationStatus::class),
            'stackSupport' => $repositories->mapWithKeys(fn (Repository $repository): array => [
                $repository->id => $resolveStackSupport->handle($repository->stack ?? []),
            ]),
            'repositoryOptions' => $this->version->repositories()->whereNull('archived_at')->pluck('path', 'id'),
            'roles' => collect(RepositoryRole::cases())->mapWithKeys(fn (RepositoryRole $role) => [$role->value => $role->label()]),
            'environmentTypes' => collect(EnvironmentType::cases())->mapWithKeys(fn (EnvironmentType $type) => [$type->value => $type->label()]),
        ])->title($this->version->name);
    }
}
