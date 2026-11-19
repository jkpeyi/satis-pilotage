<?php

namespace App\Queries;

use App\Support\CatalogName;
use Illuminate\Support\Facades\DB;

/**
 * Suggestions while typing a catalog name: matching modules and features from the whole catalog,
 * the same name first, then names starting with the text, then names containing it.
 */
final class CatalogSearch
{
    /**
     * @return list<array{key: string, type: 'module'|'feature', id: int, name: string, path: string, exact: bool, archived: bool}>
     */
    public function search(string $text, int $limit = 8, bool $withArchived = true): array
    {
        $key = CatalogName::key($text);
        if (mb_strlen($key) < 2) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%';
        $rank = 'CASE WHEN name_key = ? THEN 0 WHEN name_key LIKE ? THEN 1 ELSE 2 END';
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%';

        $query = fn (string $table, string $type) => DB::table($table)
            ->selectRaw("id, name, archived_at, '{$type}' as type, {$rank} as match_rank, CHAR_LENGTH(name) as name_length", [$key, $prefix])
            ->where('name_key', 'like', $like)
            ->when(! $withArchived, fn ($query) => $query->whereNull('archived_at'));

        $rows = $query('modules', 'module')
            ->unionAll($query('features', 'feature'))
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('match_rank')
            ->orderBy('name_length')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $tree = CatalogTree::load(withArchived: true);

        return $rows->map(function (object $row) use ($tree): array {
            $id = (int) $row->id;
            $moduleIds = $row->type === 'module'
                ? [...array_reverse($tree->ancestorModuleIds($id)), $id]
                : array_reverse($tree->featureAncestorModuleIds($id));
            $names = array_map(fn (int $moduleId): string => $tree->modules[$moduleId]['name'] ?? '?', $moduleIds);
            if ($row->type === 'feature') {
                $names[] = $row->name;
            }

            return [
                'key' => $row->type.':'.$id,
                'type' => $row->type === 'module' ? 'module' : 'feature',
                'id' => $id,
                'name' => (string) $row->name,
                'path' => implode(' › ', $names),
                'exact' => (int) $row->match_rank === 0,
                'archived' => $row->archived_at !== null,
            ];
        })->values()->all();
    }
}
