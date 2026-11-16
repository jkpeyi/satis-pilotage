<?php

namespace App\Livewire\Versions;

use App\Exports\VersionDiffExport;
use App\Models\Version;
use App\Queries\RowComparison;
use App\Queries\VersionDiff;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Functional differences between two versions: only in A, only in B, present in both but different.
 */
#[Title('Comparer deux versions')]
class Compare extends Component
{
    #[Url(except: '')]
    public string $a = '';

    #[Url(except: '')]
    public string $b = '';

    public ?string $comparedRowKey = null;

    public bool $showComparison = false;

    public function swap(): void
    {
        [$this->a, $this->b] = [$this->b, $this->a];
    }

    public function compareRow(string $rowKey): void
    {
        $this->comparedRowKey = $rowKey;
        $this->showComparison = true;
    }

    public function export(VersionDiff $versionDiff): ?BinaryFileResponse
    {
        [$a, $b] = $this->versions();

        if ($a === null || $b === null) {
            return null;
        }

        $fileName = 'differences-'.str($a->name)->slug().'-'.str($b->name)->slug().'.xlsx';

        return Excel::download(new VersionDiffExport($versionDiff->build($a, $b), $a, $b), $fileName);
    }

    public function render(VersionDiff $versionDiff, RowComparison $rowComparison): View
    {
        [$a, $b] = $this->versions();
        $ready = $a !== null && $b !== null && ! $a->is($b);

        return view('livewire.versions.compare', [
            'versionA' => $a,
            'versionB' => $b,
            'diff' => $ready ? $versionDiff->build($a, $b) : null,
            'sameVersion' => $a !== null && $b !== null && $a->is($b),
            'versionOptions' => Version::query()->whereNull('archived_at')->with('product')->get()
                ->sortBy(fn (Version $version): string => $version->product->name.' '.$version->name)
                ->mapWithKeys(fn (Version $version): array => [$version->id => $version->product->name.' · '.$version->name])
                ->all(),
            'comparison' => $ready && $this->showComparison && $this->comparedRowKey !== null
                ? $rowComparison->build($this->comparedRowKey, [$a->id, $b->id])
                : null,
        ]);
    }

    /**
     * @return array{0: ?Version, 1: ?Version}
     */
    private function versions(): array
    {
        return [
            $this->a !== '' ? Version::query()->with(['product', 'customer'])->find((int) $this->a) : null,
            $this->b !== '' ? Version::query()->with(['product', 'customer'])->find((int) $this->b) : null,
        ];
    }
}
