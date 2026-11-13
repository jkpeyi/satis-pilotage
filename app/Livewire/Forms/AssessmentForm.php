<?php

namespace App\Livewire\Forms;

use App\Enums\ImplementationStatus;
use App\Models\Implementation;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AssessmentForm extends Form
{
    public string $status = 'not_assessed';

    public string $deviation = '';

    public string $assessment_note = '';

    public function setImplementation(Implementation $implementation): void
    {
        $this->status = $implementation->status->value;
        $this->deviation = (string) $implementation->deviation;
        $this->assessment_note = (string) $implementation->assessment_note;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ImplementationStatus::class)],
            'deviation' => ['nullable', 'string', 'max:20000'],
            'assessment_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['status' => 'état', 'deviation' => 'écart', 'assessment_note' => 'commentaire'];
    }

    /**
     * @return array{status: ImplementationStatus, deviation: ?string, assessment_note: ?string}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'status' => ImplementationStatus::from($this->status),
            'deviation' => trim($this->deviation) !== '' ? $this->deviation : null,
            'assessment_note' => trim($this->assessment_note) !== '' ? $this->assessment_note : null,
        ];
    }
}
