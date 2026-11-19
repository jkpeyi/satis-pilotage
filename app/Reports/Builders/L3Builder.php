<?php

namespace App\Reports\Builders;

use App\Actions\Referential\ResolveStackSupport;
use App\Enums\ComponentType;
use App\Enums\SupportStatus;
use App\Enums\VerificationStatus;
use App\Models\Component;
use App\Models\Integration;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use Illuminate\Support\Collection;

/**
 * L3 — Cartographie technique: components of each application, their integrations, the database
 * engines and the dependencies of the repositories, checked against the technologies referential.
 * A future perspective (Satis_SaaS) is left out. Everything is entered by hand while the GitLab
 * integration is disabled; nothing is guessed.
 */
final class L3Builder implements ReportBuilder
{
    /** @var array<string, array{status: SupportStatus, label: string}> "laravel|9.52.16" => support */
    private array $supportCache = [];

    public function __construct(private readonly ResolveStackSupport $resolveStackSupport) {}

    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();

        $versions = Version::query()
            ->active()
            ->excludingFuture()
            ->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id))
            ->with([
                'repositories' => fn ($query) => $query->active()->orderBy('path'),
                'environments' => fn ($query) => $query->active()->orderBy('type')->orderBy('name'),
                'components' => fn ($query) => $query->active()->with('repository')->orderBy('type')->orderBy('name'),
            ])
            ->orderBy('name')->orderBy('id')
            ->get();

        $componentIds = $versions->flatMap(fn (Version $version): Collection => $version->components->pluck('id'));
        $integrations = Integration::query()
            ->active()
            ->whereIn('source_component_id', $componentIds)
            ->where(fn ($query) => $query->whereNull('target_component_id')->orWhereHas('target', fn ($query) => $query->whereNull('archived_at')))
            ->with(['source.version', 'target.version'])
            ->orderBy('source_component_id')->orderBy('id')
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('architecture.overview', $this->overview($versions, $integrations))
            ->set('components.table', $this->componentsTable($versions))
            ->set('integrations.table', $this->integrationsTable($integrations))
            ->set('databases.table', $this->databasesTable($versions))
            ->set('dependencies.table', $this->dependenciesTable($versions))
            ->set('architecture.risks', $this->risks($versions, $integrations));
    }

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, Integration>  $integrations
     */
    private function overview(Collection $versions, Collection $integrations): TextSection
    {
        $components = $versions->flatMap(fn (Version $version): Collection => $version->components);
        $byType = $components->countBy(fn (Component $component): string => $component->type->label())
            ->map(fn (int $count, string $label): string => $label.' : '.$count)
            ->values();
        $externals = $integrations->filter(fn (Integration $integration): bool => $integration->isExternal())
            ->pluck('external_system')->unique()->sort()->values();

        $repositories = $versions->sum(fn (Version $version): int => $version->repositories->count());

        $section = (new TextSection)->paragraph(sprintf(
            '%d application%s, %d dépôt%s, %d composant%s et %d intégration%s dans le périmètre.',
            $versions->count(), $this->plural($versions->count()),
            $repositories, $this->plural($repositories),
            $components->count(), $this->plural($components->count()),
            $integrations->count(), $this->plural($integrations->count()),
        ));

        if ($byType->isNotEmpty()) {
            $section->paragraph('Composants par type :')->bullets($byType->all());
        }

        if ($externals->isNotEmpty()) {
            $section->paragraph('Systèmes externes : '.$externals->implode(', ').'.');
        }

        return $section->paragraph('Source des données : saisie manuelle dans Satis Pilotage. L’intégration GitLab n’étant pas activée, les versions et les dépendances ne sont pas relues automatiquement dans les dépôts ; chaque ligne porte son statut de vérification.');
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function componentsTable(Collection $versions): TableSection
    {
        $table = (new TableSection('Composants'))
            ->column('component_id', 'Identifiant')
            ->column('application_id', 'Application')
            ->column('name', 'Composant')
            ->column('component_type', 'Type')
            ->column('repository_id', 'Dépôt')
            ->column('technology', 'Technologie')
            ->column('version', 'Version')
            ->column('evidence_url', 'Preuve')
            ->column('verification_status', 'Vérification');

        foreach ($versions as $version) {
            foreach ($version->components as $component) {
                $table->row([
                    'component_id' => $component->displayCode(),
                    'application_id' => $version->displayCode(),
                    'name' => $component->name,
                    'component_type' => $component->type->label(),
                    'repository_id' => $component->repository !== null ? $component->repository->displayCode().' · '.$component->repository->path : null,
                    'technology' => $component->technology,
                    'version' => $component->technology_version,
                    'evidence_url' => $component->evidence_url,
                    'verification_status' => $component->verification_status->label(),
                ]);
            }
        }

        return $table;
    }

    /**
     * @param  Collection<int, Integration>  $integrations
     */
    private function integrationsTable(Collection $integrations): TableSection
    {
        $table = (new TableSection('Intégrations'))
            ->column('integration_id', 'Identifiant')
            ->column('source', 'Source')
            ->column('target', 'Cible')
            ->column('protocol', 'Protocole')
            ->column('description', 'Description', required: false)
            ->column('evidence_url', 'Preuve')
            ->column('verification_status', 'Vérification');

        foreach ($integrations as $integration) {
            $table->row([
                'integration_id' => $integration->displayCode(),
                'source' => $this->componentLabel($integration->source),
                'target' => $integration->target !== null ? $this->componentLabel($integration->target) : 'Externe : '.$integration->external_system,
                'protocol' => $integration->protocol->label(),
                'description' => $integration->description,
                'evidence_url' => $integration->evidence_url,
                'verification_status' => $integration->verification_status->label(),
            ]);
        }

        return $table;
    }

    /**
     * Engines of the environments, then database components, with their support status.
     *
     * @param  Collection<int, Version>  $versions
     */
    private function databasesTable(Collection $versions): TableSection
    {
        $table = (new TableSection('Bases de données'))
            ->column('application_id', 'Application')
            ->column('source', 'Relevé sur')
            ->column('database_engine', 'Moteur')
            ->column('database_version', 'Version')
            ->column('support', 'Support', required: false);

        foreach ($versions as $version) {
            foreach ($version->environments as $environment) {
                $table->row([
                    'application_id' => $version->displayCode(),
                    'source' => sprintf('%s · %s (%s)', $environment->displayCode(), $environment->name, $environment->type->label()),
                    'database_engine' => $environment->database_engine,
                    'database_version' => $environment->database_version,
                    'support' => $this->support($environment->database_engine, $environment->database_version),
                ]);
            }

            foreach ($version->components->filter(fn (Component $component): bool => $component->type === ComponentType::Database) as $component) {
                $table->row([
                    'application_id' => $version->displayCode(),
                    'source' => $component->displayCode().' · '.$component->name,
                    'database_engine' => $component->technology,
                    'database_version' => $component->technology_version,
                    'support' => $this->support($component->technology, $component->technology_version),
                ]);
            }
        }

        return $table;
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function dependenciesTable(Collection $versions): TableSection
    {
        $table = (new TableSection('Dépendances'))
            ->column('application_id', 'Application')
            ->column('repository_id', 'Dépôt')
            ->column('dependency_name', 'Dépendance')
            ->column('dependency_version', 'Version')
            ->column('support', 'Support', required: false)
            ->column('evidence_url', 'Preuve (dépôt)');

        foreach ($versions as $version) {
            foreach ($version->repositories as $repository) {
                foreach ($this->resolveStackSupport->handle($repository->stack ?? []) as $name => $support) {
                    $table->row([
                        'application_id' => $version->displayCode(),
                        'repository_id' => $repository->displayCode().' · '.$repository->path,
                        'dependency_name' => $name,
                        'dependency_version' => $support['version'],
                        'support' => $this->supportLabel($support['status'], $support['technology']?->end_of_support?->format('d/m/Y')),
                        'evidence_url' => $repository->web_url,
                    ]);
                }
            }
        }

        return $table;
    }

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, Integration>  $integrations
     */
    private function risks(Collection $versions, Collection $integrations): TextSection
    {
        $obsolete = [];
        $note = function (string $where, ?string $name, ?string $version) use (&$obsolete): void {
            $support = $this->supportOf($name, $version);
            if ($support !== null && in_array($support['status'], [SupportStatus::OutOfSupport, SupportStatus::EndingSoon], true)) {
                $obsolete[] = sprintf('%s : %s %s — %s.', $where, $name, $version, mb_strtolower($support['label']));
            }
        };

        foreach ($versions as $version) {
            foreach ($version->repositories as $repository) {
                foreach ($repository->stack ?? [] as $name => $dependencyVersion) {
                    $note($version->displayCode().' · '.$repository->path, $name, $dependencyVersion);
                }
            }
            foreach ($version->components as $component) {
                $note($version->displayCode().' · '.$component->name, $component->technology, $component->technology_version);
            }
            foreach ($version->environments as $environment) {
                $note($version->displayCode().' · '.$environment->name, $environment->database_engine, $environment->database_version);
            }
        }

        $components = $versions->flatMap(fn (Version $version): Collection => $version->components);
        $toVerify = $components->filter(fn (Component $component): bool => $component->verification_status === VerificationStatus::ToVerify)->count()
            + $integrations->filter(fn (Integration $integration): bool => $integration->verification_status === VerificationStatus::ToVerify)->count();
        $withoutEvidence = $integrations->filter(fn (Integration $integration): bool => blank($integration->evidence_url))->count();
        $withoutComponents = $versions->filter(fn (Version $version): bool => $version->components->isEmpty());

        $section = new TextSection;
        $section->paragraph('Risques relevés :');
        $risks = array_values(array_unique($obsolete));
        if ($toVerify > 0) {
            $risks[] = sprintf('%d composant%s ou intégration%s encore à vérifier.', $toVerify, $this->plural($toVerify), $this->plural($toVerify));
        }
        if ($withoutEvidence > 0) {
            $risks[] = sprintf('%d intégration%s sans preuve (contrat d’interface, code client ou documentation).', $withoutEvidence, $this->plural($withoutEvidence));
        }
        if ($withoutComponents->isNotEmpty()) {
            $risks[] = 'Architecture non décrite pour : '.$withoutComponents->map(fn (Version $version): string => $version->displayCode().' · '.$version->name)->implode(', ').'.';
        }
        $risks === [] ? $section->paragraph('Aucun risque relevé sur les données saisies.') : $section->bullets($risks);

        return $section
            ->paragraph('Hypothèses :')
            ->bullets([
                'Les versions et dépendances sont celles saisies dans l’application ; elles seront vérifiées automatiquement si l’intégration GitLab est activée.',
                'La compatibilité s’appuie sur les dates de fin de support du référentiel des technologies ; un cycle absent du référentiel est indiqué « Inconnu ».',
            ]);
    }

    private function support(?string $name, ?string $version): ?string
    {
        return $this->supportOf($name, $version)['label'] ?? null;
    }

    /**
     * Support status of a technology version from the referential (EF-503), cached per name and version.
     *
     * @return array{status: SupportStatus, label: string}|null
     */
    private function supportOf(?string $name, ?string $version): ?array
    {
        if ($name === null || $version === null || trim($name) === '' || trim($version) === '') {
            return null;
        }

        $component = mb_strtolower($name);
        $key = $component.'|'.$version;

        if (! isset($this->supportCache[$key])) {
            $support = $this->resolveStackSupport->handle([$component => $version])[$component];
            $this->supportCache[$key] = [
                'status' => $support['status'],
                'label' => $this->supportLabel($support['status'], $support['technology']?->end_of_support?->format('d/m/Y')),
            ];
        }

        return $this->supportCache[$key];
    }

    private function supportLabel(SupportStatus $status, ?string $endOfSupport): string
    {
        return $status->label().($endOfSupport !== null ? ' (fin : '.$endOfSupport.')' : '');
    }

    private function componentLabel(Component $component): string
    {
        return sprintf('%s · %s (%s)', $component->displayCode(), $component->name, $component->version->displayCode());
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
