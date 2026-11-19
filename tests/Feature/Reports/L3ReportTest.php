<?php

use App\Actions\Reports\GenerateReport;
use App\Enums\ComponentType;
use App\Enums\IntegrationProtocol;
use App\Enums\VersionStatus;
use App\Models\Component;
use App\Models\Environment;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\Repository;
use App\Models\Technology;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->version = Version::factory()->for($this->product)->create(['name' => 'Macro Alpha']);
    $this->repository = Repository::factory()->for($this->version)->create([
        'path' => 'satis/api', 'web_url' => 'https://gitlab.example.test/satis/api', 'stack' => ['php' => '7.4.33', 'laravel' => '12.1.0'],
    ]);
    Environment::factory()->for($this->version)->create(['name' => 'Production', 'database_engine' => 'MySQL', 'database_version' => '5.7']);
    Technology::factory()->create(['component' => 'php', 'cycle' => '7.4', 'end_of_support' => '2022-11-28']);
    Technology::factory()->create(['component' => 'laravel', 'cycle' => '12', 'end_of_support' => '2099-02-24']);
    Technology::factory()->create(['component' => 'mysql', 'cycle' => '5.7', 'end_of_support' => '2023-10-31']);

    $this->api = Component::factory()->for($this->version)->create(['name' => 'API métier', 'repository_id' => $this->repository->id, 'technology' => 'Laravel', 'technology_version' => '12.1.0', 'evidence_url' => 'https://gitlab.example.test/satis/api/-/blob/main/composer.lock']);
    $this->front = Component::factory()->for($this->version)->create(['name' => 'Front', 'type' => ComponentType::Web, 'technology' => 'React', 'technology_version' => '17.0.2']);
    Integration::query()->create(['source_component_id' => $this->front->id, 'target_component_id' => $this->api->id, 'protocol' => IntegrationProtocol::Rest]);
    Integration::query()->create(['source_component_id' => $this->api->id, 'external_system' => 'Centrale des risques', 'protocol' => IntegrationProtocol::File, 'evidence_url' => 'https://docs.example.test/crb']);

    $saas = Version::factory()->for($this->product)->create(['name' => 'Satis_SaaS', 'status' => VersionStatus::Future]);
    Component::factory()->for($saas)->create(['name' => 'Portail SaaS']);

    $this->generate = fn (): ReportGeneration => app(GenerateReport::class)->handle('L3', new ReportScope($this->product->id), $this->user);
});

it('maps components, integrations, databases and dependencies, without the future perspective', function () {
    $generation = ($this->generate)();
    $sections = json_decode($generation->snapshot, true)['sections'];

    expect(array_column($sections['components.table']['rows'], 'name'))->toBe(['API métier', 'Front'])
        ->and($sections['components.table']['rows'][0])->toMatchArray([
            'component_id' => $this->api->displayCode(),
            'application_id' => $this->version->displayCode(),
            'component_type' => 'API / backend',
            'repository_id' => $this->repository->displayCode().' · satis/api',
            'technology' => 'Laravel',
            'version' => '12.1.0',
            'verification_status' => 'À vérifier',
        ])
        ->and($sections['components.table']['rows'][1]['repository_id'])->toBeNull()
        ->and(array_column($sections['integrations.table']['rows'], 'target'))->toBe([
            'Externe : Centrale des risques',
            $this->api->displayCode().' · API métier ('.$this->version->displayCode().')',
        ])
        ->and($sections['databases.table']['rows'][0])->toMatchArray(['database_engine' => 'MySQL', 'database_version' => '5.7', 'support' => 'Hors support (fin : 31/10/2023)'])
        ->and(collect($sections['dependencies.table']['rows'])->firstWhere('dependency_name', 'php'))->toMatchArray([
            'dependency_version' => '7.4.33',
            'support' => 'Hors support (fin : 28/11/2022)',
            'evidence_url' => 'https://gitlab.example.test/satis/api',
        ]);

    $risks = collect($sections['architecture.risks']['blocks'])->pluck('text')->implode("\n");
    expect($risks)
        ->toContain('satis/api : php 7.4.33 — hors support')
        ->toContain('Production : MySQL 5.7 — hors support')
        ->not->toContain('laravel 12.1.0')
        ->toContain('1 intégration sans preuve')
        ->and(collect($sections['architecture.overview']['blocks'])->pluck('text')->implode("\n"))
        ->toContain('1 application, 1 dépôt, 2 composants et 2 intégrations')
        ->toContain('Systèmes externes : Centrale des risques.')
        ->not->toContain('Portail SaaS');
});

it('marks a component without repository or evidence as to be confirmed', function () {
    $generation = ($this->generate)();

    expect($generation->missing_count)->toBeGreaterThan(0)
        ->and(collect(json_decode($generation->snapshot, true)['sections'])->keys()->all())
        ->toBe(['architecture.overview', 'components.table', 'integrations.table', 'databases.table', 'dependencies.table', 'architecture.risks']);
});
