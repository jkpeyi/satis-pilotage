<?php

use App\Actions\Referential\SaveRepository;
use App\Enums\RepositoryRole;
use App\Models\Repository;
use App\Models\Version;

it('normalizes the manually entered stack and branches', function () {
    $version = Version::factory()->create();

    $repository = app(SaveRepository::class)->handle([
        'version_id' => $version->id,
        'web_url' => ' https://gitlab.example.test/satis/macro-api/ ',
        'role' => RepositoryRole::Backend,
        'production_branch' => 'main',
        'active_branches' => ['main', ' develop', '', 'main'],
        'stack' => ['PHP ' => '8.1.27', 'Laravel' => ' 9.52.16 ', 'react' => '', '' => '1.0'],
    ]);

    expect($repository->refresh()->stack)->toEqual(['laravel' => '9.52.16', 'php' => '8.1.27'])
        ->and($repository->active_branches)->toBe(['main', 'develop'])
        ->and($repository->path)->toBe('satis/macro-api')
        ->and($repository->web_url)->toBe('https://gitlab.example.test/satis/macro-api');
});

it('derives the repository path from its full GitLab URL', function (string $url, ?string $path) {
    expect(Repository::pathFromUrl($url))->toBe($path);
})->with([
    ['https://gitlab.example.test/satis/macro-api', 'satis/macro-api'],
    ['https://gitlab.example.test/satis/sub/group/macro-api.git', 'satis/sub/group/macro-api'],
    ['https://gitlab.example.test/satis/macro-api/-/tree/main', 'satis/macro-api'],
    ['https://gitlab.example.test/', null],
]);
