<?php

namespace App\Livewire\Workflows;

use App\Actions\Workflows\SaveWorkflow;
use App\Livewire\Forms\WorkflowForm;
use App\Models\Product;
use App\Models\Workflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Business workflows by product (SPEC-001 L2).
 */
#[Title('Workflows')]
class Index extends Component
{
    public WorkflowForm $form;

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

    public function save(SaveWorkflow $saveWorkflow): void
    {
        $workflow = $saveWorkflow->handle($this->form->data());

        session()->flash('status', 'Workflow créé. Ajoutez ses étapes.');
        $this->redirectRoute('workflows.show', $workflow, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.workflows.index', [
            'workflows' => Workflow::query()
                ->with('product')
                ->withCount(['steps' => fn ($query) => $query->whereNull('archived_at')])
                ->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))
                ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
                ->orderBy('name')
                ->get(),
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
