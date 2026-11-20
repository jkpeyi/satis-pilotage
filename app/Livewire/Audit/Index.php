<?php

namespace App\Livewire\Audit;

use App\Actions\Audit\SaveAuditPeriod;
use App\Actions\Referential\ArchiveRecord;
use App\Livewire\Forms\AuditPeriodForm;
use App\Models\AuditPeriod;
use App\Models\Product;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Follow-up of the audit effort (L6): planned and actual hours, support interruptions, by period.
 */
#[Title('Suivi de l’audit')]
class Index extends Component
{
    public AuditPeriodForm $form;

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: false)]
    public bool $showArchived = false;

    public bool $showForm = false;

    public function create(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->form->product_id = $this->product;
        // Default: the period after the last one of the product, else the current week.
        $last = AuditPeriod::query()->active()->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))->latest('ends_on')->first();
        $start = $last !== null ? $last->ends_on->copy()->addDay() : now()->startOfWeek();
        $this->form->starts_on = $start->toDateString();
        $this->form->ends_on = $start->copy()->addDays(4)->toDateString();
        $this->showForm = true;
    }

    public function edit(int $periodId): void
    {
        $this->form->setPeriod(AuditPeriod::query()->findOrFail($periodId));
        $this->showForm = true;
    }

    public function save(SaveAuditPeriod $savePeriod): void
    {
        try {
            $savePeriod->handle($this->form->data(), $this->form->period);
        } catch (DomainException $exception) {
            $this->addError('form.starts_on', $exception->getMessage());

            return;
        }

        $this->showForm = false;
        $this->dispatch('notify', message: 'Période enregistrée.');
    }

    public function toggleArchive(int $periodId, ArchiveRecord $archiveRecord): void
    {
        $period = AuditPeriod::query()->findOrFail($periodId);
        $archiveRecord->handle($period, ! $period->isArchived());
    }

    public function render(): View
    {
        $periods = AuditPeriod::query()
            ->with('product')
            ->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderByDesc('starts_on')
            ->get();
        $active = $periods->whereNull('archived_at');

        return view('livewire.audit.index', [
            'periods' => $periods,
            'totals' => [
                'planned' => $active->sum(fn (AuditPeriod $period): float => (float) $period->hours_planned),
                'actual' => $active->whereNotNull('hours_actual')->sum(fn (AuditPeriod $period): float => (float) $period->hours_actual),
                'planned_with_actual' => $active->whereNotNull('hours_actual')->sum(fn (AuditPeriod $period): float => (float) $period->hours_planned),
                'support' => $active->whereNotNull('support_hours')->sum(fn (AuditPeriod $period): float => (float) $period->support_hours),
                'without_actual' => $active->whereNull('hours_actual')->count(),
            ],
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
