<?php

use App\Actions\Referential\SaveEnvironment;
use App\Actions\Referential\SaveRepository;
use App\Enums\EnvironmentType;
use App\Enums\RepositoryRole;
use App\Models\Repository;
use App\Models\Version;

it('normalizes the manually entered stack and branches', function () {
    $version = Version::factory()->create();

    $repository = app(SaveRepository::class)->handle([
        'version_id' => $version->id,
        'path' => ' satis/macro-api ',
        'web_url' => null,
        'role' => RepositoryRole::Backend,
        'production_branch' => 'main',
        'active_branches' => ['main', ' develop', '', 'main'],
        'stack' => ['PHP ' => '8.1.27', 'Laravel' => ' 9.52.16 ', 'react' => '', '' => '1.0'],
    ]);

    expect($repository->refresh()->stack)->toEqual(['laravel' => '9.52.16', 'php' => '8.1.27'])
        ->and($repository->active_branches)->toBe(['main', 'develop'])
        ->and($repository->path)->toBe('satis/macro-api');
});

it('refuses an environment linked to a repository of another version', function () {
    $version = Version::factory()->create();
    $foreignRepository = Repository::factory()->create();

    app(SaveEnvironment::class)->handle([
        'version_id' => $version->id,
        'repository_id' => $foreignRepository->id,
        'type' => EnvironmentType::Production,
        'name' => 'Production',
        'url' => null,
        'deployed_ref' => null,
        'database_engine' => null,
        'database_version' => null,
        'php_version' => null,
        'server' => null,
        'notes' => null,
    ]);
})->throws(DomainException::class);
