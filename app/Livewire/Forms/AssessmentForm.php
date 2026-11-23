<?php

namespace App\Livewire\Forms;

use App\Enums\FeatureOperation;
use App\Enums\FeaturePresence;
use App\Enums\ImplementationStatus;
use App\Enums\VerificationStatus;
use App\Models\Implementation;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AssessmentForm extends Form
{
    /** Two axes of the README d'intégration, stored as one ImplementationStatus. */
    public string $presence = 'unknown';

    /** Only meaningful when the item is present. */
    public string $operation = 'not_tested';

    public string $verification_status = 'to_verify';

    public string $deviation = '';

    public string $assessment_note = '';

    public function setImplementation(Implementation $implementation): void
    {
        $this->presence = $implementation->status->presence()->value;
        $this->operation = $implementation->status->operation()->value;
        $this->verification_status = $implementation->verification_status->value;
        $this->deviation = (string) $implementation->deviation;
        $this->assessment_note = (string) $implementation->assessment_note;
    }

    /**
     * After a presence change, shows the operation that will be stored: not applicable or not
     * tested when the item is not present, not tested when it becomes present.
     */
    public function syncOperation(): void
    {
        $presence = FeaturePresence::from($this->presence);
        $operation = FeatureOperation::tryFrom($this->operation) ?? FeatureOperation::NotTested;

        $this->operation = $presence === FeaturePresence::Present && $operation === FeatureOperation::NotApplicable
            ? FeatureOperation::NotTested->value
            : ImplementationStatus::fromAxes($presence, $operation)->operation()->value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'presence' => ['required', Rule::enum(FeaturePresence::class)],
            'operation' => ['required', Rule::enum(FeatureOperation::class)],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'deviation' => ['nullable', 'string', 'max:20000'],
            'assessment_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['presence' => 'présence', 'operation' => 'fonctionnement', 'deviation' => 'description fonctionnelle', 'assessment_note' => 'commentaire'];
    }

    /**
     * @return array{status: ImplementationStatus, deviation: ?string, assessment_note: ?string, verification_status: VerificationStatus}
     */
    public function data(): array
    {
        $this->validate();

        return [
            'status' => ImplementationStatus::fromAxes(FeaturePresence::from($this->presence), FeatureOperation::from($this->operation)),
            'deviation' => trim($this->deviation) !== '' ? $this->deviation : null,
            'assessment_note' => trim($this->assessment_note) !== '' ? $this->assessment_note : null,
            'verification_status' => VerificationStatus::from($this->verification_status),
        ];
    }
}
