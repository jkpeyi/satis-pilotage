<?php

namespace App\Actions\Referential;

use App\Models\Component;
use App\Models\Repository;

/**
 * Registers every technology already entered on active repositories (stack) and components.
 * New entries register themselves when saved.
 */
final class SyncTechnologiesFromEstate
{
    public function __construct(private readonly RegisterTechnologies $registerTechnologies) {}

    public function handle(): int
    {
        $added = 0;

        Repository::query()->active()->whereNotNull('stack')->each(function (Repository $repository) use (&$added): void {
            $added += $this->registerTechnologies->handle($repository->stack ?? []);
        });

        Component::query()->active()->whereNotNull('technology')->each(function (Component $component) use (&$added): void {
            $added += $this->registerTechnologies->handle([(string) $component->technology => $component->technology_version]);
        });

        return $added;
    }
}
