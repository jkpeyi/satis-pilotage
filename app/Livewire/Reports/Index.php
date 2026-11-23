<?php

namespace App\Livewire\Reports;

use App\Actions\Reports\DeleteReportGeneration;
use App\Actions\Reports\GenerateReport;
use App\Livewire\Forms\ReportForm;
use App\Models\Customer;
use App\Models\DiagnosticCampaign;
use App\Models\Product;
use App\Models\RefactoringStrategy;
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

    #[Url(except: false)]
    public bool $showDeleted = false;

    public function mount(): void
    {
        $this->form->product_id = (string) Product::query()->orderBy('name')->value('id');
    }

    public function updatedFormProductId(): void
    {
        $this->form->customer_id = '';
        $this->form->campaign_id = '';
        $this->form->strategy_id = '';
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

    public function delete(int $generationId, DeleteReportGeneration $deleteReportGeneration): void
    {
        $generation = ReportGeneration::query()->findOrFail($generationId);
        $user = Auth::user();
        abort_if($user === null, 403);

        $deleteReportGeneration->handle($generation, $user);
        $this->dispatch('notify', message: "Export {$generation->documentId()} supprimé.");
    }

    public function updatedShowDeleted(): void
    {
        $this->resetPage();
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
            'campaigns' => DiagnosticCampaign::query()->active()
                ->whereHas('repository.version', fn ($query) => $query->where('product_id', $this->form->product_id))
                ->with('repository')->latest('id')->get()
                ->mapWithKeys(fn (DiagnosticCampaign $campaign): array => [$campaign->id => $campaign->label()]),
            'strategies' => RefactoringStrategy::query()->whereNull('archived_at')->where('product_id', $this->form->product_id)->latest('id')->get()
                ->mapWithKeys(fn (RefactoringStrategy $strategy): array => [$strategy->id => $strategy->displayCode().' · '.$strategy->title.' ('.mb_strtolower($strategy->approval_status->label()).')']),
            'generations' => ReportGeneration::query()
                ->with(['author', 'deleter'])
                ->when($this->filterCode !== '', fn ($query) => $query->where('code', $this->filterCode))
                ->when(! $this->showDeleted, fn ($query) => $query->whereNull('deleted_at'))
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
