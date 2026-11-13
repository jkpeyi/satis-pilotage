<?php

namespace App\Queries;

use App\Models\Feature;
use App\Models\Module;

/**
 * In-memory snapshot of the catalog hierarchy (ids, names, parents, positions).
 *
 * Loads two light queries and answers ancestor / descendant questions without recursion in SQL.
 * Sub-modules are listed before features under a module, each list ordered by position.
 */
final class CatalogTree
{
    /** @var array<int, array{id: int, parent_id: ?int, code: ?string, name: string, position: int, archived: bool}> */
    public array $modules = [];

    /** @var array<int, array{id: int, module_id: int, code: ?string, name: string, position: int, archived: bool}> */
    public array $features = [];

    /** @var array<int, list<int>> parent module id (0 for roots) => child module ids */
    private array $childModules = [];

    /** @var array<int, list<int>> module id => feature ids */
    private array $moduleFeatures = [];

    public static function load(bool $withArchived = false): self
    {
        $tree = new self;

        Module::query()
            ->when(! $withArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('position')->orderBy('id')
            ->toBase()
            ->get(['id', 'parent_id', 'code', 'name', 'position', 'archived_at'])
            ->each(function (object $row) use ($tree): void {
                $tree->modules[(int) $row->id] = [
                    'id' => (int) $row->id,
                    'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
                    'code' => $row->code,
                    'name' => $row->name,
                    'position' => (int) $row->position,
                    'archived' => $row->archived_at !== null,
                ];
                $tree->childModules[(int) ($row->parent_id ?? 0)][] = (int) $row->id;
            });

        Feature::query()
            ->when(! $withArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('position')->orderBy('id')
            ->toBase()
            ->get(['id', 'module_id', 'code', 'name', 'position', 'archived_at'])
            ->each(function (object $row) use ($tree): void {
                $tree->features[(int) $row->id] = [
                    'id' => (int) $row->id,
                    'module_id' => (int) $row->module_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'position' => (int) $row->position,
                    'archived' => $row->archived_at !== null,
                ];
                $tree->moduleFeatures[(int) $row->module_id][] = (int) $row->id;
            });

        return $tree;
    }

    /**
     * @return list<int>
     */
    public function rootModuleIds(): array
    {
        return $this->childModules[0] ?? [];
    }

    /**
     * @return list<int>
     */
    public function childModuleIds(int $moduleId): array
    {
        return $this->childModules[$moduleId] ?? [];
    }

    /**
     * @return list<int>
     */
    public function featureIds(int $moduleId): array
    {
        return $this->moduleFeatures[$moduleId] ?? [];
    }

    public function hasChildren(int $moduleId): bool
    {
        return $this->childModuleIds($moduleId) !== [] || $this->featureIds($moduleId) !== [];
    }

    /**
     * Ancestors of a module, closest first.
     *
     * @return list<int>
     */
    public function ancestorModuleIds(int $moduleId): array
    {
        $ancestors = [];
        $parentId = $this->modules[$moduleId]['parent_id'] ?? null;

        while ($parentId !== null && ! in_array($parentId, $ancestors, true)) {
            $ancestors[] = $parentId;
            $parentId = $this->modules[$parentId]['parent_id'] ?? null;
        }

        return $ancestors;
    }

    /**
     * The feature's module and all its ancestors.
     *
     * @return list<int>
     */
    public function featureAncestorModuleIds(int $featureId): array
    {
        $moduleId = $this->features[$featureId]['module_id'] ?? null;

        return $moduleId === null ? [] : [$moduleId, ...$this->ancestorModuleIds($moduleId)];
    }

    /**
     * All modules below a module (not including it), depth first.
     *
     * @return list<int>
     */
    public function descendantModuleIds(int $moduleId): array
    {
        $descendants = [];

        foreach ($this->childModuleIds($moduleId) as $childId) {
            $descendants[] = $childId;
            array_push($descendants, ...$this->descendantModuleIds($childId));
        }

        return $descendants;
    }

    /**
     * Features of a module and of all its descendants.
     *
     * @return list<int>
     */
    public function featureIdsInSubtree(int $moduleId): array
    {
        $featureIds = $this->featureIds($moduleId);

        foreach ($this->descendantModuleIds($moduleId) as $descendantId) {
            array_push($featureIds, ...$this->featureIds($descendantId));
        }

        return $featureIds;
    }

    public function depth(int $moduleId): int
    {
        return count($this->ancestorModuleIds($moduleId));
    }
}
