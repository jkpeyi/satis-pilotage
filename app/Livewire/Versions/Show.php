<?php

namespace App\Livewire\Versions;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Referential\ArchiveVersion;
use App\Actions\Referential\ResolveStackSupport;
use App\Actions\Referential\SaveRepository;
use App\Actions\Referential\SetReferenceVersion;
use App\Enums\RepositoryRole;
use App\Livewire\Forms\RepositoryForm;
use App\Models\Repository;
use App\Models\Version;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "Fiche version": details and repositories with their stack.
 */
class Show extends Component
{
    public Version $version;

    public RepositoryForm $repositoryForm;

    public bool $showRepositoryForm = false;

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

    public function render(ResolveStackSupport $resolveStackSupport): View
    {
        $this->version->load(['product', 'customer', 'technicalOwner', 'verifier', 'creator', 'editor']);

        $repositories = $this->version->repositories()
            ->when(! $this->showArchivedItems, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('role')->orderBy('path')
            ->get();

        return view('livewire.versions.show', [
            'repositories' => $repositories,
            'stackSupport' => $repositories->mapWithKeys(fn (Repository $repository): array => [
                $repository->id => $resolveStackSupport->handle($repository->stack ?? []),
            ]),
            'roles' => collect(RepositoryRole::cases())->mapWithKeys(fn (RepositoryRole $role) => [$role->value => $role->label()]),
        ])->title($this->version->name);
    }
}
