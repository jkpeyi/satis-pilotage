<?php

namespace App\Livewire\Technologies;

use App\Actions\Referential\SaveTechnology;
use App\Enums\SupportStatus;
use App\Livewire\Forms\TechnologyForm;
use App\Models\Technology;
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
        $technologies = Technology::query()->orderBy('component')->get()
            ->sort(fn (Technology $a, Technology $b): int => [$a->component, version_compare($b->cycle, $a->cycle)] <=> [$b->component, 0]);

        return view('livewire.technologies.index', [
            'technologies' => $technologies->groupBy('component'),
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
