<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\ArchiveCatalogItem;
use App\Actions\Catalog\ConvertFeatureToSubmodule;
use App\Actions\Catalog\ConvertSubmoduleToFeature;
use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\MoveFeature;
use App\Actions\Catalog\MoveModule;
use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Implementations\DetachCatalogItems;
use App\Exceptions\DetachRequiresConfirmation;
use App\Livewire\Forms\CatalogItemForm;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Product;
use App\Models\Version;
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

        if ($this->draft['kind'] === 'feature' && $this->draft['parent_id'] !== null) {
            $createFeature->handle(['name' => $this->draftName, 'module_id' => $this->draft['parent_id']]);
        } else {
            $createModule->handle(['name' => $this->draftName, 'parent_id' => $this->draft['parent_id']]);
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

        $saveCatalogItem->handle($item, $this->form->data($item));
        $this->dispatch('notify', message: 'Élément enregistré.');
    }

    public function toggleArchive(ArchiveCatalogItem $archiveCatalogItem): void
    {
        $item = $this->selectedItem();

        if ($item === null) {
            return;
        }

        $archiveCatalogItem->handle($item, ! $item->isArchived());
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
            $this->addError('convert', $exception->getMessage());

            return;
        }

        if ($converted !== null) {
            $this->select(($converted instanceof Module ? 'module:' : 'feature:').$converted->id);
            $this->dispatch('notify', message: 'Élément converti. Ses rattachements, règles, problèmes et historiques sont conservés.');
        }
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
        ]);
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
