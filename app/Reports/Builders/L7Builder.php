<?php

namespace App\Reports\Builders;

use App\Enums\ApprovalStatus;
use App\Enums\DecisionStatus;
use App\Enums\IssueSeverity;
use App\Models\Issue;
use App\Models\RefactoringStrategy;
use App\Models\StrategyDecision;
use App\Models\StrategyOption;
use App\Models\StrategyPhase;
use App\Models\StrategyRisk;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use App\Support\Effort;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * L7 — Stratégie de refactoring: the strategy as people wrote it, with its approval status. It is
 * a decision document, not an architecture generated from metrics (README d'intégration): the
 * builder only lays out what was written, adds the factual baseline of the audits, and lists the
 * parts that are still unwritten.
 */
final class L7Builder implements ReportBuilder
{
    private const TEXT_LIMIT = 400;

    public function build(ReportScope $scope): ReportContent
    {
        $strategy = $scope->strategy();
        if ($strategy === null) {
            throw new InvalidArgumentException('L7 se génère pour une stratégie de refactoring.');
        }

        $strategy->load([
            'product', 'approver', 'issues',
            'options' => fn ($query) => $query->whereNull('archived_at'),
            'phases' => fn ($query) => $query->whereNull('archived_at'),
            'risks' => fn ($query) => $query->whereNull('archived_at')->with('owner'),
            'decisions' => fn ($query) => $query->whereNull('archived_at')->with('option', 'decider'),
        ]);

        $content = new ReportContent(
            product: sprintf('%s (%s)', $strategy->product->name, $strategy->product->code),
            client: 'Tous les clients',
            approver: $strategy->approver !== null ? $strategy->approver->name.' ('.$strategy->approval_status->label().' le '.$strategy->approved_at?->format('d/m/Y').')' : 'À désigner',
            approval: $this->approval($strategy),
        );

        foreach ([
            'le contexte et les objectifs' => $strategy->context,
            'la stratégie de migration' => $strategy->migration,
            'les tests de non-régression et le rollback' => $strategy->safety,
        ] as $part => $text) {
            if (blank($text)) {
                $content->addPending("Stratégie : {$part} à rédiger");
            }
        }
        if ($strategy->decisions->where('status', DecisionStatus::Accepted)->isEmpty()) {
            $content->addPending('Stratégie : aucune décision acceptée');
        }

        return $content
            ->set('strategy.context', $this->context($strategy))
            ->set('strategy.baseline', $this->baseline($strategy))
            ->set('strategy.options', $this->options($strategy))
            ->set('strategy.tradeoffs', $this->tradeoffs($strategy))
            ->set('strategy.migration', $this->migration($strategy))
            ->set('strategy.safety', $this->narrative($strategy->safety, 'Tests de non-régression et procédure de retour arrière à rédiger.')
                ->paragraph($strategy->phases->whereNotNull('rollback')->isNotEmpty() ? 'Le retour arrière de chaque phase est précisé dans « Stratégie de migration et compatibilité ».' : 'Aucune phase ne précise encore son retour arrière.'))
            ->set('strategy.recommendation', $this->recommendation($strategy))
            ->set('strategy.saas_future', $this->saas($strategy));
    }

    private function approval(RefactoringStrategy $strategy): string
    {
        return match ($strategy->approval_status) {
            ApprovalStatus::Approved => sprintf('Stratégie %s validée par %s le %s.%s', $strategy->displayCode(), $strategy->approver->name ?? '?', $strategy->approved_at?->format('d/m/Y'), $strategy->approval_note !== null ? ' '.$strategy->approval_note : ''),
            ApprovalStatus::Rejected => sprintf('Stratégie %s rejetée par %s le %s : %s', $strategy->displayCode(), $strategy->approver->name ?? '?', $strategy->approved_at?->format('d/m/Y'), $strategy->approval_note),
            ApprovalStatus::InReview => sprintf('Stratégie %s en revue : pas encore validée.', $strategy->displayCode()),
            ApprovalStatus::Draft => sprintf('Stratégie %s en brouillon : non soumise à validation.', $strategy->displayCode()),
        };
    }

    private function context(RefactoringStrategy $strategy): TextSection
    {
        $versions = Version::query()->active()->excludingFuture()->where('product_id', $strategy->product_id)->count();

        return $this->narrative($strategy->context, 'Contexte et objectifs à rédiger.')
            ->paragraph(sprintf('Périmètre : %s, %d application%s en exploitation (hors perspectives futures).', $strategy->product->name, $versions, $versions > 1 ? 's' : ''));
    }

    /**
     * Facts from the audits: the debt register of the product and the findings the strategy answers.
     */
    private function baseline(RefactoringStrategy $strategy): TextSection
    {
        $open = Issue::query()->active()->whereIn('status', ['identified', 'qualified', 'planned'])
            ->whereHas('implementations.version', fn ($query) => $query->where('product_id', $strategy->product_id)->where('status', '!=', 'future'))
            ->get();

        $section = (new TextSection)->paragraph(sprintf(
            'Registre de dette du produit (L5) : %d problème%s à traiter, dont %s.',
            $open->count(), $open->count() > 1 ? 's' : '',
            implode(', ', array_map(fn (IssueSeverity $severity): string => ($count = $open->where('severity', $severity)->count()).' '.mb_strtolower($severity->label()).($count > 1 ? 's' : ''), IssueSeverity::cases())),
        ));

        if ($strategy->issues->isEmpty()) {
            return $section->paragraph('Aucun constat n’est encore rattaché à la stratégie.');
        }

        return $section->paragraph('Constats auxquels la stratégie répond :')->bullets($strategy->issues
            ->sortBy(fn (Issue $issue): array => [array_search($issue->severity, IssueSeverity::cases(), true), $issue->id])
            ->map(fn (Issue $issue): string => sprintf('%s · %s (%s, %s)', $issue->displayCode(), $issue->title, mb_strtolower($issue->severity->label()), mb_strtolower($issue->status->label())))
            ->values()->all());
    }

    private function options(RefactoringStrategy $strategy): TextSection
    {
        $section = new TextSection;
        if ($strategy->options->isEmpty()) {
            return $section->paragraph('Aucun scénario décrit.');
        }

        foreach ($strategy->options as $option) {
            $section->paragraph(sprintf('%s · %s%s', $option->displayCode(), $option->name, $option->is_recommended ? ' (recommandé)' : ''));
            $section->bullets(array_values(array_filter([
                $option->description !== null ? $this->plain($option->description) : null,
                $option->benefits !== null ? 'Avantages : '.$this->plain($option->benefits) : null,
                $option->drawbacks !== null ? 'Inconvénients : '.$this->plain($option->drawbacks) : null,
            ])));
        }

        return $section;
    }

    private function tradeoffs(RefactoringStrategy $strategy): TableSection
    {
        $table = (new TableSection('Comparaison des scénarios'))
            ->column('option_id', 'Scénario')
            ->column('name', 'Intitulé')
            ->column('benefits', 'Avantages')
            ->column('drawbacks', 'Inconvénients')
            ->column('effort', 'Effort')
            ->column('risk', 'Risque')
            ->column('recommended', 'Recommandé', required: false)
            ->column('decision', 'Décision', required: false);

        foreach ($strategy->options as $option) {
            /** @var StrategyDecision|null $decision */
            $decision = $strategy->decisions->firstWhere('strategy_option_id', $option->id);
            $table->row([
                'option_id' => $option->displayCode(),
                'name' => $option->name,
                'benefits' => $option->benefits !== null ? $this->plain($option->benefits) : null,
                'drawbacks' => $option->drawbacks !== null ? $this->plain($option->drawbacks) : null,
                'effort' => Effort::format($option->effort_min_days, $option->effort_max_days),
                'risk' => $option->risk_level?->label(),
                'recommended' => $option->is_recommended ? 'Oui' : null,
                'decision' => $decision !== null ? $decision->displayCode().' · '.mb_strtolower($decision->status->label()) : null,
            ]);
        }

        return $table;
    }

    private function migration(RefactoringStrategy $strategy): TextSection
    {
        $section = $this->narrative($strategy->migration, 'Stratégie de migration à rédiger.');
        if ($strategy->phases->isEmpty()) {
            return $section->paragraph('Aucune phase de migration décrite.');
        }

        foreach ($strategy->phases->values() as $index => $phase) {
            /** @var StrategyPhase $phase */
            $section->paragraph(sprintf('Phase %d · %s', $index + 1, $phase->name))->bullets(array_values(array_filter([
                $phase->description !== null ? $this->plain($phase->description) : null,
                'Prérequis : '.($phase->prerequisites !== null ? $this->plain($phase->prerequisites) : 'à confirmer'),
                'Retour arrière : '.($phase->rollback !== null ? $this->plain($phase->rollback) : 'à confirmer'),
            ])));
        }

        return $section;
    }

    private function recommendation(RefactoringStrategy $strategy): TextSection
    {
        $section = new TextSection;

        /** @var StrategyOption|null $recommended */
        $recommended = $strategy->options->firstWhere('is_recommended', true);
        $section->paragraph($recommended !== null
            ? sprintf('Scénario recommandé : %s · %s.', $recommended->displayCode(), $recommended->name)
            : 'Aucun scénario n’est encore recommandé.');

        if ($strategy->risks->isNotEmpty()) {
            $section->paragraph('Risques :')->bullets($strategy->risks->map(fn (StrategyRisk $risk): string => sprintf(
                '%s · %s — probabilité %s, impact %s — maîtrise : %s — responsable : %s',
                $risk->displayCode(), $this->plain($risk->description),
                $risk->probability !== null ? mb_strtolower($risk->probability->feminineLabel()) : 'à confirmer',
                $risk->impact !== null ? mb_strtolower($risk->impact->label()) : 'à confirmer',
                $risk->mitigation !== null ? $this->plain($risk->mitigation) : 'à définir',
                $risk->owner->name ?? 'à désigner',
            ))->all());
        } else {
            $section->paragraph('Aucun risque décrit.');
        }

        $prerequisites = $strategy->phases->whereNotNull('prerequisites');
        if ($prerequisites->isNotEmpty()) {
            $section->paragraph('Prérequis :')->bullets($prerequisites->map(fn (StrategyPhase $phase): string => $phase->name.' : '.$this->plain((string) $phase->prerequisites))->values()->all());
        }

        if ($strategy->decisions->isEmpty()) {
            return $section->paragraph('Aucune décision enregistrée.');
        }

        return $section->paragraph('Décisions :')->bullets($strategy->decisions->map(fn (StrategyDecision $decision): string => sprintf(
            '%s · %s — %s%s%s',
            $decision->displayCode(), $decision->title, mb_strtolower($decision->status->label()),
            $decision->decider !== null ? sprintf(' (%s, %s)', $decision->decider->name, $decision->decided_on?->format('d/m/Y')) : '',
            $decision->rationale !== null ? ' — '.$this->plain($decision->rationale) : '',
        ))->all());
    }

    private function saas(RefactoringStrategy $strategy): TextSection
    {
        $future = Version::query()->active()->where('product_id', $strategy->product_id)->where('status', 'future')->orderBy('name')->pluck('name');

        $section = (new TextSection)->paragraph('Satis_SaaS est une perspective future : il n’est pas une application de référence de production et reste hors de la baseline de cette stratégie.');
        if ($future->isNotEmpty()) {
            $section->paragraph('Perspectives futures du produit, exclues des chiffres ci-dessus : '.$future->implode(', ').'.');
        }

        return $strategy->saas_notes !== null ? $section->paragraph($this->plain($strategy->saas_notes, 2000)) : $section;
    }

    private function narrative(?string $markdown, string $missing): TextSection
    {
        $section = new TextSection;
        if ($markdown === null || trim($markdown) === '') {
            return $section->paragraph($missing);
        }

        foreach (preg_split('/\n\s*\n/', trim($markdown)) ?: [] as $paragraph) {
            $section->paragraph($this->plain($paragraph, 4000));
        }

        return $section;
    }

    private function plain(string $markdown, int $limit = self::TEXT_LIMIT): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::markdown($markdown, ['html_input' => 'strip'])), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, $limit, '…');
    }
}
