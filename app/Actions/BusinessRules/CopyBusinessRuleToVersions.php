<?php

namespace App\Actions\BusinessRules;

use App\Actions\Implementations\AttachCatalogItems;
use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Implementation;
use Illuminate\Support\Facades\DB;

/**
 * Copies a business rule to the same catalog item in other versions (EF-207, RG-11).
 *
 * Every copy points to the root of the family (origin_rule_id), so that all versions of a rule
 * can be aligned in comparisons. A version that already has a copy of this family is skipped.
 * When the item is not attached to a target version, it is attached first if $attachMissing.
 */
final class CopyBusinessRuleToVersions
{
    public function __construct(private readonly AttachCatalogItems $attachCatalogItems) {}

    /**
     * @param  list<int>  $versionIds
     * @return int Number of copies created.
     */
    public function handle(BusinessRule $rule, array $versionIds, bool $attachMissing = false): int
    {
        $source = $rule->implementation;
        $originId = $rule->origin_rule_id ?? $rule->id;
        $versionIds = array_values(array_diff($versionIds, [$source->version_id]));

        return DB::transaction(function () use ($rule, $source, $originId, $versionIds, $attachMissing): int {
            if ($attachMissing && $versionIds !== []) {
                $source->module_id !== null
                    ? $this->attachCatalogItems->handle($versionIds, [$source->module_id], [], withDescendants: false)
                    : $this->attachCatalogItems->handle($versionIds, [], [(int) $source->feature_id]);
            }

            $targets = Implementation::query()
                ->whereIn('version_id', $versionIds)
                ->whereNull('archived_at')
                ->where($source->module_id !== null ? 'module_id' : 'feature_id', $source->module_id ?? $source->feature_id)
                ->get();

            $count = 0;

            foreach ($targets as $target) {
                $alreadyCopied = BusinessRule::query()
                    ->where('implementation_id', $target->id)
                    ->whereNull('archived_at')
                    ->where(fn ($query) => $query->where('origin_rule_id', $originId)->orWhere('id', $originId))
                    ->exists();

                if ($alreadyCopied) {
                    continue;
                }

                $copy = $rule->replicate(['implementation_id', 'origin_rule_id', 'created_by', 'updated_by', 'archived_at']);
                $copy->implementation_id = $target->id;
                $copy->origin_rule_id = $originId;
                $copy->save();

                $rule->codeSnippets()->whereNull('archived_at')->get()
                    ->each(function (CodeSnippet $snippet) use ($copy): void {
                        $snippetCopy = $snippet->replicate(['business_rule_id', 'created_by', 'updated_by']);
                        $snippetCopy->business_rule_id = $copy->id;
                        $snippetCopy->save();
                    });

                $count++;
            }

            return $count;
        });
    }
}
