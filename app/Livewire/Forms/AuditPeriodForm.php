<?php

namespace App\Livewire\Forms;

use App\Models\AuditPeriod;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AuditPeriodForm extends Form
{
    public ?AuditPeriod $period = null;

    public string $product_id = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $hours_planned = '';

    public string $hours_actual = '';

    public string $support_hours = '';

    public string $support_interruptions = '';

    public string $notes = '';

    public function setPeriod(AuditPeriod $period): void
    {
        $this->resetValidation();
        $this->period = $period;
        $this->product_id = (string) $period->product_id;
        $this->starts_on = $period->starts_on->toDateString();
        $this->ends_on = $period->ends_on->toDateString();
        $this->hours_planned = $this->number($period->hours_planned);
        $this->hours_actual = $this->number($period->hours_actual);
        $this->support_hours = $this->number($period->support_hours);
        $this->support_interruptions = (string) $period->support_interruptions;
        $this->notes = (string) $period->notes;
    }

    private function number(?string $value): string
    {
        return $value === null ? '' : rtrim(rtrim($value, '0'), '.');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'hours_planned' => ['required', 'numeric', 'min:0', 'max:99999'],
            'hours_actual' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'support_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'support_interruptions' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'product_id' => 'produit', 'starts_on' => 'début', 'ends_on' => 'fin', 'hours_planned' => 'heures prévues',
            'hours_actual' => 'heures réalisées', 'support_hours' => 'heures de support', 'support_interruptions' => 'interruptions',
        ];
    }

    /**
     * @return array{product_id: int, starts_on: string, ends_on: string, hours_planned: string, hours_actual: ?string, support_hours: ?string, support_interruptions: ?int, notes: ?string}
     */
    public function data(): array
    {
        foreach (['hours_planned', 'hours_actual', 'support_hours'] as $field) {
            $this->{$field} = str_replace(',', '.', trim($this->{$field}));
        }
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? trim($value) : null;

        return [
            'product_id' => (int) $this->product_id,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'hours_planned' => $this->hours_planned,
            'hours_actual' => $nullable($this->hours_actual),
            'support_hours' => $nullable($this->support_hours),
            'support_interruptions' => trim($this->support_interruptions) !== '' ? (int) $this->support_interruptions : null,
            'notes' => $nullable($this->notes),
        ];
    }
}
