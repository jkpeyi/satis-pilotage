<?php

namespace App\Livewire\Diagnostics;

use App\Actions\Diagnostics\SaveDiagnosticCampaign;
use App\Livewire\Forms\DiagnosticCampaignForm;
use App\Models\DiagnosticCampaign;
use App\Models\Product;
use App\Models\Repository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Diagnostic campaigns (SPEC-001 L4): a repository analyzed at an exact commit.
 */
#[Title('Diagnostics')]
class Index extends Component
{
    public DiagnosticCampaignForm $form;

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: false)]
    public bool $showArchived = false;

    public bool $showForm = false;

    public function create(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->form->analyzed_on = now()->toDateString();
        $this->showForm = true;
    }

    public function save(SaveDiagnosticCampaign $saveCampaign): void
    {
        $campaign = $saveCampaign->handle($this->form->data());

        session()->flash('status', "Campagne {$campaign->displayCode()} créée. Importez les résultats des outils.");
        $this->redirectRoute('diagnostics.show', $campaign, navigate: true);
    }

    /**
     * Active repositories, "Produit · Version — chemin".
     *
     * @return array<int, string>
     */
    public static function repositoryOptions(): array
    {
        return Repository::query()
            ->active()
            ->whereHas('version', fn ($query) => $query->active())
            ->with('version.product')
            ->get()
            ->mapWithKeys(fn (Repository $repository): array => [
                $repository->id => sprintf('%s · %s — %s', $repository->version->product->name, $repository->version->name, $repository->path),
            ])
            ->sort()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.diagnostics.index', [
            'campaigns' => DiagnosticCampaign::query()
                ->with('repository.version.product', 'repository.version.customer')
                ->withCount([
                    'toolRuns',
                    'findings as to_verify_count' => fn ($query) => $query->where('status', 'to_verify'),
                    'findings as retained_count' => fn ($query) => $query->where('status', 'retained'),
                    'findings as false_positive_count' => fn ($query) => $query->where('status', 'false_positive'),
                ])
                ->when($this->product !== '', fn ($query) => $query->whereHas('repository.version', fn ($query) => $query->where('product_id', $this->product)))
                ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
                ->latest('id')
                ->get(),
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'repositories' => self::repositoryOptions(),
        ]);
    }
}
