<?php

namespace App\Reports\Builders;

use App\Enums\ActionStatus;
use App\Enums\ApprovalStatus;
use App\Enums\DecisionStatus;
use App\Enums\FindingStatus;
use App\Enums\ImplementationStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditPeriod;
use App\Models\DiagnosticFinding;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\RefactoringStrategy;
use App\Models\RemediationAction;
use App\Models\Repository;
use App\Models\StrategyDecision;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use App\Support\Effort;
use Illuminate\Support\Collection;

/**
 * L6 — Tableau de bord DTI: a dated snapshot of the audit for management. Every indicator shows
 * its denominator and how much was actually measured (README d'intégration). Action plan (L8)
 * and decision register (L7) are not held by the application yet: their indicators stay
 * "À confirmer" and nothing is inferred in their place.
 */
final class L6Builder implements ReportBuilder
{
    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();
        $today = now();

        $baseQuery = Version::query()->active()->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id));
        $versions = (clone $baseQuery)->excludingFuture()->with('customer')->orderBy('name')->get()->keyBy('id');
        $futureCount = (clone $baseQuery)->where('status', 'future')->count();
        $versionIds = $versions->keys()->all();

        $repositories = Repository::query()->active()->whereIn('version_id', $versionIds)
            ->withExists(['diagnosticCampaigns as analyzed' => fn ($query) => $query->whereNull('archived_at')->whereHas('toolRuns')])
            ->get();

        $featureImplementations = Implementation::query()->active()->whereIn('version_id', $versionIds)->whereNotNull('feature_id')
            ->get(['id', 'version_id', 'status', 'verification_status']);
        $gaps = Implementation::query()->active()->whereIn('version_id', $versionIds)->where('status', ImplementationStatus::Absent->value)->count();

        $issues = Issue::query()->active()
            ->whereHas('implementations', fn ($query) => $query->whereIn('version_id', $versionIds))
            ->with('owner')
            ->get();
        $open = $issues->filter(fn (Issue $issue): bool => $issue->status->isOpen());

        $findings = DiagnosticFinding::query()
            ->whereHas('campaign', fn ($query) => $query->whereNull('archived_at')->whereIn('repository_id', $repositories->pluck('id')))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $actions = RemediationAction::query()->where('product_id', $product->id)->whereNull('archived_at')
            ->where('status', '!=', ActionStatus::Cancelled->value)
            ->when($customer !== null, fn ($query) => $query->whereHas('issue.implementations', fn ($query) => $query->whereIn('version_id', $versionIds)))
            ->get();

        $periods = AuditPeriod::query()->active()->where('product_id', $product->id)
            ->where('starts_on', '<=', $today->toDateString())->orderBy('starts_on')->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('dashboard.executive_summary', $this->indicators($today->format('d/m/Y'), $versions, $futureCount, $repositories, $featureImplementations, $open, $issues, $findings, $periods, $actions))
            ->set('dashboard.coverage', $this->coverage($versions, $repositories, $featureImplementations, $findings))
            ->set('dashboard.risks', $this->risks($open, $gaps))
            ->set('dashboard.actions', $this->actions($issues, $actions))
            ->set('dashboard.capacity', $this->capacity($periods, $customer !== null))
            ->set('dashboard.pending_decisions', $this->pendingDecisions($open, $findings, $product->id))
            ->set('dashboard.methodology', $this->methodology($today->format('d/m/Y'), $futureCount));
    }

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, Repository>  $repositories
     * @param  Collection<int, Implementation>  $features
     * @param  Collection<int, Issue>  $open
     * @param  Collection<int, Issue>  $issues
     * @param  Collection<string, int>  $findings
     * @param  Collection<int, AuditPeriod>  $periods
     * @param  Collection<int, RemediationAction>  $actions
     */
    private function indicators(string $date, Collection $versions, int $futureCount, Collection $repositories, Collection $features, Collection $open, Collection $issues, Collection $findings, Collection $periods, Collection $actions): TableSection
    {
        $analyzed = $repositories->where('analyzed', true)->count();
        $reviewed = $features->filter(fn (Implementation $item): bool => $item->status !== ImplementationStatus::Unknown)->count();
        $blocking = $open->where('severity', IssueSeverity::Blocking)->count();
        $withActual = $periods->whereNotNull('hours_actual');

        $table = (new TableSection('Indicateurs clés'))
            ->column('indicator', 'Indicateur')
            ->column('value', 'Valeur')
            ->column('basis', 'Dénominateur / couverture', required: false)
            ->column('source', 'Source', required: false);

        return $table
            ->row(['indicator' => 'Date de la photographie', 'value' => $date, 'source' => 'Date de génération'])
            ->row([
                'indicator' => 'Applications du périmètre', 'value' => $versions->count(),
                'basis' => $futureCount > 0 ? sprintf('hors %d perspective%s future%s (ex. Satis_SaaS)', $futureCount, $this->plural($futureCount), $this->plural($futureCount)) : 'versions actives, hors perspectives futures',
                'source' => 'Versions (L1)',
            ])
            ->row(['indicator' => 'Dépôts inventoriés', 'value' => $repositories->count(), 'basis' => 'dépôts actifs des applications du périmètre', 'source' => 'Dépôts (L1)'])
            ->row([
                'indicator' => 'Dépôts analysés', 'value' => $this->ratio($analyzed, $repositories->count()),
                'basis' => 'au moins une campagne avec des résultats d’outils importés', 'source' => 'Diagnostics (L4)',
            ])
            ->row([
                'indicator' => 'Fonctionnalités évaluées', 'value' => $this->ratio($reviewed, $features->count()),
                'basis' => sprintf('rattachements de fonctionnalités dont l’état est connu ; %d vérifié%s', $verified = $features->where('verification_status', VerificationStatus::Verified)->count(), $this->plural($verified)),
                'source' => 'Référentiel fonctionnel (L2)',
            ])
            ->row([
                'indicator' => 'Constats critiques à traiter', 'value' => $blocking,
                'basis' => sprintf('problèmes bloquants, sur %d problème%s à traiter et %d recensé%s', $open->count(), $this->plural($open->count()), $issues->count(), $this->plural($issues->count())),
                'source' => 'Registre de dette (L5)',
            ])
            ->row([
                'indicator' => 'Résultats d’outils à vérifier', 'value' => (int) ($findings[FindingStatus::ToVerify->value] ?? 0),
                'basis' => sprintf('sur %d résultat%s importé%s', $total = (int) $findings->sum(), $this->plural($total), $this->plural($total)),
                'source' => 'Diagnostics (L4)',
            ])
            ->row([
                'indicator' => 'Actions ouvertes', 'value' => $actions->filter(fn (RemediationAction $action): bool => $action->status->isOpen())->count(),
                'basis' => sprintf('à faire, en cours ou bloquées ; %d en retard', $actions->filter(fn (RemediationAction $action): bool => $action->isLate())->count()),
                'source' => 'Plan d’actions (L8)',
            ])
            ->row([
                'indicator' => 'Actions fermées', 'value' => $actions->where('status', ActionStatus::Done)->count(),
                'basis' => sprintf('terminées avec leur preuve de test, sur %d action%s au plan (annulées exclues)', $actions->count(), $this->plural($actions->count())),
                'source' => 'Plan d’actions (L8)',
            ])
            ->row([
                'indicator' => 'Heures d’audit prévues', 'value' => $periods->isEmpty() ? null : $this->hours($periods->sum(fn (AuditPeriod $period): float => (float) $period->hours_planned)),
                'basis' => $periods->isEmpty() ? 'aucune période saisie' : sprintf('%d période%s commencée%s à cette date', $periods->count(), $this->plural($periods->count()), $this->plural($periods->count())),
                'source' => 'Suivi de l’audit',
            ])
            ->row([
                'indicator' => 'Heures d’audit réalisées', 'value' => $withActual->isEmpty() ? null : $this->hours($withActual->sum(fn (AuditPeriod $period): float => (float) $period->hours_actual)),
                'basis' => $withActual->isEmpty() ? 'aucune période renseignée' : sprintf(
                    'pour %s prévues sur les mêmes périodes%s',
                    $this->hours($withActual->sum(fn (AuditPeriod $period): float => (float) $period->hours_planned)),
                    ($missing = $periods->count() - $withActual->count()) > 0 ? sprintf(' ; %d période%s sans réalisé', $missing, $this->plural($missing)) : '',
                ),
                'source' => 'Suivi de l’audit',
            ]);
    }

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, Repository>  $repositories
     * @param  Collection<int, Implementation>  $features
     * @param  Collection<string, int>  $findings
     */
    private function coverage(Collection $versions, Collection $repositories, Collection $features, Collection $findings): TextSection
    {
        $section = new TextSection;
        if ($versions->isEmpty()) {
            return $section->paragraph('Aucune application dans le périmètre.');
        }

        $section->paragraph('Par application : dépôts analysés sur inventoriés, fonctionnalités évaluées sur rattachées.')->bullets($versions->map(function (Version $version) use ($repositories, $features): string {
            $repos = $repositories->where('version_id', $version->id);
            $items = $features->where('version_id', $version->id);

            return sprintf(
                '%s · %s — dépôts %s — fonctionnalités %s',
                $version->displayCode(), $version->name,
                $this->ratio($repos->where('analyzed', true)->count(), $repos->count()),
                $this->ratio($items->filter(fn (Implementation $item): bool => $item->status !== ImplementationStatus::Unknown)->count(), $items->count()),
            );
        })->values()->all());

        $total = (int) $findings->sum();
        if ($total > 0) {
            $triaged = $total - (int) ($findings[FindingStatus::ToVerify->value] ?? 0);
            $section->paragraph(sprintf(
                'Résultats d’outils : %s vérifiés (%d retenus dans des problèmes, %d faux positifs).',
                $this->ratio($triaged, $total), (int) ($findings[FindingStatus::Retained->value] ?? 0), (int) ($findings[FindingStatus::FalsePositive->value] ?? 0),
            ));
        } else {
            $section->paragraph('Aucun résultat d’outil importé : la couverture des diagnostics est nulle à cette date.');
        }

        return $section;
    }

    /**
     * @param  Collection<int, Issue>  $open
     */
    private function risks(Collection $open, int $gaps): TextSection
    {
        $section = (new TextSection)->paragraph(sprintf('%d problème%s à traiter (identifiés, qualifiés ou planifiés).', $open->count(), $this->plural($open->count())));

        if ($open->isNotEmpty()) {
            $section->bullets(array_map(function (IssueSeverity $severity) use ($open): string {
                $group = $open->where('severity', $severity);
                $effort = $this->effort($group);

                return sprintf('%s : %d%s', $severity->label(), $group->count(), $effort !== null ? ' — effort estimé '.$effort : '');
            }, IssueSeverity::cases()));

            $section->paragraph('Par type : '.$open->groupBy(fn (Issue $issue): string => $issue->type->label())
                ->map(fn (Collection $group, string $label): string => $label.' '.$group->count())->sort()->implode(', ').'.');

            $estimated = $open->whereNotNull('effort_min_days');
            $section->paragraph(sprintf(
                'Effort total estimé : %s, sur %d problème%s estimé%s ; %d sans estimation.',
                $this->effort($open) ?? '0 j/h', $estimated->count(), $this->plural($estimated->count()), $this->plural($estimated->count()), $open->count() - $estimated->count(),
            ));
        }

        return $section->paragraph(sprintf('Écarts fonctionnels : %d élément%s attendu%s mais absent%s (état « absent »).', $gaps, $this->plural($gaps), $this->plural($gaps), $this->plural($gaps)));
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @param  Collection<int, RemediationAction>  $actions
     */
    private function actions(Collection $issues, Collection $actions): TextSection
    {
        $section = new TextSection;
        if ($actions->isEmpty()) {
            $section->paragraph('Aucune action au plan d’actions (L8) pour ce périmètre.');
        } else {
            $section->paragraph('Plan d’actions (L8) :')->bullets(array_values(array_filter(array_map(
                fn (ActionStatus $status): ?string => ($count = $actions->where('status', $status)->count()) > 0 ? sprintf('%s : %d', $status->label(), $count) : null,
                ActionStatus::cases(),
            ))));
            $late = $actions->filter(fn (RemediationAction $action): bool => $action->isLate());
            if ($late->isNotEmpty()) {
                $section->paragraph(sprintf('%d action%s en retard : %s.', $late->count(), $this->plural($late->count()), $late->take(10)->map(fn (RemediationAction $action): string => $action->displayCode())->implode(', ')));
            }
        }

        $section->paragraph('Avancement du traitement des problèmes :');

        $done = $issues->filter(fn (Issue $issue): bool => in_array($issue->status, [IssueStatus::Treated, IssueStatus::Verified], true))->count();
        $considered = $issues->where('status', '!=', IssueStatus::Dropped)->count();
        $section->bullets(array_values(array_filter(array_map(
            fn (IssueStatus $status): ?string => ($count = $issues->where('status', $status)->count()) > 0 ? sprintf('%s : %d', $status->label(), $count) : null,
            IssueStatus::cases(),
        ))));

        $planned = $issues->where('status', IssueStatus::Planned);

        return $section
            ->paragraph(sprintf('Problèmes traités ou vérifiés : %s (abandonnés exclus).', $this->ratio($done, $considered)))
            ->paragraph(sprintf('Problèmes planifiés : %d, dont %d sans responsable.', $planned->count(), $planned->whereNull('owner_id')->count()));
    }

    /**
     * @param  Collection<int, AuditPeriod>  $periods
     */
    private function capacity(Collection $periods, bool $customerFilter): TextSection
    {
        $section = new TextSection;
        if ($periods->isEmpty()) {
            return $section->paragraph('Aucune période saisie dans le suivi de l’audit : capacité et interruptions à confirmer.');
        }

        $withActual = $periods->whereNotNull('hours_actual');
        $actual = $withActual->sum(fn (AuditPeriod $period): float => (float) $period->hours_actual);
        $plannedSame = $withActual->sum(fn (AuditPeriod $period): float => (float) $period->hours_planned);
        $support = $periods->whereNotNull('support_hours');
        $supportHours = $support->sum(fn (AuditPeriod $period): float => (float) $period->support_hours);

        if ($withActual->isNotEmpty()) {
            $section->paragraph(sprintf(
                'Réalisé : %s pour %s prévues sur les périodes renseignées (%s).',
                $this->hours($actual), $this->hours($plannedSame), $plannedSame > 0 ? number_format($actual * 100 / $plannedSame, 0, ',', ' ').' %' : 'sans objet',
            ));
        } else {
            $section->paragraph('Aucune période n’a encore d’heures réalisées.');
        }

        if ($support->isNotEmpty()) {
            $section->paragraph(sprintf(
                'Interruptions de support : %s%s, renseignées sur %d période%s sur %d.',
                $this->hours($supportHours),
                ($count = $support->whereNotNull('support_interruptions')->sum('support_interruptions')) > 0 ? sprintf(' en %d interruption%s', $count, $this->plural($count)) : '',
                $support->count(), $this->plural($support->count()), $periods->count(),
            ));
        } else {
            $section->paragraph('Interruptions de support : non renseignées.');
        }

        $section->paragraph('Dernières périodes :')->bullets($periods->sortByDesc('starts_on')->take(8)->map(fn (AuditPeriod $period): string => sprintf(
            '%s — prévu %s, réalisé %s, support %s',
            $period->label(), $this->hours((float) $period->hours_planned),
            $period->hours_actual !== null ? $this->hours((float) $period->hours_actual) : 'à confirmer',
            $period->support_hours !== null ? $this->hours((float) $period->support_hours) : 'non renseigné',
        ))->values()->all());

        if ($customerFilter) {
            $section->paragraph('Les heures sont suivies par produit : elles ne sont pas filtrées par client.');
        }

        return $section;
    }

    /**
     * @param  Collection<int, Issue>  $open
     * @param  Collection<string, int>  $findings
     */
    private function pendingDecisions(Collection $open, Collection $findings, int $productId): TextSection
    {
        $section = new TextSection;

        $decisions = StrategyDecision::query()->whereNull('archived_at')->where('status', DecisionStatus::Proposed->value)
            ->whereHas('strategy', fn ($query) => $query->whereNull('archived_at')->where('product_id', $productId))
            ->with('strategy')->orderBy('id')->get();
        $inReview = RefactoringStrategy::query()->whereNull('archived_at')->where('product_id', $productId)
            ->where('approval_status', ApprovalStatus::InReview->value)->orderBy('id')->get();

        if ($decisions->isEmpty() && $inReview->isEmpty()) {
            $section->paragraph('Aucune décision en attente dans les stratégies de refactoring (L7).');
        } else {
            $section->paragraph('Stratégies de refactoring (L7) :')->bullets([
                ...$inReview->map(fn (RefactoringStrategy $strategy): string => sprintf('%s · %s : en revue, à valider ou rejeter', $strategy->displayCode(), $strategy->title))->all(),
                ...$decisions->map(fn (StrategyDecision $decision): string => sprintf('%s · %s (%s) : décision en attente', $decision->displayCode(), $decision->title, $decision->strategy->displayCode()))->all(),
            ]);
        }

        $section->paragraph('Points à arbitrer relevés dans les données :');

        $qualified = $open->where('status', IssueStatus::Qualified);
        $blockingWithoutOwner = $open->where('severity', IssueSeverity::Blocking)->whereNull('owner_id');
        $toVerify = (int) ($findings[FindingStatus::ToVerify->value] ?? 0);

        $points = [];
        if ($qualified->isNotEmpty()) {
            $points[] = sprintf('%d problème%s qualifié%s à planifier ou abandonner : %s', $qualified->count(), $this->plural($qualified->count()), $this->plural($qualified->count()),
                $qualified->take(10)->map(fn (Issue $issue): string => $issue->displayCode())->implode(', ').($qualified->count() > 10 ? '…' : ''));
        }
        if ($blockingWithoutOwner->isNotEmpty()) {
            $points[] = sprintf('%d problème%s bloquant%s sans responsable : %s', $blockingWithoutOwner->count(), $this->plural($blockingWithoutOwner->count()), $this->plural($blockingWithoutOwner->count()),
                $blockingWithoutOwner->take(10)->map(fn (Issue $issue): string => $issue->displayCode())->implode(', ').($blockingWithoutOwner->count() > 10 ? '…' : ''));
        }
        if ($toVerify > 0) {
            $points[] = sprintf('%d résultat%s d’outils à vérifier avant de conclure les diagnostics', $toVerify, $this->plural($toVerify));
        }

        return $points === [] ? $section->paragraph('Aucun point à arbitrer relevé.') : $section->bullets($points);
    }

    private function methodology(string $date, int $futureCount): TextSection
    {
        return (new TextSection)
            ->paragraph("Photographie au {$date}, calculée depuis les données de l’application au moment de la génération ; elle n’est pas mise à jour ensuite.")
            ->bullets([
                'Applications : versions actives du produit (et du client choisi), hors perspectives futures'.($futureCount > 0 ? sprintf(' (%d exclue%s)', $futureCount, $this->plural($futureCount)) : '').'. Les éléments archivés sont exclus partout.',
                'Dépôt analysé : au moins une campagne de diagnostic active avec des résultats d’outils importés. Les outils sont exécutés hors de la plateforme.',
                'Fonctionnalité évaluée : rattachement d’une fonctionnalité à une application dont l’état n’est plus « inconnu ». Le dénominateur est le nombre de rattachements, une fonctionnalité pouvant être présente dans plusieurs applications.',
                'Constat critique : problème bloquant encore à traiter (identifié, qualifié ou planifié).',
                'Heures : saisies par période dans le suivi de l’audit ; seules les périodes commencées à la date de la photographie sont comptées, et le réalisé est comparé au prévu des mêmes périodes.',
                'Actions : celles du plan d’actions (L8), annulées et archivées exclues ; avec un client, celles qui traitent un problème de ses versions. Une action fermée est terminée avec sa preuve de test.',
                'Décisions : décisions « en attente » et stratégies « en revue » des stratégies de refactoring (L7) du produit, puis points à arbitrer tirés des données.',
            ]);
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function effort(Collection $issues): ?string
    {
        $estimated = $issues->whereNotNull('effort_min_days');

        return $estimated->isEmpty() ? null : Effort::format(
            $estimated->sum(fn (Issue $issue): float => (float) $issue->effort_min_days),
            $estimated->sum(fn (Issue $issue): float => (float) ($issue->effort_max_days ?? $issue->effort_min_days)),
        );
    }

    private function ratio(int $part, int $total): string
    {
        return $total === 0 ? '0 / 0' : sprintf('%d / %d (%s %%)', $part, $total, number_format($part * 100 / $total, 0, ',', ' '));
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', ' '), '0'), ',').' h';
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
