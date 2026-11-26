<?php

namespace App\Livewire\Versions;

use App\Actions\Architecture\SaveComponent;
use App\Actions\Referential\ArchiveRecord;
use App\Enums\ComponentType;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\ComponentForm;
use App\Models\Component as ComponentModel;
use App\Models\Version;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Technical components of a version (SPEC-001 L3), shown in the version page under the repositories.
 */
class Architecture extends Component
{
    use EnumOptions;

    public Version $version;

    public ComponentForm $componentForm;

    public bool $showComponentForm = false;

    /** Follows the « show archived items » toggle of the version page. */
    #[Reactive]
    public bool $showArchived = false;

    public function createComponent(): void
    {
        $this->componentForm->forVersion($this->version);
        $this->showComponentForm = true;
    }

    public function editComponent(int $componentId): void
    {
        $this->componentForm->setComponent($this->findComponent($componentId));
        $this->showComponentForm = true;
    }

    public function saveComponent(SaveComponent $saveComponent): void
    {
        try {
            $saveComponent->handle($this->componentForm->data(), $this->componentForm->editedComponent);
        } catch (DomainException $exception) {
            $this->addError('componentForm.repository_id', $exception->getMessage());

            return;
        }

        $this->showComponentForm = false;
        $this->dispatch('notify', message: 'Composant enregistré.');
    }

    public function toggleComponentArchive(int $componentId, ArchiveRecord $archiveRecord): void
    {
        $component = $this->findComponent($componentId);
        $archiveRecord->handle($component, ! $component->isArchived());
    }

    public function render(): View
    {
        $this->version->load(['product', 'customer']);

        $components = $this->version->components()
            ->with(['repository', 'verifier'])
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('type')->orderBy('name')
            ->get();

        return view('livewire.versions.architecture', [
            'components' => $components,
            'componentTypes' => $this->enumOptions(ComponentType::class),
            'repositoryOptions' => $this->version->repositories()->whereNull('archived_at')->orderBy('path')->pluck('path', 'id'),
        ]);
    }

    private function findComponent(int $componentId): ComponentModel
    {
        return $this->version->components()->findOrFail($componentId);
    }
}
