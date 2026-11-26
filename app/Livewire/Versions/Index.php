<?php

namespace App\Livewire\Versions;

use App\Enums\VersionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Version;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Versions')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $product = '';

    /** Customer id, or "none" for baseline versions. */
    #[Url(except: '')]
    public string $customer = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $showArchived = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $versions = Version::query()
            ->with(['product', 'customer', 'technicalOwner', 'deputy', 'repositories', 'components'])
            ->when(! $this->showArchived, fn (Builder $query) => $query->whereNull('versions.archived_at'))
            ->when($this->product !== '', fn (Builder $query) => $query->where('product_id', $this->product))
            ->when($this->customer === 'none', fn (Builder $query) => $query->whereNull('customer_id'))
            ->when($this->customer !== '' && $this->customer !== 'none', fn (Builder $query) => $query->where('customer_id', $this->customer))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'versions.product_id'))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.versions.index', [
            'versions' => $versions,
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'customers' => Customer::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'statuses' => collect(VersionStatus::cases())->mapWithKeys(fn (VersionStatus $status) => [$status->value => $status->label()]),
        ]);
    }
}
