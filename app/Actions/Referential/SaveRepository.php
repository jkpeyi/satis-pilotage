<?php

namespace App\Actions\Referential;

use App\Enums\RepositoryRole;
use App\Models\Repository;

/**
 * Phase 1: repositories and their stack are entered manually.
 */
final class SaveRepository
{
    public function __construct(private readonly RegisterTechnologies $registerTechnologies) {}

    /**
     * @param  array{version_id: int, path: string, web_url: ?string, role: RepositoryRole, production_branch: string, active_branches: list<string>, stack: array<string, string>}  $data
     */
    public function handle(array $data, ?Repository $repository = null): Repository
    {
        $repository ??= new Repository;

        $data['path'] = trim($data['path']);
        $data['stack'] = $this->normalizeStack($data['stack']);
        $data['active_branches'] = array_values(array_unique(array_filter(array_map('trim', $data['active_branches']))));

        $repository->fill($data)->save();
        $this->registerTechnologies->handle($data['stack']);

        return $repository;
    }

    /**
     * Lower-cases component names so they match the technologies referential.
     *
     * @param  array<string, string>  $stack
     * @return array<string, string>
     */
    private function normalizeStack(array $stack): array
    {
        $normalized = [];

        foreach ($stack as $component => $version) {
            $component = strtolower(trim($component));
            $version = trim($version);

            if ($component !== '' && $version !== '') {
                $normalized[$component] = $version;
            }
        }

        ksort($normalized);

        return $normalized;
    }
}
