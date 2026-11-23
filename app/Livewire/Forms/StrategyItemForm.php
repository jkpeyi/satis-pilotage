<?php

namespace App\Livewire\Forms;

use App\Enums\DecisionStatus;
use App\Enums\RiskLevel;
use App\Models\StrategyDecision;
use App\Models\StrategyOption;
use App\Models\StrategyPhase;
use App\Models\StrategyRisk;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * One form for the items of a strategy: scenario (option), migration phase, risk or decision.
 * $kind says which fields apply.
 */
class StrategyItemForm extends Form
{
    /** @var 'option'|'phase'|'risk'|'decision' */
    public string $kind = 'option';

    public ?int $itemId = null;

    public string $name = '';

    public string $description = '';

    public string $benefits = '';

    public string $drawbacks = '';

    public string $effort_min_days = '';

    public string $effort_max_days = '';

    public string $risk_level = '';

    public bool $is_recommended = false;

    public string $prerequisites = '';

    public string $rollback = '';

    public string $probability = '';

    public string $impact = '';

    public string $mitigation = '';

    public string $owner_id = '';

    public string $strategy_option_id = '';

    public string $rationale = '';

    public string $status = 'proposed';

    public string $decided_on = '';

    /**
     * @param  'option'|'phase'|'risk'|'decision'  $kind
     */
    public function start(string $kind): void
    {
        $this->reset();
        $this->resetValidation();
        $this->kind = $kind;
    }

    public function setItem(StrategyOption|StrategyPhase|StrategyRisk|StrategyDecision $item): void
    {
        $this->reset();
        $this->resetValidation();
        $this->itemId = $item->id;
        $number = fn (?string $value): string => $value !== null ? rtrim(rtrim($value, '0'), '.') : '';

        if ($item instanceof StrategyOption) {
            $this->kind = 'option';
            $this->name = $item->name;
            $this->description = (string) $item->description;
            $this->benefits = (string) $item->benefits;
            $this->drawbacks = (string) $item->drawbacks;
            $this->effort_min_days = $number($item->effort_min_days);
            $this->effort_max_days = $number($item->effort_max_days);
            $this->risk_level = (string) $item->risk_level?->value;
            $this->is_recommended = $item->is_recommended;
        } elseif ($item instanceof StrategyPhase) {
            $this->kind = 'phase';
            $this->name = $item->name;
            $this->description = (string) $item->description;
            $this->prerequisites = (string) $item->prerequisites;
            $this->rollback = (string) $item->rollback;
        } elseif ($item instanceof StrategyRisk) {
            $this->kind = 'risk';
            $this->description = $item->description;
            $this->probability = (string) $item->probability?->value;
            $this->impact = (string) $item->impact?->value;
            $this->mitigation = (string) $item->mitigation;
            $this->owner_id = (string) $item->owner_id;
        } else {
            $this->kind = 'decision';
            $this->name = $item->title;
            $this->strategy_option_id = (string) $item->strategy_option_id;
            $this->rationale = (string) $item->rationale;
            $this->status = $item->status->value;
            $this->decided_on = (string) $item->decided_on?->toDateString();
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $levels = ['nullable', Rule::enum(RiskLevel::class)];

        return match ($this->kind) {
            'option' => [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:20000'],
                'benefits' => ['nullable', 'string', 'max:10000'],
                'drawbacks' => ['nullable', 'string', 'max:10000'],
                'effort_min_days' => ['nullable', 'numeric', 'min:0', 'max:999999'],
                'effort_max_days' => ['nullable', 'numeric', 'min:0', 'max:999999', ...($this->effort_min_days !== '' ? ['gte:effort_min_days'] : [])],
                'risk_level' => $levels,
            ],
            'phase' => [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:20000'],
                'prerequisites' => ['nullable', 'string', 'max:10000'],
                'rollback' => ['nullable', 'string', 'max:10000'],
            ],
            'risk' => [
                'description' => ['required', 'string', 'max:5000'],
                'probability' => $levels,
                'impact' => $levels,
                'mitigation' => ['nullable', 'string', 'max:10000'],
                'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            ],
            default => [
                'name' => ['required', 'string', 'max:255'],
                'strategy_option_id' => ['nullable', 'integer', Rule::exists('strategy_options', 'id')],
                'rationale' => [Rule::requiredIf($this->status !== 'proposed'), 'nullable', 'string', 'max:10000'],
                'status' => ['required', Rule::enum(DecisionStatus::class)],
                'decided_on' => ['nullable', 'date'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => $this->kind === 'decision' ? 'décision' : 'nom', 'benefits' => 'avantages', 'drawbacks' => 'inconvénients',
            'effort_min_days' => 'effort minimum', 'effort_max_days' => 'effort maximum', 'rationale' => 'justification',
            'mitigation' => 'mesure de maîtrise', 'prerequisites' => 'prérequis',
        ];
    }

    /**
     * Typed values of the current kind, ready for the Action.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        foreach (['effort_min_days', 'effort_max_days'] as $field) {
            $this->{$field} = str_replace(',', '.', trim($this->{$field}));
        }
        if ($this->effort_min_days !== '' && $this->effort_max_days === '') {
            $this->effort_max_days = $this->effort_min_days;
        }
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? $value : null;
        $level = fn (string $value): ?RiskLevel => $value !== '' ? RiskLevel::from($value) : null;

        return match ($this->kind) {
            'option' => [
                'name' => trim($this->name), 'description' => $nullable($this->description), 'benefits' => $nullable($this->benefits),
                'drawbacks' => $nullable($this->drawbacks), 'effort_min_days' => $nullable($this->effort_min_days),
                'effort_max_days' => $nullable($this->effort_max_days), 'risk_level' => $level($this->risk_level), 'is_recommended' => $this->is_recommended,
            ],
            'phase' => [
                'name' => trim($this->name), 'description' => $nullable($this->description),
                'prerequisites' => $nullable($this->prerequisites), 'rollback' => $nullable($this->rollback),
            ],
            'risk' => [
                'description' => trim($this->description), 'probability' => $level($this->probability), 'impact' => $level($this->impact),
                'mitigation' => $nullable($this->mitigation), 'owner_id' => $this->owner_id !== '' ? (int) $this->owner_id : null,
            ],
            default => [
                'title' => trim($this->name), 'strategy_option_id' => $this->strategy_option_id !== '' ? (int) $this->strategy_option_id : null,
                'rationale' => $nullable($this->rationale), 'status' => DecisionStatus::from($this->status), 'decided_on' => $nullable($this->decided_on),
            ],
        };
    }
}
