<?php

namespace App\Actions\Implementations;

use App\Models\BusinessRule;
use App\Models\Implementation;
use App\Models\Version;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Initializes a version from another one (EF-108): copies its implementations and, on demand,
 * their statuses, deviations and business rules. Items already attached to the target are left untouched.
 * Copied rules keep a link to their origin (RG-11). Copied statuses are recorded in the history (RG-06).
 */
final class InitializeVersionFrom
{
    /**
     * @param  array{statuses: bool, deviations: bool, rules: bool}  $options
     * @return int Number of implementations created in the target.
     */
    public function handle(Version $source, Version $target, array $options): int
    {
        if ($source->is($target)) {
            throw new DomainException('Choisissez une autre version que celle à initialiser.');
        }

        return DB::transaction(function () use ($source, $target, $options): int {
            $userId = Auth::id();
            $existing = $target->implementations()->get(['module_id', 'feature_id']);
            $existingModules = $existing->pluck('module_id')->filter()->all();
            $existingFeatures = $existing->pluck('feature_id')->filter()->all();

            $sources = $source->implementations()->whereNull('archived_at')
                ->with(['businessRules' => fn ($query) => $query->whereNull('archived_at')->with('codeSnippets')])
                ->get()
                ->reject(fn (Implementation $implementation): bool => in_array($implementation->module_id, $existingModules, true)
                    || in_array($implementation->feature_id, $existingFeatures, true));

            foreach ($sources as $implementation) {
                $copy = Implementation::query()->create([
                    'version_id' => $target->id,
                    'module_id' => $implementation->module_id,
                    'feature_id' => $implementation->feature_id,
                    'deviation' => $options['deviations'] ? $implementation->deviation : null,
                ]);

                if ($options['statuses'] && $implementation->status !== $copy->status) {
                    $copy->statusChanges()->create([
                        'from_status' => $copy->status,
                        'to_status' => $implementation->status,
                        'note' => "Initialisé depuis la version {$source->name}",
                        'user_id' => $userId,
                    ]);
                    $copy->forceFill(['status' => $implementation->status])->saveQuietly();
                }

                if ($options['rules']) {
                    $implementation->businessRules->each(function (BusinessRule $rule) use ($copy): void {
                        $ruleCopy = $rule->replicate(['implementation_id', 'origin_rule_id', 'created_by', 'updated_by']);
                        $ruleCopy->implementation_id = $copy->id;
                        $ruleCopy->origin_rule_id = $rule->origin_rule_id ?? $rule->id;
                        $ruleCopy->save();

                        foreach ($rule->codeSnippets->whereNull('archived_at') as $snippet) {
                            $snippetCopy = $snippet->replicate(['business_rule_id', 'created_by', 'updated_by']);
                            $snippetCopy->business_rule_id = $ruleCopy->id;
                            $snippetCopy->save();
                        }
                    });
                }
            }

            return $sources->count();
        });
    }
}
