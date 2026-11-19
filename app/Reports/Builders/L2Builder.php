<?php

namespace App\Reports\Builders;

use App\Enums\FeatureOperation;
use App\Enums\FeaturePresence;
use App\Enums\ImplementationStatus;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Enums\VerificationStatus;
use App\Enums\WorkflowStepKind;
use App\Models\Feature;
use App\Models\User;
use App\Models\Version;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Queries\ProductCatalog;
use App\Queries\WorkflowGraph;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\Rendering\WorkflowDiagramPng;
use App\Reports\ReportScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L2 — Référentiel fonctionnel consolidé: the product's catalog features, their state in each
 * customer version (presence and operation, README d'intégration), the workflows with their rules,
 * the gaps and the verification progress. A future perspective (Satis_SaaS) is left out.
 * The origin of each feature in the functional review (sheet, row) waits for the import of the
 * 937 observations: it stays "À confirmer".
 *
 * @phpstan-type ImplementationRow object{id: int, version_id: int, feature_id: int, status: string, deviation: ?string, verification_status: string, verified_by: ?int}
 */
final class L2Builder implements ReportBuilder
{
    public function __construct(private readonly WorkflowDiagramPng $diagramRenderer) {}

    private const TEXT_LIMIT = 400;

    private const GAP_LIST_LIMIT = 100;

    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();
        $catalog = ProductCatalog::load();
        $featureIds = $catalog->featureIds($product->id);

        $versions = Version::query()
            ->active()
            ->excludingFuture()
            ->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id))
            ->with('customer')
            ->orderBy('name')->orderBy('id')
            ->get()
            ->keyBy('id');

        $implementations = $this->featureImplementations($versions->keys()->all());
        $verifiers = User::query()->whereIn('id', $implementations->pluck('verified_by')->filter()->unique())->pluck('name', 'id');
        $features = Feature::query()->whereIn('id', $featureIds)->get(['id', 'standard_description'])->keyBy('id');

        $workflows = Workflow::query()
            ->active()
            ->where('product_id', $product->id)
            ->with(['steps' => fn ($query) => $query->active()->with([
                'feature',
                'businessRules' => fn ($query) => $query->whereNull('business_rules.archived_at')
                    ->whereHas('implementation', fn ($query) => $query->whereIn('version_id', $versions->keys())),
            ])])
            ->orderBy('name')
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        $content
            ->set('functional.scope', $this->scopeSection($catalog, $featureIds, $versions, $implementations, $workflows))
            ->set('features.table', $this->featuresTable($catalog, $featureIds, $features))
            ->set('feature_implementations.table', $this->implementationsTable($catalog, $featureIds, $versions, $implementations, $verifiers))
            ->set('workflows.table', $this->workflowsTable($catalog, $workflows))
            ->set('functional.gaps', $this->gaps($catalog, $versions, $implementations, $product->id))
            ->set('functional.source_references', $this->sourceReferences($versions));

        foreach ($workflows as $workflow) {
            $this->addDiagram($content, $workflow);
        }

        return $content;
    }

    /**
     * Active feature implementations of the versions, light rows (tens of thousands at full volume).
     *
     * @param  list<int>  $versionIds
     * @return Collection<int, ImplementationRow>
     */
    private function featureImplementations(array $versionIds): Collection
    {
        /** @var Collection<int, ImplementationRow> $rows */
        $rows = DB::table('implementations')
            ->whereIn('version_id', $versionIds)
            ->whereNotNull('feature_id')
            ->whereNull('archived_at')
            ->get(['id', 'version_id', 'feature_id', 'status', 'deviation', 'verification_status', 'verified_by']);

        return $rows;
    }

    /**
     * @param  list<int>  $featureIds
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, ImplementationRow>  $implementations
     * @param  Collection<int, Workflow>  $workflows
     */
    private function scopeSection(ProductCatalog $catalog, array $featureIds, Collection $versions, Collection $implementations, Collection $workflows): TextSection
    {
        $moduleCount = collect($featureIds)->flatMap(fn (int $featureId): array => $catalog->tree->featureAncestorModuleIds($featureId))->unique()->count();
        $steps = $workflows->sum(fn (Workflow $workflow): int => $workflow->steps->count());

        return (new TextSection)
            ->paragraph(sprintf(
                'Catalogue du produit : %d fonctionnalité%s réparties dans %d module%s et sous-module%s. Le catalogue est commun à toutes les versions ; chaque version n’en retient qu’une partie.',
                count($featureIds), $this->plural(count($featureIds)), $moduleCount, $this->plural($moduleCount), $this->plural($moduleCount),
            ))
            ->paragraph(sprintf(
                'Variantes clients : %d application%s (%s), %d rattachement%s de fonctionnalités.',
                $versions->count(), $this->plural($versions->count()),
                $versions->map(fn (Version $version): string => $version->displayCode())->implode(', ') ?: 'aucune',
                $implementations->count(), $this->plural($implementations->count()),
            ))
            ->paragraph('Méthode : chaque rattachement est évalué sur deux axes, la présence (présent, absent, inconnu, non applicable) et le fonctionnement (opérationnel, défaillant, partiel, non testé, non applicable), puis vérifié. Une fonctionnalité non rattachée est hors du périmètre du client ; « Absent » signifie attendue mais absente, et seul ce cas compte comme manque. Un écart se décrit par rapport à la description standard du catalogue, jamais par rapport à une autre version.')
            ->paragraph(sprintf('%d workflow%s décrit%s, %d étape%s au total. Les perspectives futures (ex. Satis_SaaS) sont exclues du périmètre.', $workflows->count(), $this->plural($workflows->count()), $this->plural($workflows->count()), $steps, $this->plural($steps)));
    }

    /**
     * @param  list<int>  $featureIds
     * @param  Collection<int, Feature>  $features
     */
    private function featuresTable(ProductCatalog $catalog, array $featureIds, Collection $features): TableSection
    {
        $table = (new TableSection('Catalogue des fonctionnalités'))
            ->column('feature_id', 'Identifiant')
            ->column('module', 'Module')
            ->column('submodule', 'Sous-module')
            ->column('label', 'Fonctionnalité')
            ->column('other_modules', 'Aussi dans', required: false)
            ->column('description', 'Description standard')
            ->column('source_sheet', 'Feuille source')
            ->column('source_row', 'Ligne source');

        foreach ($featureIds as $featureId) {
            $path = $catalog->modulePath($featureId);

            $table->row([
                'feature_id' => Feature::CODE_PREFIX.sprintf('%04d', $featureId),
                'module' => $path[0] ?? null,
                'submodule' => count($path) > 1 ? implode(' › ', array_slice($path, 1)) : 'Sans objet',
                'label' => $catalog->tree->features[$featureId]['name'],
                // A shared feature (RG-16) is listed once, under its main module.
                'other_modules' => collect($catalog->tree->otherModuleIds($featureId))
                    ->map(fn (int $moduleId): string => $catalog->tree->modules[$moduleId]['name'])
                    ->sort()->implode(', '),
                'description' => $this->plainText($features->get($featureId)?->standard_description),
                // Filled by the import of the functional review (937 observations), not available yet.
                'source_sheet' => null,
                'source_row' => null,
            ]);
        }

        return $table;
    }

    /**
     * @param  list<int>  $featureIds
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, ImplementationRow>  $implementations
     * @param  Collection<int, string>  $verifiers
     */
    private function implementationsTable(ProductCatalog $catalog, array $featureIds, Collection $versions, Collection $implementations, Collection $verifiers): TableSection
    {
        $table = (new TableSection('Variantes clients'))
            ->column('feature_id', 'Fonctionnalité')
            ->column('label', 'Libellé')
            ->column('application_id', 'Application')
            ->column('client_id', 'Client')
            ->column('presence_status', 'Présence')
            ->column('operational_status', 'Fonctionnement')
            ->column('deviation', 'Écart', required: false)
            ->column('verification_status', 'Vérification')
            ->column('verified_by', 'Vérifié par');

        $order = array_flip($featureIds);
        $versionOrder = array_flip($versions->keys()->all());
        $rows = $implementations
            ->filter(fn (object $row): bool => isset($order[$row->feature_id]))
            ->sortBy(fn (object $row): array => [$order[$row->feature_id], $versionOrder[$row->version_id]]);

        foreach ($rows as $row) {
            $version = $versions[$row->version_id];
            $status = ImplementationStatus::from($row->status);
            $verification = VerificationStatus::from($row->verification_status);

            $table->row([
                'feature_id' => Feature::CODE_PREFIX.sprintf('%04d', $row->feature_id),
                'label' => $catalog->tree->features[$row->feature_id]['name'],
                'application_id' => $version->displayCode(),
                'client_id' => $version->customer !== null
                    ? sprintf('%s · %s', $version->customer->displayCode(), $version->customer->name)
                    : 'Sans objet (version de base)',
                'presence_status' => $status->presence()->label(),
                'operational_status' => $status->operation()->label(),
                'deviation' => $this->plainText($row->deviation),
                'verification_status' => $verification->label(),
                'verified_by' => $verification === VerificationStatus::ToVerify ? null : ($verifiers[$row->verified_by] ?? null),
            ]);
        }

        return $table;
    }

    /**
     * Diagram of a workflow with its conditions (RG-17), after the workflows table.
     */
    private function addDiagram(ReportContent $content, Workflow $workflow): void
    {
        $layout = WorkflowGraph::for($workflow)->layout();
        if ($layout['nodes'] === []) {
            return;
        }

        $content->addFigure(
            'workflows.table',
            sprintf('Schéma du workflow %s · %s', $workflow->displayCode(), $workflow->name),
            $this->diagramRenderer->render($layout),
            round(min(16.0, $layout['width'] * 0.0265), 1),
        );
    }

    /**
     * @param  Collection<int, Workflow>  $workflows
     */
    private function workflowsTable(ProductCatalog $catalog, Collection $workflows): TableSection
    {
        $table = (new TableSection('Workflows'))
            ->column('workflow_id', 'Workflow')
            ->column('workflow', 'Intitulé')
            ->column('step', 'Étape')
            ->column('kind', 'Type', required: false)
            ->column('exits', 'Sorties (si / sinon)', required: false)
            ->column('actor', 'Acteur', required: false)
            ->column('feature', 'Fonctionnalité')
            ->column('business_rules', 'Règles métier', required: false);

        foreach ($workflows as $workflow) {
            $graph = WorkflowGraph::for($workflow);

            foreach ($workflow->steps as $step) {
                /** @var WorkflowStep $step */
                $table->row([
                    'workflow_id' => $workflow->displayCode(),
                    'workflow' => $workflow->name,
                    'step' => $step->position.'. '.$step->name,
                    'kind' => $step->kind->label(),
                    'exits' => $graph->exitsLabel($step->id),
                    'actor' => $step->actor,
                    // A decision or an end is not realized by a feature.
                    'feature' => match (true) {
                        $step->feature !== null => $step->feature->displayCode().' · '.$catalog->featureLabel($step->feature_id),
                        $step->kind !== WorkflowStepKind::Action => 'Sans objet',
                        default => null,
                    },
                    'business_rules' => $step->businessRules->sortBy('id')->map(fn ($rule): string => $rule->displayCode().' '.$rule->title)->implode("\n"),
                ]);
            }
        }

        return $table;
    }

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, ImplementationRow>  $implementations
     */
    private function gaps(ProductCatalog $catalog, Collection $versions, Collection $implementations, int $productId): TextSection
    {
        $section = new TextSection;
        $byVersion = $implementations->groupBy('version_id');

        $summaries = [];
        foreach ($versions as $version) {
            /** @var Collection<int, ImplementationRow> $rows */
            $rows = $byVersion->get($version->id, collect());
            $statuses = $rows->map(fn (object $row): ImplementationStatus => ImplementationStatus::from($row->status));
            $absent = $statuses->filter(fn (ImplementationStatus $status): bool => $status->isGap())->count();
            $defective = $statuses->filter(fn (ImplementationStatus $status): bool => $status->operation() === FeatureOperation::Defective)->count();
            $partial = $statuses->filter(fn (ImplementationStatus $status): bool => $status->operation() === FeatureOperation::Partial)->count();
            $unknown = $statuses->filter(fn (ImplementationStatus $status): bool => $status->presence() === FeaturePresence::Unknown)->count();
            $deviations = $rows->filter(fn (object $row): bool => trim((string) $row->deviation) !== '')->count();
            $verified = $rows->where('verification_status', VerificationStatus::Verified->value)->count();

            $summaries[] = sprintf(
                '%s · %s : %d absente%s, %d défaillante%s, %d partielle%s, %d de présence inconnue, %d écart%s décrit%s ; %d sur %d vérifiée%s.',
                $version->displayCode(), $version->name,
                $absent, $this->plural($absent),
                $defective, $this->plural($defective),
                $partial, $this->plural($partial),
                $unknown,
                $deviations, $this->plural($deviations), $this->plural($deviations),
                $verified, $rows->count(), $this->plural($verified),
            );
        }

        if ($summaries === []) {
            return $section->paragraph('Aucune application dans le périmètre.');
        }

        $section->paragraph('Par application (fonctionnalités rattachées) :')->bullets($summaries);

        $gaps = $implementations
            ->filter(fn (object $row): bool => ImplementationStatus::from($row->status)->isGap())
            ->sortBy(fn (object $row): array => [$row->feature_id, $row->version_id])
            ->values();

        if ($gaps->isNotEmpty()) {
            $section->paragraph(sprintf('Fonctionnalités attendues mais absentes (%d) :', $gaps->count()))
                ->bullets($gaps->take(self::GAP_LIST_LIMIT)->map(fn (object $row): string => sprintf(
                    '%s · %s — %s',
                    Feature::CODE_PREFIX.sprintf('%04d', $row->feature_id),
                    $catalog->featureLabel($row->feature_id),
                    $versions[$row->version_id]->displayCode(),
                )));

            if ($gaps->count() > self::GAP_LIST_LIMIT) {
                $section->paragraph(sprintf('… et %d autres, listées dans l’onglet « Variantes clients » de l’annexe XLSX (présence « Absent »).', $gaps->count() - self::GAP_LIST_LIMIT));
            }
        }

        $pendingRules = DB::table('business_rules')
            ->join('implementations', 'implementations.id', '=', 'business_rules.implementation_id')
            ->whereIn('implementations.version_id', $versions->keys())
            ->whereNull('business_rules.archived_at')
            ->where('business_rules.status', RuleStatus::Pending->value)
            ->count();

        return $section->paragraph(sprintf('Validation métier : %d règle%s métier en attente de validation.', $pendingRules, $this->plural($pendingRules)));
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function sourceReferences(Collection $versions): TextSection
    {
        $bySource = DB::table('business_rules')
            ->join('implementations', 'implementations.id', '=', 'business_rules.implementation_id')
            ->whereIn('implementations.version_id', $versions->keys())
            ->whereNull('business_rules.archived_at')
            ->groupBy('business_rules.source')
            ->pluck(DB::raw('count(*)'), 'business_rules.source');

        return (new TextSection)
            ->paragraph('Revue fonctionnelle d’origine : l’import des 937 observations est en attente du fichier source. La feuille et la ligne d’origine de chaque fonctionnalité restent donc à confirmer ; une observation importée ne sera jamais considérée comme validée.')
            ->paragraph('Origine des règles métier du périmètre :')
            ->bullets(array_map(
                fn (RuleSource $source): string => sprintf('%s : %d', $source->label(), (int) ($bySource[$source->value] ?? 0)),
                RuleSource::cases(),
            ));
    }

    /**
     * Markdown to one line of plain text, shortened for the tables.
     */
    private function plainText(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::markdown($markdown, ['html_input' => 'strip'])), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, self::TEXT_LIMIT, '…');
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
