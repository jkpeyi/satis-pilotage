<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\MergeCatalogItems;
use App\Models\Feature;
use App\Models\Module;
use App\Queries\CatalogDuplicates;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Catalog duplicates to review: same names, a module and a feature sharing a name, close names.
 */
#[Title('Doublons du catalogue')]
class Duplicates extends Component
{
    /**
     * Merges the first item into the second, which is kept.
     */
    public function merge(string $duplicateKey, string $keptKey, MergeCatalogItems $mergeCatalogItems): void
    {
        $duplicate = $this->resolve($duplicateKey);
        $kept = $this->resolve($keptKey);

        if ($duplicate === null || $kept === null) {
            $this->dispatch('notify', message: 'Élément introuvable : la liste a peut-être changé.');

            return;
        }

        try {
            $mergeCatalogItems->handle($duplicate, $kept);
        } catch (DomainException $exception) {
            $this->dispatch('notify', message: $exception->getMessage());

            return;
        }

        $this->dispatch('notify', message: "« {$duplicate->name} » a été fusionné dans « {$kept->name} ».");
    }

    public function render(CatalogDuplicates $duplicates): View
    {
        return view('livewire.catalog.duplicates', [
            'groups' => $duplicates->mergeableGroups(),
            'crossKind' => $duplicates->crossKindGroups(),
            'similar' => $duplicates->similarPairs(),
        ]);
    }

    private function resolve(string $key): Module|Feature|null
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '0');

        return match ($type) {
            'module' => Module::query()->active()->find((int) $id),
            'feature' => Feature::query()->active()->find((int) $id),
            default => null,
        };
    }
}
