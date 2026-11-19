<?php

namespace App\Reports\Builders;

use App\Actions\Referential\ResolveStackSupport;
use App\Enums\EnvironmentType;
use App\Enums\RepositoryRole;
use App\Enums\SupportStatus;
use App\Models\Environment;
use App\Models\Repository;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use Illuminate\Support\Collection;

/**
 * L1 — Cartographie du patrimoine applicatif: one application per version, with its repositories
 * and deployments. Data the application does not hold yet is left empty, shown as "À confirmer".
 */
final class L1Builder implements ReportBuilder
{
    public function __construct(private readonly ResolveStackSupport $resolveStackSupport) {}

    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();

        $versions = Version::query()
            ->active()
            ->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id))
            ->with([
                'customer',
                'repositories' => fn ($query) => $query->active()->orderBy('path'),
                'environments' => fn ($query) => $query->active()->with('repository')->orderBy('type')->orderBy('name'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('applications.summary', $this->summary($versions, $product->reference_version_id))
            ->set('applications.table', $this->applications($versions, $product->name))
            ->set('deployments.table', $this->deployments($versions))
            ->set('applications.constraints', $this->constraints($versions))
            ->set('applications.pending_verifications', $this->pendingVerifications($versions));
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function summary(Collection $versions, ?int $referenceVersionId): TextSection
    {
        $baselines = $versions->filter(fn (Version $version): bool => $version->isBaseline())->count();
        $repositories = $versions->sum(fn (Version $version): int => $version->repositories->count());
        $environments = $versions->sum(fn (Version $version): int => $version->environments->count());
        $reference = $versions->firstWhere('id', $referenceVersionId);

        $section = (new TextSection)->paragraph(sprintf(
            '%d application%s dans le périmètre (%d version%s client, %d version%s de base sans client), %d dépôt%s et %d environnement%s enregistrés.',
            $versions->count(), $this->plural($versions->count()),
            $versions->count() - $baselines, $this->plural($versions->count() - $baselines),
            $baselines, $this->plural($baselines),
            $repositories, $this->plural($repositories),
            $environments, $this->plural($environments),
        ));

        if ($reference !== null) {
            $section->paragraph(sprintf('Version de référence du produit : %s · %s.', $reference->displayCode(), $reference->name));
        }

        return $section->paragraph('Une application correspond à une version déployée du produit (implémentation client). Les identifiants APP-, CLI-, DEP- et ENV- sont stables d’un export à l’autre.');
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function applications(Collection $versions, string $productName): TableSection
    {
        $table = (new TableSection('Applications'))
            ->column('application_id', 'Identifiant')
            ->column('name', 'Application')
            ->column('client_id', 'Client')
            ->column('product', 'Produit')
            ->column('status', 'État', required: false)
            ->column('backend', 'Backend')
            ->column('frontend', 'Frontend')
            ->column('database_engine', 'Base de données')
            ->column('repository_url', 'Dépôt')
            ->column('technical_owner', 'Responsable technique')
            ->column('verification_status', 'Statut de vérification');

        foreach ($versions as $version) {
            $table->row([
                'application_id' => $version->displayCode(),
                'name' => $version->name,
                'client_id' => $version->customer !== null
                    ? sprintf('%s · %s', $version->customer->displayCode(), $version->customer->name)
                    : 'Sans objet (version de base)',
                'product' => $productName,
                'status' => $version->status->label(),
                'backend' => $this->repositoriesWithRole($version, RepositoryRole::Backend),
                'frontend' => $this->repositoriesWithRole($version, RepositoryRole::Frontend),
                'database_engine' => $this->databases($version->environments),
                'repository_url' => $version->repositories->pluck('web_url')->filter()->implode("\n"),
                'technical_owner' => null,
                'verification_status' => null,
            ]);
        }

        return $table;
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function deployments(Collection $versions): TableSection
    {
        $table = (new TableSection('Déploiements'))
            ->column('application_id', 'Application')
            ->column('environment', 'Environnement')
            ->column('repository', 'Dépôt')
            ->column('deployed_commit', 'Commit ou tag déployé')
            ->column('database', 'Base de données')
            ->column('php_version', 'PHP', required: false)
            ->column('url', 'URL', required: false)
            ->column('verification_status', 'Statut de vérification');

        foreach ($versions as $version) {
            foreach ($version->environments as $environment) {
                $table->row([
                    'application_id' => $version->displayCode(),
                    'environment' => sprintf('%s · %s (%s)', $environment->displayCode(), $environment->name, $environment->type->label()),
                    'repository' => $environment->repository !== null
                        ? sprintf('%s · %s', $environment->repository->displayCode(), $environment->repository->path)
                        : null,
                    'deployed_commit' => $environment->deployed_ref,
                    'database' => $this->database($environment),
                    'php_version' => $environment->php_version,
                    'url' => $environment->url,
                    'verification_status' => null,
                ]);
            }
        }

        return $table;
    }

    /**
     * Technologies out of support or ending support, from the repositories' stacks (EF-503).
     *
     * @param  Collection<int, Version>  $versions
     */
    private function constraints(Collection $versions): TextSection
    {
        $items = [];
        $unknown = 0;

        foreach ($versions as $version) {
            foreach ($version->repositories as $repository) {
                foreach ($this->resolveStackSupport->handle($repository->stack ?? []) as $component => $support) {
                    if ($support['status'] === SupportStatus::Unknown) {
                        $unknown++;
                    }

                    if (in_array($support['status'], [SupportStatus::OutOfSupport, SupportStatus::EndingSoon], true)) {
                        $items[] = sprintf(
                            '%s · %s — %s · %s : %s %s, %s%s.',
                            $version->displayCode(), $version->name,
                            $repository->displayCode(), $repository->path,
                            $component, $support['version'],
                            mb_strtolower($support['status']->label()),
                            $support['technology']?->end_of_support !== null ? ' (fin de support : '.$support['technology']->end_of_support->format('d/m/Y').')' : '',
                        );
                    }
                }
            }
        }

        $section = new TextSection;
        if ($items === []) {
            $section->paragraph('Aucune technologie hors support ou en fin de support relevée dans les stacks renseignées.');
        } else {
            $section->paragraph('Technologies hors support ou dont le support se termine dans les six mois :')->bullets($items);
        }

        if ($unknown > 0) {
            $section->paragraph(sprintf('%d composant%s de stack sans date de fin de support connue dans le référentiel des technologies.', $unknown, $this->plural($unknown)));
        }

        return $section;
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function pendingVerifications(Collection $versions): TextSection
    {
        $items = [];

        foreach ($versions as $version) {
            $label = $version->displayCode().' · '.$version->name;

            if ($version->repositories->isEmpty()) {
                $items[] = $label.' : aucun dépôt enregistré.';
            }

            $withoutStack = $version->repositories->filter(fn (Repository $repository): bool => empty($repository->stack));
            if ($withoutStack->isNotEmpty()) {
                $items[] = $label.' : stack non renseignée pour '.$withoutStack->map(fn (Repository $repository): string => $repository->displayCode())->implode(', ').'.';
            }

            if ($version->environments->doesntContain(fn (Environment $environment): bool => $environment->type === EnvironmentType::Production)) {
                $items[] = $label.' : aucun environnement de production enregistré.';
            }

            $withoutRef = $version->environments->filter(fn (Environment $environment): bool => blank($environment->deployed_ref));
            if ($withoutRef->isNotEmpty()) {
                $items[] = $label.' : commit déployé inconnu pour '.$withoutRef->map(fn (Environment $environment): string => $environment->displayCode())->implode(', ').'.';
            }
        }

        $section = new TextSection;
        if ($items !== []) {
            $section->bullets($items);
        }

        return $section->paragraph('Le responsable technique et le statut de vérification ne sont pas encore gérés dans l’application : ils restent à confirmer pour chaque application et chaque déploiement.');
    }

    private function repositoriesWithRole(Version $version, RepositoryRole $role): string
    {
        return $version->repositories
            ->filter(fn (Repository $repository): bool => $repository->role === $role)
            ->map(function (Repository $repository): string {
                $stack = collect($repository->stack ?? [])
                    ->sortKeys()
                    ->map(fn (string $version, string $component): string => $component.' '.$version)
                    ->implode(', ');

                return $repository->displayCode().' · '.$repository->path.($stack !== '' ? ' ('.$stack.')' : '');
            })
            ->implode("\n");
    }

    /**
     * @param  Collection<int, Environment>  $environments
     */
    private function databases(Collection $environments): string
    {
        $production = $environments->filter(fn (Environment $environment): bool => $environment->type === EnvironmentType::Production);

        return ($production->isNotEmpty() ? $production : $environments)
            ->map(fn (Environment $environment): ?string => $this->database($environment))
            ->filter()
            ->unique()
            ->implode("\n");
    }

    private function database(Environment $environment): ?string
    {
        if (blank($environment->database_engine)) {
            return null;
        }

        return trim($environment->database_engine.' '.$environment->database_version);
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
