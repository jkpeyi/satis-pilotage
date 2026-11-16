<?php

namespace App\Livewire\BusinessRules;

use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Livewire\Concerns\EnumOptions;
use App\Models\BusinessRule;
use App\Models\Product;
use App\Models\Version;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Global list of business rules (EF-212).
 */
#[Title('Règles métier')]
class Index extends Component
{
    use EnumOptions, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: '')]
    public string $version = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $rules = BusinessRule::query()
            ->whereNull('business_rules.archived_at')
            ->with(['implementation.version.product', 'implementation.module', 'implementation.feature'])
            ->when($this->product !== '', fn (Builder $query) => $query->whereHas('implementation.version', fn (Builder $version) => $version->where('product_id', $this->product)))
            ->when($this->version !== '', fn (Builder $query) => $query->whereHas('implementation', fn (Builder $implementation) => $implementation->where('version_id', $this->version)))
            ->when($this->source !== '', fn (Builder $query) => $query->where('source', $this->source))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->orderByDesc('id')
            ->paginate(30);

        return view('livewire.business-rules.index', [
            'rules' => $rules,
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'versions' => Version::query()->whereNull('archived_at')
                ->when($this->product !== '', fn (Builder $query) => $query->where('product_id', $this->product))
                ->orderBy('name')->pluck('name', 'id'),
            'sources' => $this->enumOptions(RuleSource::class),
            'statuses' => $this->enumOptions(RuleStatus::class),
        ]);
    }

    /**
     * @param  Builder<BusinessRule>  $query
     */
    private function applySearch(Builder $query): void
    {
        $code = preg_match('/^\s*RM-?0*(\d+)\s*$/i', $this->search, $matches) === 1 ? (int) $matches[1] : null;

        $query->where(fn (Builder $query) => $query
            ->where('title', 'like', "%{$this->search}%")
            ->orWhere('description', 'like', "%{$this->search}%")
            ->when($code !== null, fn (Builder $query) => $query->orWhere('business_rules.id', $code)));
    }
}
