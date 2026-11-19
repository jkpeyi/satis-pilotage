<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\ArchiveCatalogItem;
use App\Actions\Catalog\ConvertFeatureToSubmodule;
use App\Actions\Catalog\ConvertSubmoduleToFeature;
use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\MergeCatalogItems;
use App\Actions\Catalog\MoveFeature;
use App\Actions\Catalog\MoveModule;
use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Implementations\DetachCatalogItems;
use App\Exceptions\DetachRequiresConfirmation;
use App\Exceptions\DuplicateCatalogItem;
use App\Livewire\Forms\CatalogItemForm;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Product;
use App\Models\Version;
use App\Queries\CatalogSearch;
use App\Queries\CatalogTree;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catalog tree (EF-201, EF-202) with the selected item's sheet (EF-211, EF-213, EF-204).
 */
#[Title('Catalogue')]
class Index extends Component
{
    /** @var list<int> */
    #[Session]
    public array $expanded = [];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'item', except: '')]
    public string $selected = '';

    #[Url(except: false)]
    public bool $showArchived = false;

    /**
     * Quick entry line (EF-202): where the next item will be created.
     *
     * @var array{parent_id: ?int, kind: 'module'|'feature'}|null
     */
    public ?array $draft = null;

    public string $draftName = '';

    public CatalogItemForm $form;

    /** @var list<string> */
    public array $attachVersionIds = [];

    public bool $attachWithDescendants = true;

    public ?string $detachWarning = null;

    public ?int $pendingDetachVersionId = null;

    public bool $showMerge = false;

    public string $mergeSearch = '';

    /** "module:12" or "feature:34": the item the selected one is merged into. */
    public string $mergeTarget = '';

    public function mount(): void
    {
        if ($this->selected !== '' && $this->selectedItem() !== null) {
            $this->select($this->selected);
        }
    }

    public function toggle(int $moduleId): void
    {
        $this->expanded = in_array($moduleId, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$moduleId]))
            : [...$this->expanded, $moduleId];
    }

    public function expandAll(): void
    {
        $this->expanded = array_keys(CatalogTree::load($this->showArchived)->modules);
    }

    public function collapseAll(): void
    {
        $this->expanded = [];
    }

    public function select(string $key): void
    {
        $item = $this->resolve($key);

        if ($item === null) {
            return;
        }

        $this->selected = $key;
        $this->form->setItem($item);
        $this->attachVersionIds = [];
        $this->detachWarning = null;
        $this->pendingDetachVersionId = null;

        $tree = CatalogTree::load(withArchived: true);
        $ancestors = $item instanceof Module ? $tree->ancestorModuleIds($item->id) : $tree->featureAncestorModuleIds($item->id);
        $this->expanded = array_values(array_unique([...$this->expanded, ...$ancestors]));
    }

    // Quick entry -----------------------------------------------------------------------------

    public function startDraft(?int $parentId, string $kind = 'module'): void
    {
        $this->draft = ['parent_id' => $parentId, 'kind' => $parentId === null ? 'module' : ($kind === 'feature' ? 'feature' : 'module')];
        $this->draftName = '';

        if ($parentId !== null && ! in_array($parentId, $this->expanded, true)) {
            $this->expanded[] = $parentId;
        }
    }

    public function saveDraft(CreateModule $createModule, CreateFeature $createFeature): void
    {
        if ($this->draft === null) {
            return;
        }

        $this->validate(['draftName' => ['required', 'string', 'max:255']], [], ['draftName' => 'nom']);

        try {
            if ($this->draft['kind'] === 'feature' && $this->draft['parent_id'] !== null) {
                $createFeature->handle(['name' => $this->draftName, 'module_id' => $this->draft['parent_id']]);
            } else {
                $createModule->handle(['name' => $this->draftName, 'parent_id' => $this->draft['parent_id']]);
            }
        } catch (DuplicateCatalogItem $exception) {
            $this->addError('draftName', $exception->getMessage());

            return;
        }

        $this->draftName = '';
    }

    /**
     * Tab: the next item goes under the last module just above the entry line.
     */
    public function indentDraft(): void
    {
        if ($this->draft === null) {
            return;
        }

        $tree = CatalogTree::load();
        $siblings = $this->draft['parent_id'] === null ? $tree->rootModuleIds() : $tree->childModuleIds($this->draft['parent_id']);
        $newParent = end($siblings);

        if ($newParent !== false) {
            $this->startDraft($newParent, $this->draft['kind']);
        }
    }

    /**
     * Shift+Tab: the next item goes one level up.
     */
    public function outdentDraft(): void
    {
        if ($this->draft === null || $this->draft['parent_id'] === null) {
            return;
        }

        $tree = CatalogTree::load();
        $this->startDraft($tree->modules[$this->draft['parent_id']]['parent_id'] ?? null, $this->draft['kind']);
    }

    public function setDraftKind(string $kind): void
    {
        if ($this->draft !== null && $this->draft['parent_id'] !== null) {
            $this->draft['kind'] = $kind === 'feature' ? 'feature' : 'module';
        }
    }

    public function cancelDraft(): void
    {
        $this->draft = null;
        $this->draftName = '';
        $this->resetValidation('draftName');
    }

    /**
     * The name typed in the quick entry already exists elsewhere: move that item here instead of
     * creating a duplicate.
     */
    public function moveExistingHere(string $key, MoveModule $moveModule, MoveFeature $moveFeature): void
    {
        $item = $this->resolve($key);
        $parentId = $this->draft['parent_id'] ?? null;

        if ($item === null || $this->draft === null) {
            return;
        }

        try {
            if ($item instanceof Module) {
                $parent = $parentId !== null ? Module::query()->findOrFail($parentId) : null;
                $siblings = Module::query()->where('parent_id', $parentId)->whereNull('archived_at')->count();
                $moveModule->handle($item, $parent, $siblings);
            } elseif ($parentId !== null) {
                $moveFeature->handle($item, Module::query()->findOrFail($parentId), Feature::query()->where('module_id', $parentId)->whereNull('archived_at')->count());
            }
        } catch (DomainException $exception) {
            $this->addError('draftName', $exception->getMessage());

            return;
        }

        $this->draftName = '';
        $this->resetValidation('draftName');
        $this->dispatch('notify', message: "« {$item->name} » a été déplacé ici.");
    }

    public function restoreExisting(string $key, ArchiveCatalogItem $archiveCatalogItem): void
    {
        $item = $this->resolve($key);
        if ($item === null) {
            return;
        }

        try {
            $archiveCatalogItem->handle($item, archive: false);
        } catch (DuplicateCatalogItem $exception) {
            $this->addError('draftName', $exception->getMessage());

            return;
        }

        $this->draftName = '';
        $this->select($key);
        $this->dispatch('notify', message: "« {$item->name} » a été restauré.");
    }

    // Tree changes ----------------------------------------------------------------------------

    public function move(string $group, int $id, ?int $parentId, int $position, MoveModule $moveModule, MoveFeature $moveFeature): void
    {
        try {
            if ($group === 'modules') {
                $moveModule->handle(Module::query()->findOrFail($id), $parentId !== null ? Module::query()->findOrFail($parentId) : null, $position);
            } elseif ($parentId !== null) {
                $moveFeature->handle(Feature::query()->findOrFail($id), Module::query()->findOrFail($parentId), $position);
            }
        } catch (DomainException $exception) {
            $this->dispatch('notify', message: $exception->getMessage());
        }
    }

    // Selected item ---------------------------------------------------------------------------

    public function save(SaveCatalogItem $saveCatalogItem): void
    {
        $item = $this->selectedItem();

        if ($item === null) {
            return;
        }

        try {
            $saveCatalogItem->handle($item, $this->form->data($item));
        } catch (DuplicateCatalogItem $exception) {
            $this->addError('form.name', $exception->getMessage());

            return;
        }

        $this->dispatch('notify', message: 'Élément enregistré.');
    }

    public function toggleArchive(ArchiveCatalogItem $archiveCatalogItem): void
    {
        $item = $this->selectedItem();

        if ($item === null) {
            return;
        }

        try {
            $archiveCatalogItem->handle($item, ! $item->isArchived());
        } catch (DuplicateCatalogItem $exception) {
            $this->addError('itemAction', $exception->getMessage());

            return;
        }

        $this->dispatch('notify', message: $item->refresh()->isArchived() ? 'Élément archivé avec ses sous-éléments.' : 'Élément restauré.');
    }

    public function convert(ConvertFeatureToSubmodule $toSubmodule, ConvertSubmoduleToFeature $toFeature): void
    {
        $item = $this->selectedItem();

        try {
            $converted = match (true) {
                $item instanceof Feature => $toSubmodule->handle($item),
                $item instanceof Module => $toFeature->handle($item),
                default => null,
            };
        } catch (DomainException $exception) {
            $this->addError('itemAction', $exception->getMessage());

            return;
        }

        if ($converted !== null) {
            $this->select(($converted instanceof Module ? 'module:' : 'feature:').$converted->id);
            $this->dispatch('notify', message: 'Élément converti. Ses rattachements, règles, problèmes et historiques sont conservés.');
        }
    }

    public function startMerge(): void
    {
        $this->mergeSearch = '';
        $this->mergeTarget = '';
        $this->resetValidation('mergeTarget');
        $this->showMerge = true;
    }

    public function updatedMergeSearch(): void
    {
        $this->mergeTarget = '';
    }

    /**
     * Merges the selected item into the chosen one, which is kept; the selected item is deleted
     * once everything it holds has moved.
     */
    public function merge(MergeCatalogItems $mergeCatalogItems): void
    {
        $item = $this->selectedItem();
        $target = $this->mergeTarget !== '' ? $this->resolve($this->mergeTarget) : null;

        if ($item === null || $target === null) {
            $this->addError('mergeTarget', 'Choisissez l’élément à conserver.');

            return;
        }

        try {
            $mergeCatalogItems->handle($item, $target);
        } catch (DomainException $exception) {
            $this->addError('mergeTarget', $exception->getMessage());

            return;
        }

        $this->showMerge = false;
        $this->select($this->mergeTarget);
        $this->dispatch('notify', message: "« {$item->name} » a été fusionné dans « {$target->name} ».");
    }

    public function attachToVersions(AttachCatalogItems $attachCatalogItems): void
    {
        $item = $this->selectedItem();
        $this->validate(['attachVersionIds' => ['required', 'array', 'min:1']], [], ['attachVersionIds' => 'versions']);

        if ($item === null) {
            return;
        }

        $versionIds = array_map('intval', $this->attachVersionIds);
        $count = $item instanceof Module
            ? $attachCatalogItems->handle($versionIds, [$item->id], [], $this->attachWithDescendants)
            : $attachCatalogItems->handle($versionIds, [], [$item->id]);

        $this->attachVersionIds = [];
        $this->dispatch('notify', message: "{$count} rattachement(s) créé(s) ou restauré(s).");
    }

    public function detachFromVersion(int $versionId, DetachCatalogItems $detachCatalogItems, bool $confirmed = false): void
    {
        $item = $this->selectedItem();

        if ($item === null) {
            return;
        }

        try {
            $item instanceof Module
                ? $detachCatalogItems->handle([$versionId], [$item->id], [], $confirmed)
                : $detachCatalogItems->handle([$versionId], [], [$item->id], $confirmed);
        } catch (DetachRequiresConfirmation $exception) {
            $this->detachWarning = $exception->getMessage();
            $this->pendingDetachVersionId = $versionId;

            return;
        }

        $this->detachWarning = null;
        $this->pendingDetachVersionId = null;
        $this->dispatch('notify', message: 'Élément détaché de la version.');
    }

    public function confirmDetach(DetachCatalogItems $detachCatalogItems): void
    {
        if ($this->pendingDetachVersionId !== null) {
            $this->detachFromVersion($this->pendingDetachVersionId, $detachCatalogItems, confirmed: true);
        }
    }

    public function render(): View
    {
        $tree = CatalogTree::load($this->showArchived);
        $item = $this->selectedItem();

        return view('livewire.catalog.index', [
            'tree' => $tree,
            'searchResults' => $this->search !== '' ? $this->searchResults($tree) : null,
            'item' => $item,
            'implementations' => $item?->implementations()->whereNull('archived_at')
                ->with(['version.product', 'version.customer'])->get()
                ->sortBy(fn (Implementation $implementation): string => $implementation->version->product->name.' '.$implementation->version->name)
                ->values(),
            'availableVersions' => $item !== null ? $this->availableVersions($item) : collect(),
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'path' => $item !== null ? $this->path($item) : [],
            'draftSuggestions' => $this->draft !== null ? app(CatalogSearch::class)->search($this->draftName, 6) : [],
            'mergeCandidates' => $this->showMerge && $item !== null ? $this->mergeCandidates($item) : [],
            'mergePreview' => $this->showMerge && $item !== null && $this->mergeTarget !== '' ? $this->mergePreview($item) : null,
        ]);
    }

    /**
     * Active items of the same kind matching the search, the selected item excluded.
     *
     * @return list<array{key: string, type: 'module'|'feature', id: int, name: string, path: string, exact: bool, archived: bool}>
     */
    private function mergeCandidates(Module|Feature $item): array
    {
        $type = $item instanceof Module ? 'module' : 'feature';
        $selfKey = $type.':'.$item->getKey();

        return array_values(array_filter(
            app(CatalogSearch::class)->search($this->mergeSearch !== '' ? $this->mergeSearch : $item->name, 20, withArchived: false),
            fn (array $candidate): bool => $candidate['type'] === $type && $candidate['key'] !== $selfKey,
        ));
    }

    /**
     * @return array{target: Module|Feature, moving: int, common: int, children: int}|null
     */
    private function mergePreview(Module|Feature $item): ?array
    {
        $target = $this->resolve($this->mergeTarget);
        if ($target === null) {
            return null;
        }

        $versionIds = $item->implementations()->pluck('version_id');
        $common = $target->implementations()->whereIn('version_id', $versionIds)->count();

        return [
            'target' => $target,
            'moving' => $versionIds->count() - $common,
            'common' => $common,
            'children' => $item instanceof Module ? $item->children()->count() + $item->features()->count() : 0,
        ];
    }

    private function selectedItem(): Module|Feature|null
    {
        return $this->selected !== '' ? $this->resolve($this->selected) : null;
    }

    private function resolve(string $key): Module|Feature|null
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);

        return match ($type) {
            'module' => Module::query()->find((int) $id),
            'feature' => Feature::query()->find((int) $id),
            default => null,
        };
    }

    /**
     * @return list<array{key: string, name: string, type: string, path: string}>
     */
    private function searchResults(CatalogTree $tree): array
    {
        $needle = mb_strtolower($this->search);
        $results = [];
        $pathOf = fn (array $moduleIds): string => implode(' › ', array_map(
            fn (int $id): string => $tree->modules[$id]['name'] ?? '?',
            array_reverse($moduleIds),
        ));

        foreach ($tree->modules as $module) {
            if (str_contains(mb_strtolower($module['name'].' '.$module['code']), $needle)) {
                $results[] = [
                    'key' => 'module:'.$module['id'],
                    'name' => $module['name'],
                    'type' => $module['parent_id'] === null ? 'Module' : 'Sous-module',
                    'path' => $pathOf($tree->ancestorModuleIds($module['id'])),
                ];
            }
        }

        foreach ($tree->features as $feature) {
            if (str_contains(mb_strtolower($feature['name'].' '.$feature['code']), $needle)) {
                $results[] = [
                    'key' => 'feature:'.$feature['id'],
                    'name' => $feature['name'],
                    'type' => 'Fonctionnalité',
                    'path' => $pathOf($tree->featureAncestorModuleIds($feature['id'])),
                ];
            }
        }

        return array_slice($results, 0, 100);
    }

    /**
     * @return Collection<int, Version>
     */
    private function availableVersions(Module|Feature $item): Collection
    {
        $attached = $item->implementations()->whereNull('archived_at')->pluck('version_id');

        return Version::query()->whereNull('archived_at')->whereNotIn('id', $attached)
            ->with('product')->get()
            ->sortBy(fn (Version $version): string => $version->product->name.' '.$version->name)
            ->values();
    }

    /**
     * Names of the ancestors of the item, root first.
     *
     * @return list<string>
     */
    private function path(Module|Feature $item): array
    {
        $full = CatalogTree::load(withArchived: true);
        $ids = $item instanceof Module ? $full->ancestorModuleIds($item->id) : $full->featureAncestorModuleIds($item->id);

        return array_map(fn (int $id): string => $full->modules[$id]['name'] ?? '?', array_reverse($ids));
    }
}
