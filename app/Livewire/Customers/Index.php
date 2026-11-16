<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $showArchived = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'showArchived'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $customers = Customer::query()
            ->withCount(['versions' => fn (Builder $query) => $query->whereNull('archived_at')])
            ->when(! $this->showArchived, fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.customers.index', ['customers' => $customers]);
    }
}
