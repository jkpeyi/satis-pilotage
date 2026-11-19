<?php

namespace App\Queries;

use App\Actions\Catalog\EnsureUniqueCatalogName;
use App\Models\Feature;
use App\Models\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Active catalog items sharing a name (case, accents and punctuation ignored).
 * Same-kind groups can be merged; a module and a feature with the same name need a decision
 * (convert one of them first).
 */
final class CatalogDuplicates
{
    public function __construct(private readonly EnsureUniqueCatalogName $names) {}

    /**
     * Groups of the same kind, the item to keep first: the most implementations, then a standard
     * description, then the oldest.
     *
     * @return list<array{kind: 'module'|'feature', name_key: string, items: list<array{item: Module|Feature, path: string, implementations: int}>}>
     */
    public function mergeableGroups(): array
    {
        $groups = [];

        foreach (['module' => Module::class, 'feature' => Feature::class] as $kind => $class) {
            $keys = $class::query()->active()->select('name_key')->groupBy('name_key')->havingRaw('count(*) > 1')->pluck('name_key');

            foreach ($keys as $key) {
                $items = $class::query()->active()->where('name_key', $key)->withCount('implementations')->get()
                    ->sortBy(fn (Module|Feature $item): array => [-$item->implementations_count, blank($item->standard_description) ? 1 : 0, $item->id])
                    ->values()
                    ->map(fn (Module|Feature $item): array => ['item' => $item, 'path' => $this->names->path($item), 'implementations' => (int) $item->implementations_count])
                    ->all();

                $groups[] = ['kind' => $kind, 'name_key' => (string) $key, 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Names used by an active module and an active feature at the same time.
     *
     * @return list<array{name_key: string, items: list<array{item: Module|Feature, path: string}>}>
     */
    public function crossKindGroups(): array
    {
        $keys = DB::table('modules')->whereNull('archived_at')->select('name_key')
            ->whereIn('name_key', DB::table('features')->whereNull('archived_at')->select('name_key'))
            ->distinct()->pluck('name_key');

        return $keys->map(fn (string $key): array => [
            'name_key' => $key,
            'items' => (new Collection([
                ...Module::query()->active()->where('name_key', $key)->get()->all(),
                ...Feature::query()->active()->where('name_key', $key)->get()->all(),
            ]))->map(fn (Module|Feature $item): array => ['item' => $item, 'path' => $this->names->path($item)])->all(),
        ])->values()->all();
    }

    /**
     * Active items of the same kind with close names: one or two typing differences, a plural,
     * or the same words in another order. Not blocked by the uniqueness rule, to be reviewed.
     *
     * @return list<array{kind: 'module'|'feature', a: array{item: Module|Feature, path: string}, b: array{item: Module|Feature, path: string}}>
     */
    public function similarPairs(int $limit = 100): array
    {
        $pairs = [];

        foreach (['module' => Module::class, 'feature' => Feature::class] as $kind => $class) {
            /** @var Collection<int, Module|Feature> $items */
            $items = $class::query()->active()->get(['id', 'name', 'name_key'])
                ->sortBy(fn (Module|Feature $item): int => strlen($item->name_key))
                ->values();
            $count = $items->count();

            for ($i = 0; $i < $count && count($pairs) < $limit; $i++) {
                $a = $items[$i];
                for ($j = $i + 1; $j < $count; $j++) {
                    $b = $items[$j];
                    if (strlen($b->name_key) - strlen($a->name_key) > 2) {
                        break;
                    }
                    if ($this->close($a->name_key, $b->name_key)) {
                        $pairs[] = [
                            'kind' => $kind,
                            'a' => ['item' => $a, 'path' => $this->names->path($a)],
                            'b' => ['item' => $b, 'path' => $this->names->path($b)],
                        ];
                    }
                }
            }
        }

        return array_slice($pairs, 0, $limit);
    }

    private function close(string $a, string $b): bool
    {
        if ($a === $b || min(strlen($a), strlen($b)) < 5) {
            return $a === $b;
        }

        $words = fn (string $key): array => collect(explode(' ', $key))
            ->map(fn (string $word): string => rtrim($word, 'sx'))
            ->sort()->values()->all();

        return $words($a) === $words($b) || levenshtein($a, $b) <= 2;
    }
}
