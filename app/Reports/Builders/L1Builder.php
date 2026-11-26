<?php

namespace App\Reports\Builders;

use App\Actions\Referential\ResolveStackSupport;
use App\Enums\ComponentType;
use App\Enums\EnvironmentType;
use App\Enums\RepositoryRole;
use App\Enums\SupportStatus;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Models\Environment;
use App\Models\Repository;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use App\Support\VersionInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * L1 — Cartographie du patrimoine applicatif: one application per version, with its repositories
 * stacks, database components, owner and deputy, and its environments (releases and deployments
 * are not tracked). A future perspective (e.g. Satis_SaaS) is left
 * out of the inventory (SPEC-001, scenario 9). Missing data is left empty, shown as "À confirmer".
 */
final class L1Builder implements ReportBuilder
{
    public function __construct(private readonly ResolveStackSupport $resolveStackSupport) {}

    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();

        $all = Version::query()
            ->active()
            ->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id))
            ->with([
                'customer',
                'technicalOwner',
                'deputy',
                'repositories' => fn ($query) => $query->active()->orderBy('path'),
                'components' => fn ($query) => $query->active()->where('type', ComponentType::Database->value)->orderBy('name'),
                'environments' => fn ($query) => $query->active(),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        [$future, $versions] = $all->partition(fn (Version $version): bool => $version->isFuture());

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('applications.summary', $this->summary($versions, $future, $product->reference_version_id))
            ->set('applications.table', $this->applications($versions, $product->name))
            ->set('deployments.table', $this->environments($versions))
            ->set('applications.constraints', $this->constraints($versions))
            ->set('applications.pending_verifications', $this->pendingVerifications($versions));
    }

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, Version>  $future
     */
    private function summary(Collection $versions, Collection $future, ?int $referenceVersionId): TextSection
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

        $retired = $versions->filter(fn (Version $version): bool => $version->status === VersionStatus::Retired);
        if ($retired->isNotEmpty()) {
            $section->paragraph(sprintf('Dont %d application%s retirée%s, conservée%s pour l’historique.', $retired->count(), $this->plural($retired->count()), $this->plural($retired->count()), $this->plural($retired->count())));
        }

        if ($reference !== null) {
            $section->paragraph(sprintf('Version de référence du produit : %s · %s.', $reference->displayCode(), $reference->name));
        }

        $pivots = $versions->filter(fn (Version $version): bool => $version->is_historical_pivot);
        if ($pivots->isNotEmpty()) {
            $section->paragraph('Pivot historique (point de comparaison, pas une cible) : '.$pivots->map(fn (Version $version): string => $version->displayCode().' · '.$version->name)->implode(', ').'.');
        }

        if ($future->isNotEmpty()) {
            $section->paragraph('Hors inventaire de production (perspectives futures) : '.$future->map(fn (Version $version): string => $version->displayCode().' · '.$version->name)->implode(', ').'.');
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
            ->column('status', 'Cycle de vie', required: false)
            ->column('backend', 'Backend')
            ->column('frontend', 'Frontend')
            ->column('database_engine', 'Base de données')
            ->column('repository_url', 'Dépôt', link: true)
            ->column('technical_owner', 'Responsable technique')
            ->column('deputy', 'Suppléant');

        foreach ($versions as $version) {
            $table->row([
                'application_id' => $version->displayCode(),
                'name' => $version->name,
                'client_id' => $version->customer !== null
                    ? sprintf('%s · %s', $version->customer->displayCode(), $version->customer->name)
                    : 'Sans objet (version de base)',
                'product' => $productName,
                'status' => $version->status->label(),
                // Stacks only: the technologies and their versions.
                'backend' => VersionInventory::stack($version, RepositoryRole::Backend),
                'frontend' => VersionInventory::stack($version, RepositoryRole::Frontend),
                'database_engine' => VersionInventory::databases($version),
                'repository_url' => implode("\n", VersionInventory::repositoryUrls($version)),
                'technical_owner' => $version->technicalOwner?->name,
                'deputy' => $version->deputy?->name,
            ]);
        }

        return $table;
    }

    /**
     * Environments of each application: kind, name and notes. Releases and deployments are not tracked.
     *
     * @param  Collection<int, Version>  $versions
     */
    private function environments(Collection $versions): TableSection
    {
        $table = (new TableSection('Environnements'))
            ->column('application_id', 'Application')
            ->column('environment', 'Environnement')
            ->column('type', 'Type')
            ->column('notes', 'Remarques', required: false);

        foreach ($versions as $version) {
            foreach ($version->environments as $environment) {
                $table->row([
                    'application_id' => $version->displayCode().' · '.$version->name,
                    'environment' => $environment->displayCode().' · '.$environment->name,
                    'type' => $environment->type->label(),
                    'notes' => $environment->notes !== null ? Str::limit(trim((string) preg_replace('/\s+/u', ' ', $environment->notes)), 300, '…') : null,
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

            if ($version->verification_status === VerificationStatus::ToVerify) {
                $items[] = $label.' : fiche à vérifier.';
            }

            if ($version->technicalOwner === null) {
                $items[] = $label.' : responsable technique non désigné.';
            }

            if ($version->deputy === null) {
                $items[] = $label.' : suppléant non désigné.';
            }

            if ($version->environments->doesntContain(fn (Environment $environment): bool => $environment->type === EnvironmentType::Production)) {
                $items[] = $label.' : aucun environnement de production enregistré.';
            }

            if ($version->repositories->isEmpty()) {
                $items[] = $label.' : aucun dépôt enregistré.';
            }

            $withoutStack = $version->repositories->filter(fn (Repository $repository): bool => empty($repository->stack));
            if ($withoutStack->isNotEmpty()) {
                $items[] = $label.' : stack non renseignée pour '.$withoutStack->map(fn (Repository $repository): string => $repository->displayCode())->implode(', ').'.';
            }

            if ($version->components->isEmpty()) {
                $items[] = $label.' : base de données non renseignée (composant « base de données » dans l’architecture).';
            }
        }

        $section = new TextSection;

        return $items === []
            ? $section->paragraph('Aucune vérification en attente sur ce périmètre.')
            : $section->bullets($items);
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
