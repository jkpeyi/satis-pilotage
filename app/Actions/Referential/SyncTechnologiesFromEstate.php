<?php

namespace App\Actions\Referential;

use App\Models\Component;
use App\Models\Environment;
use App\Models\Repository;

/**
 * Registers every technology already entered on active repositories (stack), components and
 * environments (database engine, PHP version). New entries register themselves when saved.
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

        Environment::query()->active()->each(function (Environment $environment) use (&$added): void {
            $added += $this->registerTechnologies->handle($this->environmentStack($environment));
        });

        return $added;
    }

    /**
     * @return array<string, string|null>
     */
    public function environmentStack(Environment $environment): array
    {
        $stack = ['php' => $environment->php_version];
        if (filled($environment->database_engine)) {
            $stack[(string) $environment->database_engine] = $environment->database_version;
        }

        return $stack;
    }
}
