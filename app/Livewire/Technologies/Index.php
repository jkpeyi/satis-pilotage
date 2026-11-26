<?php

namespace App\Livewire\Technologies;

use App\Actions\Referential\SaveTechnology;
use App\Actions\Referential\SyncTechnologiesFromEstate;
use App\Enums\SupportStatus;
use App\Livewire\Forms\TechnologyForm;
use App\Models\Technology;
use App\Queries\TechnologyUsage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Technologies referential: support end dates used to flag obsolete stacks (EF-107).
 */
#[Title('Technologies')]
class Index extends Component
{
    public TechnologyForm $form;

    public bool $showForm = false;

    /** Only the cycles whose end-of-support date is still unknown. */
    public bool $onlyMissing = false;

    /**
     * Brings in the technologies already entered on repositories and components.
     */
    public function sync(SyncTechnologiesFromEstate $syncTechnologies): void
    {
        $added = $syncTechnologies->handle();

        $this->dispatch('notify', message: $added === 0
            ? 'Toutes les technologies des versions sont déjà dans le référentiel.'
            : "{$added} cycle(s) ajouté(s) depuis les versions : renseignez leur date de fin de support.");
    }

    public function create(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $technologyId): void
    {
        $this->form->setTechnology(Technology::query()->findOrFail($technologyId));
        $this->showForm = true;
    }

    public function save(SaveTechnology $saveTechnology): void
    {
        $saveTechnology->handle($this->form->data(), $this->form->technology);
        $this->showForm = false;
        $this->dispatch('notify', message: 'Technologie enregistrée.');
    }

    public function delete(int $technologyId): void
    {
        Technology::query()->whereKey($technologyId)->delete();
    }

    public function render(): View
    {
        $missing = Technology::query()->whereNull('end_of_support')->count();
        $technologies = Technology::query()
            ->when($this->onlyMissing, fn ($query) => $query->whereNull('end_of_support'))
            ->orderBy('component')->get()
            ->sort(fn (Technology $a, Technology $b): int => [$a->component, version_compare($b->cycle, $a->cycle)] <=> [$b->component, 0]);

        return view('livewire.technologies.index', [
            'missing' => $missing,
            'technologies' => $technologies->groupBy('component'),
            'usage' => app(TechnologyUsage::class)->versionsByTechnology(),
            'statuses' => $technologies->mapWithKeys(fn (Technology $technology): array => [
                $technology->id => match (true) {
                    $technology->end_of_support === null => SupportStatus::Unknown,
                    $technology->isOutOfSupport() => SupportStatus::OutOfSupport,
                    $technology->endsSupportWithin(6) => SupportStatus::EndingSoon,
                    default => SupportStatus::Supported,
                },
            ]),
        ]);
    }
}
