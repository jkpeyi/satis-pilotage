<?php

namespace App\Actions\Referential;

use App\Enums\RepositoryRole;
use App\Models\Repository;
use DomainException;

/**
 * Phase 1: repositories and their stack are entered manually. A repository is entered by its full
 * GitLab URL; its path (its name in the application) is derived from it.
 */
final class SaveRepository
{
    public function __construct(private readonly RegisterTechnologies $registerTechnologies) {}

    /**
     * @param  array{version_id: int, web_url: string, role: RepositoryRole, production_branch: string, active_branches: list<string>, stack: array<string, string>}  $data
     */
    public function handle(array $data, ?Repository $repository = null): Repository
    {
        $repository ??= new Repository;

        $data['web_url'] = rtrim(trim($data['web_url']), '/');
        $path = Repository::pathFromUrl($data['web_url']);
        if ($path === null) {
            throw new DomainException('L’URL GitLab doit contenir le chemin du projet.');
        }
        $data['path'] = $path;
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
