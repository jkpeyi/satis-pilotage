<?php

namespace App\Reports\Builders;

use App\Enums\Confidence;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\CodeSnippet;
use App\Models\Issue;
use App\Models\Version;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use App\Support\Effort;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * L5 — Registre consolidé de dette technique: the issues of the scope's versions, with their
 * impact, effort range, confidence, owner and status (README finding statuses). An issue that
 * concerns several applications appears once. A future perspective (Satis_SaaS) is left out.
 * The evidence is the GitLab link of an anchored code snippet when there is one; nothing else is
 * inferred.
 */
final class L5Builder implements ReportBuilder
{
    private const TEXT_LIMIT = 300;

    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();

        $versions = Version::query()
            ->active()
            ->excludingFuture()
            ->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id))
            ->with('customer')
            ->get()
            ->keyBy('id');

        $issues = Issue::query()
            ->active()
            ->whereHas('implementations', fn ($query) => $query->whereIn('version_id', $versions->keys()))
            ->with([
                'implementations' => fn ($query) => $query->whereIn('version_id', $versions->keys()),
                'owner', 'repository', 'component',
                'codeSnippets' => fn ($query) => $query->whereNull('archived_at')->whereNotNull('commit_sha')->with('repository'),
                'diagnosticFindings' => fn ($query) => $query->whereNotNull('path')->with('campaign.repository')->orderBy('id'),
            ])
            ->orderByRaw("FIELD(severity, 'blocking', 'major', 'minor')")
            ->orderBy('id')
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('debt.summary', $this->summary($issues))
            ->set('debt_items.table', $this->itemsTable($issues, $versions))
            ->set('debt.cross_cutting', $this->crossCutting($issues, $versions))
            ->set('debt.estimates', $this->estimates($issues))
            ->set('debt.decisions', $this->decisions($issues));
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function summary(Collection $issues): TextSection
    {
        $open = $issues->filter(fn (Issue $issue): bool => $issue->status->isOpen());
        $section = (new TextSection)->paragraph(sprintf(
            '%d problème%s recensé%s dans le périmètre, dont %d encore à traiter (identifiés, qualifiés ou planifiés).',
            $issues->count(), $this->plural($issues->count()), $this->plural($issues->count()), $open->count(),
        ));

        if ($issues->isEmpty()) {
            return $section;
        }

        $section->paragraph('À traiter, par gravité :')->bullets(array_map(
            fn (IssueSeverity $severity): string => sprintf('%s : %d', $severity->label(), $open->where('severity', $severity)->count()),
            IssueSeverity::cases(),
        ));

        $section->paragraph('Par statut :')->bullets(array_values(array_filter(array_map(
            fn (IssueStatus $status): ?string => ($count = $issues->where('status', $status)->count()) > 0 ? sprintf('%s : %d', $status->label(), $count) : null,
            IssueStatus::cases(),
        ))));

        return $section->paragraph('Par type : '.$issues->groupBy(fn (Issue $issue): string => $issue->type->label())
            ->map(fn (Collection $group, string $label): string => $label.' '.$group->count())->sort()->implode(', ').'.');
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @param  Collection<int, Version>  $versions
     */
    private function itemsTable(Collection $issues, Collection $versions): TableSection
    {
        $table = (new TableSection('Registre de dette'))
            ->column('debt_id', 'Identifiant')
            ->column('title', 'Intitulé')
            ->column('finding_type', 'Type')
            ->column('client_id', 'Clients')
            ->column('repository_id', 'Dépôt')
            ->column('component_id', 'Composant')
            ->column('description', 'Description')
            ->column('impact', 'Impact')
            ->column('severity', 'Gravité')
            ->column('evidence_url', 'Preuve')
            ->column('effort_min_days', 'Effort min (j/h)')
            ->column('effort_max_days', 'Effort max (j/h)')
            ->column('confidence', 'Confiance')
            ->column('owner', 'Responsable')
            ->column('status', 'Statut')
            ->column('gitlab_issue_url', 'Ticket GitLab');

        foreach ($issues as $issue) {
            $table->row([
                'debt_id' => $issue->displayCode(),
                'title' => $issue->title,
                'finding_type' => $issue->type->label(),
                'client_id' => $this->clients($issue, $versions),
                'repository_id' => $issue->repository !== null ? $issue->repository->displayCode().' · '.$issue->repository->path : null,
                'component_id' => $issue->component !== null ? $issue->component->displayCode().' · '.$issue->component->name : null,
                'description' => $this->plainText($issue->description),
                'impact' => $this->plainText($issue->impact),
                'severity' => $issue->severity->label(),
                'evidence_url' => $this->evidenceUrl($issue),
                'effort_min_days' => $this->number($issue->effort_min_days),
                'effort_max_days' => $this->number($issue->effort_max_days),
                'confidence' => $issue->confidence?->label(),
                'owner' => $issue->owner?->name,
                'status' => $issue->status->label(),
                'gitlab_issue_url' => $issue->gitlab_issue_url,
            ]);
        }

        return $table;
    }

    /**
     * Issues shared by several applications: one fix can serve them all.
     *
     * @param  Collection<int, Issue>  $issues
     * @param  Collection<int, Version>  $versions
     */
    private function crossCutting(Collection $issues, Collection $versions): TextSection
    {
        $shared = $issues
            ->filter(fn (Issue $issue): bool => $issue->status->isOpen() && $issue->implementations->pluck('version_id')->unique()->count() > 1)
            ->sortByDesc(fn (Issue $issue): int => $issue->implementations->pluck('version_id')->unique()->count());

        $section = new TextSection;
        if ($shared->isEmpty()) {
            $section->paragraph('Aucun problème à traiter ne concerne plusieurs applications du périmètre.');
        } else {
            $section->paragraph('Problèmes à traiter présents dans plusieurs applications : une correction mutualisée peut les couvrir.')
                ->bullets($shared->map(fn (Issue $issue): string => sprintf(
                    '%s · %s (%s) — %s',
                    $issue->displayCode(), $issue->title, mb_strtolower($issue->severity->label()),
                    $issue->implementations->pluck('version_id')->unique()->map(fn (int $id): string => $versions[$id]->displayCode())->sort()->implode(', '),
                ))->values()->all());
        }

        $byRepository = $issues->filter(fn (Issue $issue): bool => $issue->status->isOpen() && $issue->repository !== null)
            ->groupBy(fn (Issue $issue): string => $issue->repository->displayCode().' · '.$issue->repository->path)
            ->map->count()->sortDesc()->take(5);
        if ($byRepository->isNotEmpty()) {
            $section->paragraph('Dépôts qui concentrent le plus de problèmes à traiter :')
                ->bullets($byRepository->map(fn (int $count, string $repository): string => $repository.' : '.$count)->values()->all());
        }

        return $section;
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function estimates(Collection $issues): TextSection
    {
        $open = $issues->filter(fn (Issue $issue): bool => $issue->status->isOpen());
        $estimated = $open->filter(fn (Issue $issue): bool => $issue->effort_min_days !== null);
        $total = Effort::format(
            $estimated->sum(fn (Issue $issue): float => (float) $issue->effort_min_days),
            $estimated->sum(fn (Issue $issue): float => (float) ($issue->effort_max_days ?? $issue->effort_min_days)),
        );

        $section = (new TextSection)->paragraph(sprintf(
            'Effort estimé des problèmes à traiter : %s, sur %d problème%s estimé%s ; %d sans estimation.',
            $total ?? '0 j/h', $estimated->count(), $this->plural($estimated->count()), $this->plural($estimated->count()), $open->count() - $estimated->count(),
        ));

        if ($estimated->isEmpty()) {
            return $section;
        }

        $section->paragraph('Par gravité :')->bullets(array_values(array_filter(array_map(function (IssueSeverity $severity) use ($estimated): ?string {
            $group = $estimated->where('severity', $severity);

            return $group->isEmpty() ? null : sprintf('%s : %s', $severity->label(), Effort::format(
                $group->sum(fn (Issue $issue): float => (float) $issue->effort_min_days),
                $group->sum(fn (Issue $issue): float => (float) ($issue->effort_max_days ?? $issue->effort_min_days)),
            ));
        }, IssueSeverity::cases()))));

        return $section->paragraph('Confiance des estimations : '.implode(', ', array_map(
            fn (Confidence $confidence): string => mb_strtolower($confidence->label()).' '.$estimated->where('confidence', $confidence)->count(),
            Confidence::cases(),
        )).', non renseignée '.$estimated->whereNull('confidence')->count().'.');
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function decisions(Collection $issues): TextSection
    {
        $planned = $issues->where('status', IssueStatus::Planned);
        $dropped = $issues->where('status', IssueStatus::Dropped);
        $section = new TextSection;

        if ($planned->isNotEmpty()) {
            $section->paragraph('Problèmes planifiés :')->bullets($planned->map(fn (Issue $issue): string => sprintf(
                '%s · %s%s%s',
                $issue->displayCode(), $issue->title,
                $issue->owner !== null ? ' — responsable : '.$issue->owner->name : '',
                $issue->gitlab_issue_url !== null ? ' — '.$issue->gitlab_issue_url : '',
            ))->values()->all());
        }

        if ($dropped->isNotEmpty()) {
            $section->paragraph('Problèmes abandonnés (décision de ne pas traiter) :')
                ->bullets($dropped->map(fn (Issue $issue): string => $issue->displayCode().' · '.$issue->title)->values()->all());
        }

        return $section->paragraph('Les décisions d’architecture et le plan d’actions détaillé relèvent des livrables L7 et L8 ; ils seront reliés à ce registre lorsque ces livrables seront disponibles.');
    }

    /**
     * @param  Collection<int, Version>  $versions
     */
    private function clients(Issue $issue, Collection $versions): string
    {
        return $issue->implementations->pluck('version_id')->unique()
            ->map(fn (int $versionId): string => $versions[$versionId]->customer !== null
                ? $versions[$versionId]->customer->displayCode().' · '.$versions[$versionId]->customer->name
                : $versions[$versionId]->displayCode().' (version de base)')
            ->unique()->sort()->implode("\n");
    }

    /**
     * GitLab link of the first anchored snippet (repository URL, commit, file, lines), else of the
     * first tool result retained in the issue (L4 campaign commit).
     */
    private function evidenceUrl(Issue $issue): ?string
    {
        /** @var CodeSnippet|null $snippet */
        $snippet = $issue->codeSnippets->first(fn (CodeSnippet $snippet): bool => $snippet->repository?->web_url !== null && $snippet->path !== null);
        if ($snippet === null) {
            foreach ($issue->diagnosticFindings as $finding) {
                if (($url = $finding->campaign->fileUrl($finding->path, $finding->line_start, $finding->line_end)) !== null) {
                    return $url;
                }
            }

            return null;
        }

        $lines = $snippet->line_start !== null ? '#L'.$snippet->line_start.($snippet->line_end !== null && $snippet->line_end !== $snippet->line_start ? '-'.$snippet->line_end : '') : '';

        return rtrim((string) $snippet->repository?->web_url, '/').'/-/blob/'.$snippet->commit_sha.'/'.ltrim((string) $snippet->path, '/').$lines;
    }

    private function plainText(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::markdown($markdown, ['html_input' => 'strip'])), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, self::TEXT_LIMIT, '…');
    }

    private function number(float|string|null $value): ?string
    {
        return $value === null ? null : rtrim(rtrim(number_format((float) $value, 1, ',', ' '), '0'), ',');
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
