<?php

namespace App\Livewire\Versions;

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Implementations\ChangeImplementationStatus;
use App\Actions\Implementations\CreateCatalogItemInVersion;
use App\Actions\Implementations\DetachCatalogItems;
use App\Actions\Implementations\InitializeVersionFrom;
use App\Actions\Implementations\MoveItemInVersionScope;
use App\Actions\Implementations\ReuseCatalogItemInVersion;
use App\Enums\ImplementationStatus;
use App\Exceptions\DetachRequiresConfirmation;
use App\Exceptions\DuplicateCatalogItem;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use App\Queries\CatalogSearch;
use App\Queries\CatalogTree;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Périmètre fonctionnel" of a version: its implementations as a tree, with their status (EF-210),
 * bulk status changes (EF-206), attach / detach (EF-204), inline creation from the version with
 * catalog suggestions (EF-203, RG-15) and drag and drop in the shared catalog (RG-02).
 */
class Scope extends Component
{
    public Version $version;

    #[Url(as: 'etat', except: '')]
    public string $statusFilter = '';

    /** @var list<string> */
    public array $selectedIds = [];

    public string $bulkStatus = '';

    public string $bulkNote = '';

    public ?string $detachWarning = null;

    public bool $showAttach = false;

    /** @var list<string> */
    public array $attachModuleIds = [];

    /** @var list<string> */
    public array $attachFeatureIds = [];

    public bool $attachWithDescendants = true;

    /** @var array{parent_id: ?int, kind: 'module'|'feature'}|null Inline entry line, like in the catalog. */
    public ?array $draft = null;

    public string $draftName = '';

    public bool $showInitialize = false;

    public string $initSourceId = '';

    public bool $initStatuses = true;

    public bool $initDeviations = true;

    public bool $initRules = false;

    public ?int $historyImplementationId = null;

    public bool $showHistory = false;

    public function setStatus(int $implementationId, string $status, ChangeImplementationStatus $changeStatus): void
    {
        $this->ensureBelongsToVersion([$implementationId]);
        $changeStatus->handle([$implementationId], ImplementationStatus::from($status), $this->user());
    }

    public function selectAll(): void
    {
        $this->selectedIds = $this->implementations()
            ->when($this->statusFilter !== '', fn (Collection $items) => $items->where('status', ImplementationStatus::from($this->statusFilter)))
            ->map(fn (Implementation $implementation): string => (string) $implementation->id)
            ->values()->all();
    }

    public function applyBulkStatus(ChangeImplementationStatus $changeStatus): void
    {
        $this->validate([
            'selectedIds' => ['required', 'array', 'min:1'],
            'bulkStatus' => ['required', Rule::enum(ImplementationStatus::class)],
            'bulkNote' => ['nullable', 'string', 'max:1000'],
        ], [], ['selectedIds' => 'sélection', 'bulkStatus' => 'état', 'bulkNote' => 'commentaire']);

        $ids = array_map('intval', $this->selectedIds);
        $this->ensureBelongsToVersion($ids);

        $count = $changeStatus->handle($ids, ImplementationStatus::from($this->bulkStatus), $this->user(), $this->bulkNote !== '' ? $this->bulkNote : null);

        $this->reset('selectedIds', 'bulkStatus', 'bulkNote');
        $this->dispatch('notify', message: "État mis à jour pour {$count} élément(s).");
    }

    public function detachSelected(DetachCatalogItems $detachCatalogItems, bool $confirmed = false): void
    {
        $this->validate(['selectedIds' => ['required', 'array', 'min:1']], [], ['selectedIds' => 'sélection']);

        $implementations = $this->version->implementations()->whereKey(array_map('intval', $this->selectedIds))->get(['id', 'module_id', 'feature_id']);

        try {
            $count = $detachCatalogItems->handle(
                [$this->version->id],
                $implementations->pluck('module_id')->filter()->values()->all(),
                $implementations->pluck('feature_id')->filter()->values()->all(),
                $confirmed,
            );
        } catch (DetachRequiresConfirmation $exception) {
            $this->detachWarning = $exception->getMessage();

            return;
        }

        $this->reset('selectedIds', 'detachWarning');
        $this->dispatch('notify', message: "{$count} élément(s) détaché(s), sous-éléments compris.");
    }

    public function confirmDetach(DetachCatalogItems $detachCatalogItems): void
    {
        $this->detachSelected($detachCatalogItems, confirmed: true);
    }

    public function openAttach(): void
    {
        $this->reset('attachModuleIds', 'attachFeatureIds');
        $this->attachWithDescendants = true;
        $this->showAttach = true;
    }

    public function attach(AttachCatalogItems $attachCatalogItems): void
    {
        $count = $attachCatalogItems->handle(
            [$this->version->id],
            array_map('intval', $this->attachModuleIds),
            array_map('intval', $this->attachFeatureIds),
            $this->attachWithDescendants,
        );

        $this->showAttach = false;
        $this->dispatch('notify', message: "{$count} élément(s) rattaché(s).");
    }

    // Inline entry (same keys as the catalog) ------------------------------------------------

    public function startDraft(?int $parentId, string $kind = 'module'): void
    {
        $this->draft = ['parent_id' => $parentId, 'kind' => $parentId !== null && $kind === 'feature' ? 'feature' : 'module'];
        $this->draftName = '';
        $this->resetValidation('draftName');

        if ($parentId !== null) {
            $this->dispatch('scope-open-module', id: $parentId);
        }
    }

    /**
     * Enter: creates the item in the shared catalog, attaches it to this version (EF-203) and
     * keeps the line open for the next one.
     */
    public function saveDraft(CreateCatalogItemInVersion $createCatalogItemInVersion): void
    {
        if ($this->draft === null) {
            return;
        }

        $this->validate(['draftName' => ['required', 'string', 'max:255']], [], ['draftName' => 'nom']);

        try {
            $createCatalogItemInVersion->handle($this->version, $this->draft['kind'], $this->draftName, $this->draft['parent_id']);
        } catch (DuplicateCatalogItem $exception) {
            $this->addError('draftName', $exception->getMessage());

            return;
        }

        $this->draftName = '';
    }

    /**
     * Tab: the next item goes under the last module of this version just above the entry line.
     */
    public function indentDraft(): void
    {
        if ($this->draft === null) {
            return;
        }

        $tree = CatalogTree::load();
        $attached = $this->implementations()->pluck('module_id')->filter()->all();
        $siblings = $this->draft['parent_id'] === null ? $tree->rootModuleIds() : $tree->childModuleIds($this->draft['parent_id']);
        $visible = array_values(array_filter($siblings, fn (int $id): bool => in_array($id, $attached, true)));

        if ($visible !== []) {
            $this->startDraft(end($visible), $this->draft['kind']);
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

        $this->startDraft(CatalogTree::load()->modules[$this->draft['parent_id']]['parent_id'] ?? null, $this->draft['kind']);
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
     * The typed name already exists in the catalog (RG-15): reuse it rather than create a duplicate.
     * $mode comes from the page: "attach", "move" or "link".
     */
    public function reuseExisting(string $key, string $mode, ReuseCatalogItemInVersion $reuseItem): void
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '0');
        $item = $type === 'module' ? Module::query()->find((int) $id) : Feature::query()->find((int) $id);
        $mode = match ($mode) {
            'move' => 'move',
            'link' => 'link',
            default => 'attach',
        };

        if ($item === null) {
            return;
        }

        try {
            $reuseItem->handle($this->version, $item, $this->draft['parent_id'] ?? null, $mode);
        } catch (DomainException $exception) {
            $this->addError('draftName', $exception->getMessage());

            return;
        }

        $this->draftName = '';
        $this->resetValidation('draftName');
        $this->dispatch('notify', message: match ($mode) {
            'move' => "« {$item->name} » a été déplacé ici dans le catalogue et rattaché.",
            'link' => "« {$item->name} » apparaît aussi ici et est rattaché.",
            default => "« {$item->name} » est rattaché à cette version.",
        });
    }

    /**
     * Drag and drop in the tree: moves the item in the shared catalog (RG-02).
     */
    public function move(string $group, int $id, ?int $parentId, int $position, MoveItemInVersionScope $moveItem): void
    {
        try {
            $moveItem->handle($this->version, $group === 'features' ? 'feature' : 'module', $id, $parentId, $position);
        } catch (DomainException $exception) {
            $this->dispatch('notify', message: $exception->getMessage());

            return;
        }

        $this->dispatch('notify', message: 'Déplacé dans le catalogue : toutes les versions voient la nouvelle organisation.');
    }

    public function initialize(InitializeVersionFrom $initializeVersionFrom): void
    {
        $this->validate(
            ['initSourceId' => ['required', 'integer', Rule::exists('versions', 'id'), Rule::notIn([$this->version->id])]],
            [],
            ['initSourceId' => 'version source'],
        );

        $count = $initializeVersionFrom->handle(Version::query()->findOrFail((int) $this->initSourceId), $this->version, [
            'statuses' => $this->initStatuses,
            'deviations' => $this->initDeviations,
            'rules' => $this->initRules,
        ]);

        $this->showInitialize = false;
        $this->dispatch('notify', message: "{$count} élément(s) repris de la version source.");
    }

    public function showHistoryOf(int $implementationId): void
    {
        $this->ensureBelongsToVersion([$implementationId]);
        $this->historyImplementationId = $implementationId;
        $this->showHistory = true;
    }

    public function render(): View
    {
        $this->version->load('product', 'customer');
        $tree = CatalogTree::load();
        $implementations = $this->implementations();

        $byModule = $implementations->whereNotNull('module_id')->keyBy('module_id');
        $byFeature = $implementations->whereNotNull('feature_id')->keyBy('feature_id');

        $suggestions = $this->draft !== null && trim($this->draftName) !== '' ? app(CatalogSearch::class)->search($this->draftName, 6) : [];

        return view('livewire.versions.scope', [
            'tree' => $tree,
            'draftSuggestions' => array_map(fn (array $suggestion): array => [
                ...$suggestion,
                'attached' => $suggestion['type'] === 'module' ? $byModule->has($suggestion['id']) : $byFeature->has($suggestion['id']),
            ], $suggestions),
            'byModule' => $byModule,
            'byFeature' => $byFeature,
            'counts' => collect(ImplementationStatus::cases())->mapWithKeys(fn (ImplementationStatus $status): array => [
                $status->value => $implementations->where('status', $status)->count(),
            ]),
            'total' => $implementations->count(),
            'filtered' => $this->statusFilter !== ''
                ? $implementations->where('status', ImplementationStatus::from($this->statusFilter))->values()
                : null,
            'statuses' => collect(ImplementationStatus::cases())->mapWithKeys(fn (ImplementationStatus $status): array => [$status->value => $status->label()]),
            'sourceVersions' => Version::query()->whereNull('archived_at')->whereKeyNot($this->version->id)
                ->with('product')->get()
                ->sortBy(fn (Version $version): string => ($version->product_id === $this->version->product_id ? '0' : '1').$version->product->name.' '.$version->name)
                ->mapWithKeys(fn (Version $version): array => [$version->id => $version->product->name.' · '.$version->name])
                ->all(),
            'history' => $this->showHistory && $this->historyImplementationId !== null
                ? Implementation::query()->with(['statusChanges.user', 'module', 'feature'])->find($this->historyImplementationId)
                : null,
        ])->title('Périmètre · '.$this->version->name);
    }

    /**
     * Active implementations of the version whose catalog item is active.
     *
     * @return Collection<int, Implementation>
     */
    private function implementations(): Collection
    {
        return $this->version->implementations()
            ->whereNull('implementations.archived_at')
            ->where(fn ($query) => $query
                ->whereHas('module', fn ($module) => $module->whereNull('archived_at'))
                ->orWhereHas('feature', fn ($feature) => $feature->whereNull('archived_at')))
            ->with(['module', 'feature'])
            ->withCount([
                'businessRules' => fn ($query) => $query->whereNull('archived_at'),
                'issues' => fn ($query) => $query->whereNull('issues.archived_at'),
            ])
            ->get();
    }

    /**
     * @param  list<int>  $implementationIds
     */
    private function ensureBelongsToVersion(array $implementationIds): void
    {
        $count = $this->version->implementations()->whereKey($implementationIds)->count();

        abort_unless($count === count(array_unique($implementationIds)), 404);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
