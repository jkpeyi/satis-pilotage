<?php

namespace App\Livewire\Issues;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Livewire\Concerns\EnumOptions;
use App\Models\Issue;
use App\Models\Product;
use App\Models\Version;
use App\Support\Effort;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Global list of issues with filters (EF-209).
 */
#[Title('Problèmes')]
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
    public string $type = '';

    #[Url(except: '')]
    public string $severity = '';

    /** "active" hides fixed and dropped issues. */
    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: false)]
    public bool $showArchived = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Issue::query()
            ->when(! $this->showArchived, fn (Builder $query) => $query->whereNull('issues.archived_at'))
            ->when($this->product !== '', fn (Builder $query) => $query->whereHas('implementations.version', fn (Builder $version) => $version->where('product_id', $this->product)))
            ->when($this->version !== '', fn (Builder $query) => $query->whereHas('implementations', fn (Builder $implementation) => $implementation->where('version_id', $this->version)))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->severity !== '', fn (Builder $query) => $query->where('severity', $this->severity))
            ->when($this->status === 'active', fn (Builder $query) => $query->whereIn('status', IssueStatus::openValues()))
            ->when(! in_array($this->status, ['', 'active'], true), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query));

        $totals = (clone $query)->toBase()->selectRaw('SUM(effort_min_days) as low, SUM(COALESCE(effort_max_days, effort_min_days)) as high')->first();
        $totalEffort = Effort::format($totals->low ?? null, $totals->high ?? null) ?? '0 j/h';

        $issues = $query
            ->withCount('implementations')
            ->with(['implementations.version'])
            ->orderByRaw("FIELD(severity, 'blocking', 'major', 'minor')")
            ->orderByDesc('id')
            ->paginate(30);

        return view('livewire.issues.index', [
            'issues' => $issues,
            'totalEffort' => $totalEffort,
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'versions' => Version::query()->whereNull('archived_at')
                ->when($this->product !== '', fn (Builder $query) => $query->where('product_id', $this->product))
                ->orderBy('name')->pluck('name', 'id'),
            'types' => $this->enumOptions(IssueType::class),
            'severities' => $this->enumOptions(IssueSeverity::class),
            'statuses' => ['active' => 'Non résolus', ...$this->enumOptions(IssueStatus::class)],
        ]);
    }

    /**
     * @param  Builder<Issue>  $query
     */
    private function applySearch(Builder $query): void
    {
        $code = preg_match('/^\s*PB-?0*(\d+)\s*$/i', $this->search, $matches) === 1 ? (int) $matches[1] : null;

        $query->where(fn (Builder $query) => $query
            ->where('title', 'like', "%{$this->search}%")
            ->orWhere('description', 'like', "%{$this->search}%")
            ->when($code !== null, fn (Builder $query) => $query->orWhere('issues.id', $code)));
    }
}
