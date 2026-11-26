<?php

namespace App\Reports\Builders;

use App\Actions\Referential\ResolveStackSupport;
use App\Enums\ComponentType;
use App\Enums\SupportStatus;
use App\Models\Component;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * L3 — Cartographie technique: components of each application (integrations are not tracked), the database
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
                'components' => fn ($query) => $query->active()->with('repository')->orderBy('type')->orderBy('name'),
            ])
            ->orderBy('name')->orderBy('id')
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('architecture.overview', $this->overview($versions))
            ->set('components.table', $this->componentsTable($versions))
            ->set('integrations.table', (new TextSection)->paragraph('Les API et intégrations ne sont pas suivies dans l’application : les flux entre composants et avec les systèmes externes sont à confirmer auprès des équipes techniques.'))
            ->addPending('API et intégrations : non suivies dans l’application')
            ->set('databases.table', $this->databasesTable($versions))
            ->set('dependencies.table', $this->dependenciesTable($versions))
            ->set('architecture.risks', $this->risks($versions));
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function overview(Collection $versions): TextSection
    {
        $components = $versions->flatMap(fn (Version $version): Collection => $version->components);
        $byType = $components->countBy(fn (Component $component): string => $component->type->label())
            ->map(fn (int $count, string $label): string => $label.' : '.$count)
            ->values();

        $repositories = $versions->sum(fn (Version $version): int => $version->repositories->count());

        $section = (new TextSection)->paragraph(sprintf(
            '%d application%s, %d dépôt%s et %d composant%s dans le périmètre.',
            $versions->count(), $this->plural($versions->count()),
            $repositories, $this->plural($repositories),
            $components->count(), $this->plural($components->count()),
        ));

        if ($byType->isNotEmpty()) {
            $section->paragraph('Composants par type :')->bullets($byType->all());
        }

        return $section->paragraph('Source des données : saisie manuelle dans Satis Pilotage. L’intégration GitLab n’étant pas activée, les versions et les dépendances ne sont pas relues automatiquement dans les dépôts.');
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
            ->column('note', 'Note', required: false);

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
                    'note' => $component->description !== null ? Str::limit(trim((string) preg_replace('/\s+/u', ' ', $component->description)), 300, '…') : null,
                ]);
            }
        }

        return $table;
    }

    /**
     * Database components of each application, with their support status.
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
     */
    private function risks(Collection $versions): TextSection
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
        }

        $withoutComponents = $versions->filter(fn (Version $version): bool => $version->components->isEmpty());

        $section = new TextSection;
        $section->paragraph('Risques relevés :');
        $risks = array_values(array_unique($obsolete));
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

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
