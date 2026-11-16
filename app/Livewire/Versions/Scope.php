<?php

namespace App\Livewire\Versions;

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Implementations\ChangeImplementationStatus;
use App\Actions\Implementations\CreateCatalogItemInVersion;
use App\Actions\Implementations\DetachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Exceptions\DetachRequiresConfirmation;
use App\Models\Implementation;
use App\Models\User;
use App\Models\Version;
use App\Queries\CatalogTree;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Périmètre fonctionnel" of a version: its implementations as a tree, with their status (EF-210),
 * bulk status changes (EF-206), attach / detach (EF-204), creation from the version (EF-203).
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

    public bool $showCreate = false;

    public string $newKind = 'feature';

    public string $newName = '';

    public string $newParentId = '';

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

    public function createItem(CreateCatalogItemInVersion $createCatalogItemInVersion): void
    {
        $this->validate([
            'newKind' => ['required', Rule::in(['module', 'feature'])],
            'newName' => ['required', 'string', 'max:255'],
            'newParentId' => [Rule::requiredIf($this->newKind === 'feature'), 'nullable', 'integer', Rule::exists('modules', 'id')],
        ], [], ['newKind' => 'type', 'newName' => 'nom', 'newParentId' => 'module parent']);

        /** @var 'module'|'feature' $kind */
        $kind = $this->newKind;
        $createCatalogItemInVersion->handle($this->version, $kind, $this->newName, $this->newParentId !== '' ? (int) $this->newParentId : null);

        $this->reset('newName');
        $this->dispatch('notify', message: 'Élément créé dans le catalogue et rattaché à cette version.');
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

        return view('livewire.versions.scope', [
            'tree' => $tree,
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
            'attachedModuleOptions' => $this->moduleOptions($tree, $byModule->keys()->all()),
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
     * Attached modules, indented by depth, for the "parent" select of the creation form.
     *
     * @param  list<int>  $attachedModuleIds
     * @return array<int, string>
     */
    private function moduleOptions(CatalogTree $tree, array $attachedModuleIds): array
    {
        $options = [];
        $walk = function (array $moduleIds, int $depth) use (&$walk, &$options, $tree, $attachedModuleIds): void {
            foreach ($moduleIds as $moduleId) {
                if (in_array($moduleId, $attachedModuleIds, true)) {
                    $options[$moduleId] = str_repeat('— ', $depth).$tree->modules[$moduleId]['name'];
                    $walk($tree->childModuleIds($moduleId), $depth + 1);
                }
            }
        };
        $walk($tree->rootModuleIds(), 0);

        return $options;
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
