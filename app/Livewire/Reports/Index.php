<?php

namespace App\Livewire\Reports;

use App\Actions\Reports\GenerateReport;
use App\Livewire\Forms\ReportForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ReportGeneration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Deliverables of the DTI plan (SPEC-001): generation from the central data and register of exports.
 */
#[Title('Rapports')]
class Index extends Component
{
    use WithPagination;

    public ReportForm $form;

    #[Url]
    public string $filterCode = '';

    public function mount(): void
    {
        $this->form->product_id = (string) Product::query()->orderBy('name')->value('id');
    }

    public function updatedFormProductId(): void
    {
        $this->form->customer_id = '';
    }

    public function updatedFilterCode(): void
    {
        $this->resetPage();
    }

    public function generate(GenerateReport $generateReport): void
    {
        $data = $this->form->data();
        $user = Auth::user();
        abort_if($user === null, 403);

        $generation = $generateReport->handle($data['code'], $data['scope'], $user);

        $this->dispatch('notify', message: sprintf(
            'Rapport %s généré (complétude %s %%).',
            $generation->documentId(),
            number_format((float) $generation->completeness, 0, ',', ' '),
        ));
    }

    public function render(): View
    {
        /** @var array<string, array{title: string, builder: ?string}> $deliverables */
        $deliverables = config('reports.deliverables');

        return view('livewire.reports.index', [
            'deliverables' => $deliverables,
            'exportable' => ReportForm::exportableCodes(),
            'products' => Product::query()->orderBy('name')->pluck('name', 'id'),
            'customers' => Customer::query()
                ->whereHas('versions', fn ($query) => $query->active()->where('product_id', $this->form->product_id))
                ->orderBy('name')
                ->pluck('name', 'id'),
            'generations' => ReportGeneration::query()
                ->with('author')
                ->when($this->filterCode !== '', fn ($query) => $query->where('code', $this->filterCode))
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
