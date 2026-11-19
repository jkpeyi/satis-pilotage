<?php

namespace App\Livewire\ComparisonMatrix;

use App\Enums\ImplementationStatus;
use App\Exports\ComparisonMatrixExport;
use App\Models\Implementation;
use App\Models\Product;
use App\Models\Version;
use App\Queries\CatalogTree;
use App\Queries\ComparisonMatrixData;
use App\Queries\RowComparison;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Comparison matrix (EF-301 to EF-307).
 *
 * Livewire holds the selection (product, versions, branch) and serves data, the cell panel,
 * the row comparison and the export. The matrix itself is rendered and filtered in the browser
 * (resources/js/comparison-matrix.js) so that pivot and filters change without round trips.
 */
#[Title('Cartographie des écarts')]
class Index extends Component
{
    public const MAX_VERSIONS = 30;

    #[Url(except: '')]
    public string $product = '';

    /** @var array<int, string> keys are not guaranteed: the value comes from the URL and checkboxes */
    #[Url(as: 'versions')]
    public array $versionIds = [];

    #[Url(as: 'branch', except: '')]
    public string $rootModuleId = '';

    #[Url(except: '')]
    public string $pivot = '';

    public ?int $panelImplementationId = null;

    public ?string $comparedRowKey = null;

    public bool $showComparison = false;

    public function mount(): void
    {
        if ($this->product !== '' && $this->versionIds === []) {
            $this->selectProductVersions();
        }
    }

    public function updatedProduct(): void
    {
        $this->rootModuleId = '';
        $this->selectProductVersions();
        $this->dispatch('matrix-reload');
    }

    public function updatedVersionIds(): void
    {
        $this->versionIds = array_slice(array_values($this->versionIds), 0, self::MAX_VERSIONS);

        if (! in_array($this->pivot, $this->versionIds, true)) {
            $this->pivot = $this->versionIds[0] ?? '';
        }

        $this->dispatch('matrix-reload');
    }

    public function updatedRootModuleId(): void
    {
        $this->dispatch('matrix-reload');
    }

    /**
     * Matrix data for the browser (EF-302). Called from Alpine with `await $wire.payload()`.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = app(ComparisonMatrixData::class)->build($this->selectedVersionIds(), $this->rootModuleId !== '' ? (int) $this->rootModuleId : null);

        return [
            'versions' => $data['versions'],
            'rows' => $data['rows'],
            'cells' => $this->compactCells($data['cells']),
            'pivot' => $this->pivot !== '' ? (int) $this->pivot : null,
            // Status index => value and label, so the renderer never duplicates the enum.
            'statuses' => array_map(fn (ImplementationStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ImplementationStatus::cases()),
        ];
    }

    /**
     * Cells as [implementation id, status index, has deviation, rules, issues], half the size of
     * the associative form. The status index follows ImplementationStatus::cases(), like the JS renderer.
     *
     * @param  array<string, array<int, array{id: int, s: string, d: bool, r: int, i: int}>>  $cells
     * @return array<string, array<int, array{0: int, 1: int, 2: int, 3: int, 4: int}>>
     */
    private function compactCells(array $cells): array
    {
        $statusIndex = array_flip(array_map(fn (ImplementationStatus $status): string => $status->value, ImplementationStatus::cases()));

        return array_map(fn (array $byVersion): array => array_map(
            fn (array $cell): array => [$cell['id'], $statusIndex[$cell['s']] ?? 0, (int) $cell['d'], $cell['r'], $cell['i']],
            $byVersion,
        ), $cells);
    }

    public function setPivot(int $versionId): void
    {
        $this->pivot = (string) $versionId;
    }

    public function openCell(int $implementationId): void
    {
        $this->panelImplementationId = $implementationId;
    }

    public function closePanel(): void
    {
        $this->panelImplementationId = null;
    }

    public function compareRow(string $rowKey): void
    {
        $this->comparedRowKey = $rowKey;
        $this->showComparison = true;
    }

    /**
     * Excel export of the rows visible on screen (EF-307).
     *
     * @param  list<string>  $rowKeys
     */
    public function export(array $rowKeys, ?int $pivotId): BinaryFileResponse
    {
        $data = app(ComparisonMatrixData::class)->build($this->selectedVersionIds(), $this->rootModuleId !== '' ? (int) $this->rootModuleId : null);
        $product = Product::query()->find((int) $this->product);
        $fileName = 'cartographie-'.str($product->name ?? 'satis')->slug().'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ComparisonMatrixExport($data, array_values(array_filter($rowKeys, 'is_string')), $pivotId), $fileName);
    }

    public function render(): View
    {
        return view('livewire.comparison-matrix.index', [
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'versions' => $this->product !== ''
                ? Version::query()->where('product_id', $this->product)->whereNull('archived_at')->with('customer')->orderBy('name')->get()
                : collect(),
            'branches' => $this->branchOptions(),
            'panel' => $this->panelImplementationId !== null
                ? Implementation::query()->with([
                    'version', 'module', 'feature',
                    'businessRules' => fn ($query) => $query->whereNull('archived_at'),
                    'issues' => fn ($query) => $query->whereNull('issues.archived_at'),
                ])->find($this->panelImplementationId)
                : null,
            'comparison' => $this->showComparison && $this->comparedRowKey !== null
                ? app(RowComparison::class)->build($this->comparedRowKey, $this->selectedVersionIds())
                : null,
        ]);
    }

    private function selectProductVersions(): void
    {
        $product = Product::query()->find((int) $this->product);

        if ($product === null) {
            $this->versionIds = [];
            $this->pivot = '';

            return;
        }

        $ids = Version::query()->where('product_id', $product->id)->whereNull('archived_at')
            ->orderByRaw('id = ? desc', [(int) $product->reference_version_id])
            ->orderBy('name')
            ->limit(self::MAX_VERSIONS)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();

        $this->versionIds = $ids;
        $this->pivot = $product->reference_version_id !== null && in_array((string) $product->reference_version_id, $ids, true)
            ? (string) $product->reference_version_id
            : ($ids[0] ?? '');
    }

    /**
     * @return list<int>
     */
    private function selectedVersionIds(): array
    {
        $ids = array_map('intval', $this->versionIds);

        return Version::query()->whereIn('id', $ids)->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))
            ->pluck('id')
            ->sortBy(fn (int $id): int|false => array_search($id, $ids, true))
            ->values()
            ->all();
    }

    /**
     * Top-level modules and sub-modules, indented, to restrict the matrix to a branch (EF-301).
     *
     * @return array<int, string>
     */
    private function branchOptions(): array
    {
        $tree = CatalogTree::load();
        $options = [];
        $walk = function (array $ids, int $depth) use (&$walk, &$options, $tree): void {
            foreach ($ids as $id) {
                $options[$id] = str_repeat('— ', $depth).$tree->modules[$id]['name'];
                if ($depth < 2) {
                    $walk($tree->childModuleIds($id), $depth + 1);
                }
            }
        };
        $walk($tree->rootModuleIds(), 0);

        return $options;
    }
}
