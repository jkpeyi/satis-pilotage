<?php

namespace App\Livewire\Strategies;

use App\Actions\Strategy\SaveRefactoringStrategy;
use App\Livewire\Forms\StrategyForm;
use App\Models\Product;
use App\Models\RefactoringStrategy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Refactoring strategies (L7): decision documents written and approved by people.
 */
#[Title('Stratégies')]
class Index extends Component
{
    public StrategyForm $form;

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
        $this->showForm = true;
    }

    public function save(SaveRefactoringStrategy $saveStrategy): void
    {
        $data = $this->form->data();
        $strategy = $saveStrategy->handle($data['data'], null, $data['issue_ids']);

        session()->flash('status', "Stratégie {$strategy->displayCode()} créée. Décrivez son contexte et ses scénarios.");
        $this->redirectRoute('strategies.show', $strategy, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.strategies.index', [
            'strategies' => RefactoringStrategy::query()
                ->with('product', 'approver')
                ->withCount([
                    'options' => fn ($query) => $query->whereNull('archived_at'),
                    'decisions as pending_decisions_count' => fn ($query) => $query->whereNull('archived_at')->where('status', 'proposed'),
                ])
                ->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))
                ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
                ->latest('id')
                ->get(),
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
