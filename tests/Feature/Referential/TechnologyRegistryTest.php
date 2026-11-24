<?php

use App\Actions\Architecture\SaveComponent;
use App\Actions\Referential\RegisterTechnologies;
use App\Actions\Referential\SaveRepository;
use App\Enums\ComponentType;
use App\Enums\RepositoryRole;
use App\Enums\VerificationStatus;
use App\Livewire\Technologies\Index as TechnologiesPage;
use App\Models\Component;
use App\Models\Repository;
use App\Models\Technology;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->version = Version::factory()->create();
    $this->cycles = fn (): array => Technology::query()->orderBy('component')->orderBy('cycle')->get()
        ->map(fn (Technology $technology): string => $technology->component.' '.$technology->cycle)->all();
});

it('derives the cycle of a version without duplicating a known one', function (string $component, string $version, ?string $expected) {
    Technology::query()->create(['component' => 'oracle', 'cycle' => '19.3']);
    Technology::query()->create(['component' => 'laravel', 'cycle' => '10']);

    expect(app(RegisterTechnologies::class)->cycleFor($component, $version))->toBe($expected);
})->with([
    'major for most technologies' => ['react', '17.0.2', '17'],
    'major.minor for php' => ['php', '8.1.27', '8.1'],
    'constraints and prefixes' => ['php', '^8.2', '8.2'],
    'v prefix' => ['node', 'v16.20.0', '16'],
    'granularity of the known cycles' => ['oracle', '21.4.0', '21.4'],
    'covered by a known cycle' => ['laravel', '10.48.4', null],
    'no number' => ['vue', 'latest', null],
]);

it('registers the stack of a repository when it is saved, without a support date', function () {
    app(SaveRepository::class)->handle([
        'version_id' => $this->version->id, 'web_url' => 'https://gitlab.example.test/satis/api', 'role' => RepositoryRole::Backend,
        'production_branch' => 'main', 'active_branches' => ['main'], 'stack' => ['PHP' => '8.1.27', 'Laravel' => '9.52.16', 'composer' => 'latest'],
    ]);
    app(SaveRepository::class)->handle([
        'version_id' => $this->version->id, 'web_url' => 'https://gitlab.example.test/satis/batch', 'role' => RepositoryRole::Other,
        'production_branch' => 'main', 'active_branches' => ['main'], 'stack' => ['php' => '8.1.2'],
    ]);

    expect(($this->cycles)())->toBe(['laravel 9', 'php 8.1'])
        ->and(Technology::query()->whereNotNull('end_of_support')->count())->toBe(0);
});

it('registers the technology of components, database engines included', function () {
    app(SaveComponent::class)->handle([
        'version_id' => $this->version->id, 'repository_id' => null, 'name' => 'Front', 'type' => ComponentType::Web,
        'technology' => 'React', 'technology_version' => '17.0.2', 'description' => null, 'evidence_url' => null,
        'verification_status' => VerificationStatus::ToVerify,
    ]);
    app(SaveComponent::class)->handle([
        'version_id' => $this->version->id, 'repository_id' => null, 'name' => 'Base principale', 'type' => ComponentType::Database,
        'technology' => 'MySQL', 'technology_version' => '5.7.44', 'description' => null, 'evidence_url' => null,
        'verification_status' => VerificationStatus::ToVerify,
    ]);

    expect(($this->cycles)())->toBe(['mysql 5.7', 'react 17']);
});

it('brings in the technologies entered before, from the technologies page', function () {
    Repository::factory()->for($this->version)->create(['stack' => ['symfony' => '5.4.21']]);
    Component::factory()->for($this->version)->create(['technology' => 'Angular', 'technology_version' => '12.2.0']);
    Component::factory()->for($this->version)->create(['type' => ComponentType::Database, 'technology' => 'Oracle', 'technology_version' => '19c']);

    Livewire::test(TechnologiesPage::class)
        ->call('sync')
        ->assertDispatched('notify', message: '3 cycle(s) ajouté(s) depuis les versions : renseignez leur date de fin de support.')
        ->assertSee('3 cycle(s) sans date de fin de support')
        ->assertSee('À renseigner')
        ->call('sync')
        ->assertDispatched('notify', message: 'Toutes les technologies des versions sont déjà dans le référentiel.');

    expect(($this->cycles)())->toBe(['angular 12', 'oracle 19', 'symfony 5']);

    Technology::query()->where('component', 'symfony')->update(['end_of_support' => '2029-02-28']);
    Livewire::test(TechnologiesPage::class)
        ->set('onlyMissing', true)
        ->assertSee('angular')
        ->assertDontSee('symfony');

    $this->artisan('technologies:sync')->expectsOutputToContain('0 cycle(s) ajouté(s)')->assertSuccessful();
});
