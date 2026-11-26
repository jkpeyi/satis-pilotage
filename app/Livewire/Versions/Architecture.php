<?php

namespace App\Livewire\Versions;

use App\Actions\Architecture\SaveComponent;
use App\Actions\Architecture\SaveIntegration;
use App\Actions\Referential\ArchiveRecord;
use App\Enums\ComponentType;
use App\Enums\IntegrationProtocol;
use App\Enums\VerificationStatus;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\ComponentForm;
use App\Livewire\Forms\IntegrationForm;
use App\Models\Component as ComponentModel;
use App\Models\Integration;
use App\Models\Version;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Technical architecture of a version (SPEC-001 L3): its components and their integrations,
 * shown in the version page under the repositories.
 */
class Architecture extends Component
{
    use EnumOptions;

    public Version $version;

    public ComponentForm $componentForm;

    public IntegrationForm $integrationForm;

    public bool $showComponentForm = false;

    public bool $showIntegrationForm = false;

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

    public function createIntegration(?int $sourceComponentId = null): void
    {
        $this->integrationForm->forVersion($this->version->id);
        $this->integrationForm->source_component_id = (string) $sourceComponentId;
        $this->showIntegrationForm = true;
    }

    public function editIntegration(int $integrationId): void
    {
        $this->integrationForm->setIntegration($this->findIntegration($integrationId), $this->version->id);
        $this->showIntegrationForm = true;
    }

    public function saveIntegration(SaveIntegration $saveIntegration): void
    {
        try {
            $saveIntegration->handle($this->integrationForm->data(), $this->integrationForm->integration);
        } catch (DomainException $exception) {
            $this->addError('integrationForm.target_component_id', $exception->getMessage());

            return;
        }

        $this->showIntegrationForm = false;
        $this->dispatch('notify', message: 'Intégration enregistrée.');
    }

    public function toggleIntegrationArchive(int $integrationId, ArchiveRecord $archiveRecord): void
    {
        $integration = $this->findIntegration($integrationId);
        $archiveRecord->handle($integration, ! $integration->isArchived());
    }

    public function render(): View
    {
        $this->version->load(['product', 'customer']);

        $components = $this->version->components()
            ->with(['repository', 'verifier'])
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('type')->orderBy('name')
            ->get();

        $integrations = Integration::query()
            ->with(['source', 'target.version', 'verifier'])
            ->whereHas('source', fn ($query) => $query->where('version_id', $this->version->id))
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('source_component_id')->orderBy('id')
            ->get();

        $activeComponents = $components->reject(fn (ComponentModel $component): bool => $component->isArchived());

        return view('livewire.versions.architecture', [
            'components' => $components,
            'integrations' => $integrations,
            'componentTypes' => $this->enumOptions(ComponentType::class),
            'protocols' => $this->enumOptions(IntegrationProtocol::class),
            'verificationStatuses' => $this->enumOptions(VerificationStatus::class),
            'repositoryOptions' => $this->version->repositories()->whereNull('archived_at')->orderBy('path')->pluck('path', 'id'),
            'sourceOptions' => $activeComponents->mapWithKeys(fn (ComponentModel $component): array => [$component->id => $component->displayCode().' · '.$component->name]),
            // A target can belong to another application of the same product (e.g. a shared service).
            'targetOptions' => ComponentModel::query()
                ->active()
                ->whereHas('version', fn ($query) => $query->where('product_id', $this->version->product_id)->whereNull('archived_at'))
                ->with('version')
                ->orderBy('version_id')->orderBy('name')
                ->get()
                ->mapWithKeys(fn (ComponentModel $component): array => [
                    $component->id => $component->displayCode().' · '.$component->name.($component->version_id !== $this->version->id ? ' ('.$component->version->name.')' : ''),
                ]),
        ]);
    }

    private function findComponent(int $componentId): ComponentModel
    {
        return $this->version->components()->findOrFail($componentId);
    }

    private function findIntegration(int $integrationId): Integration
    {
        return Integration::query()
            ->whereHas('source', fn ($query) => $query->where('version_id', $this->version->id))
            ->findOrFail($integrationId);
    }
}
