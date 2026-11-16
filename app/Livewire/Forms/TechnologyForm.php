<?php

namespace App\Livewire\Forms;

use App\Models\Technology;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TechnologyForm extends Form
{
    public ?Technology $technology = null;

    /** Named componentName: Livewire's Form already has a $component property. */
    public string $componentName = '';

    public string $cycle = '';

    public string $end_of_support = '';

    public function setTechnology(Technology $technology): void
    {
        $this->resetValidation();
        $this->technology = $technology;
        $this->componentName = $technology->component;
        $this->cycle = $technology->cycle;
        $this->end_of_support = (string) $technology->end_of_support?->format('Y-m-d');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'componentName' => ['required', 'string', 'max:64'],
            'cycle' => [
                'required', 'string', 'max:32', 'regex:/^[0-9][0-9.]*$/',
                Rule::unique('technologies', 'cycle')
                    ->where('component', strtolower(trim($this->componentName)))
                    ->ignore($this->technology),
            ],
            'end_of_support' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['componentName' => 'composant'];
    }

    /**
     * @return array{component: string, cycle: string, end_of_support: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'component' => $this->componentName,
            'cycle' => $this->cycle,
            'end_of_support' => $this->end_of_support !== '' ? $this->end_of_support : null,
        ];
    }
}
