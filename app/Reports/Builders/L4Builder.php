<?php

namespace App\Reports\Builders;

use App\Enums\FindingStatus;
use App\Enums\IssueSeverity;
use App\Models\DiagnosticCampaign;
use App\Models\DiagnosticFinding;
use App\Models\Issue;
use App\Models\Release;
use App\Models\TestRun;
use App\Models\ToolRun;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * L4 — Rapport de diagnostic par dépôt: one campaign, i.e. one repository at one commit. Tools
 * run outside the platform; their imported results are reported with their triage (RG-18): only
 * retained results are findings, results still to verify are shown as such, false positives go
 * to the XLSX annex with the reason of their rejection. Nothing is measured here: what was not
 * imported or entered stays "À confirmer" or "non mesuré".
 */
final class L4Builder implements ReportBuilder
{
    private const TEXT_LIMIT = 300;

    public function build(ReportScope $scope): ReportContent
    {
        $campaign = $scope->campaign();
        if ($campaign === null) {
            throw new InvalidArgumentException('L4 se génère pour une campagne de diagnostic.');
        }

        $campaign->load(['repository.version.product', 'repository.version.customer', 'toolRuns', 'testRuns' => fn ($query) => $query->whereNull('archived_at')]);
        $version = $campaign->repository->version;

        $findings = $campaign->findings()
            ->with(['toolRun', 'issue', 'triager'])
            ->orderBy('id')
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $version->product->name, $version->product->code),
            client: $version->customer !== null ? sprintf('%s · %s', $version->customer->displayCode(), $version->customer->name) : 'Sans client (version de base)',
            repository: sprintf('%s · %s', $campaign->repository->displayCode(), $campaign->repository->path),
            commit: $campaign->commit_sha,
        );

        return $content
            ->set('diagnostic.scope', $this->scope($campaign))
            ->set('diagnostic.tools', $this->tools($campaign))
            ->set('diagnostic.results', $this->results($findings))
            ->set('findings.table', $this->findingsTable($campaign, $findings))
            ->set('diagnostic.tests', $this->tests($campaign))
            ->set('diagnostic.recommendations', $this->recommendations($campaign, $findings))
            ->set('diagnostic.evidence', $this->evidence($campaign))
            // Not in the Word template: kept in the XLSX annex only.
            ->set('annex.false_positives', $this->falsePositivesTable($campaign, $findings));
    }

    private function scope(DiagnosticCampaign $campaign): TextSection
    {
        $repository = $campaign->repository;
        $version = $repository->version;

        $section = (new TextSection)->paragraph(sprintf(
            'Campagne %s « %s » : analyse du dépôt %s (%s) de l’application %s · %s, au commit %s.',
            $campaign->displayCode(), $campaign->title, $repository->path, $repository->displayCode(),
            $version->displayCode(), $version->name, $campaign->commit_sha,
        ));

        $section->paragraph('Date d’analyse : '.($campaign->analyzed_on?->format('d/m/Y') ?? 'À confirmer').'.');

        $releases = Release::query()->where('repository_id', $repository->id)->whereNotNull('commit_sha')
            ->where('commit_sha', 'like', substr($campaign->commit_sha, 0, 7).'%')->with('deployments.environment')->get()
            ->filter(fn (Release $release): bool => str_starts_with((string) $release->commit_sha, $campaign->commit_sha) || str_starts_with($campaign->commit_sha, (string) $release->commit_sha));
        foreach ($releases as $release) {
            $environments = $release->deployments->map(fn ($deployment): string => $deployment->environment->name.' ('.$deployment->deployed_at->format('d/m/Y').')')->unique()->implode(', ');
            $section->paragraph(sprintf('Ce commit est celui de la release %s%s.', $release->name, $environments !== '' ? ', déployée sur '.$environments : ''));
        }

        if ($campaign->objective !== null) {
            $section->paragraph('Objet : '.$this->plainText($campaign->objective, 2000));
        }

        return $section;
    }

    private function tools(DiagnosticCampaign $campaign): TextSection
    {
        $section = (new TextSection)->paragraph('Les outils sont exécutés hors de la plateforme, sur le commit analysé. Leurs fichiers de résultats sont importés tels quels, une seule fois chacun ; un résultat signalé plusieurs fois n’est compté qu’une fois. Chaque résultat est vérifié par une personne avant de devenir un constat ; les extraits de code des fichiers ne sont pas conservés.');

        if ($campaign->toolRuns->isEmpty()) {
            return $section->paragraph('Aucun résultat d’outil importé pour cette campagne.');
        }

        $section->paragraph('Analyses importées :')->bullets($campaign->toolRuns->map(fn (ToolRun $run): string => sprintf(
            '%s %s — exécuté le %s — %s, fichier %s — %d résultat%s, dont %d nouveau%s',
            $run->tool_name, $run->tool_version ?? '(version à confirmer)', $run->scanned_on->format('d/m/Y'),
            $run->format->shortLabel(), $run->file_name,
            $run->results_count, $this->plural($run->results_count), $run->new_count, $run->new_count > 1 ? 'x' : '',
        ))->all());

        if ($campaign->testRuns->isNotEmpty()) {
            $section->paragraph('Tests : '.$campaign->testRuns->map(fn (TestRun $run): string => $run->toolLabel())->unique()->implode(', ').' (voir « Tests et limites de couverture »).');
        }

        return $section;
    }

    /**
     * @param  EloquentCollection<int, DiagnosticFinding>  $findings
     */
    private function results(EloquentCollection $findings): TextSection
    {
        $total = $findings->count();
        $byStatus = fn (FindingStatus $status): int => $findings->where('status', $status)->count();
        $verified = $total - $byStatus(FindingStatus::ToVerify);

        $section = (new TextSection)->paragraph(sprintf(
            '%d résultat%s distinct%s importé%s ; %d vérifié%s (%s %%).',
            $total, $this->plural($total), $this->plural($total), $this->plural($total),
            $verified, $this->plural($verified), $total > 0 ? number_format($verified * 100 / $total, 0, ',', ' ') : '0',
        ));

        if ($total === 0) {
            return $section;
        }

        $section->bullets([
            sprintf('Retenus dans un problème : %d', $byStatus(FindingStatus::Retained)),
            sprintf('Faux positifs écartés : %d (motifs en annexe XLSX)', $byStatus(FindingStatus::FalsePositive)),
            sprintf('Encore à vérifier : %d — ils ne constituent pas des constats', $byStatus(FindingStatus::ToVerify)),
        ]);

        $section->paragraph('Par outil (retenus / à vérifier / faux positifs) :')->bullets(
            $findings->groupBy(fn (DiagnosticFinding $finding): string => $finding->toolRun->tool_name)
                ->map(fn (Collection $group, string $tool): string => sprintf(
                    '%s : %d / %d / %d', $tool,
                    $group->where('status', FindingStatus::Retained)->count(),
                    $group->where('status', FindingStatus::ToVerify)->count(),
                    $group->where('status', FindingStatus::FalsePositive)->count(),
                ))->sort()->values()->all(),
        );

        $rules = $findings->where('status', '!=', FindingStatus::FalsePositive)->whereNotNull('rule_id')
            ->countBy('rule_id')->sortDesc()->take(5);
        if ($rules->isNotEmpty()) {
            $section->paragraph('Règles les plus signalées (hors faux positifs) :')
                ->bullets($rules->map(fn (int $count, string $rule): string => $rule.' : '.$count)->values()->all());
        }

        return $section;
    }

    /**
     * Retained results first, by gravity of their problem, then results still to verify by level.
     *
     * @param  EloquentCollection<int, DiagnosticFinding>  $findings
     */
    private function findingsTable(DiagnosticCampaign $campaign, EloquentCollection $findings): TableSection
    {
        $table = $this->findingColumns(new TableSection('Constats priorisés'))
            ->column('issue_id', 'Problème', required: false)
            ->column('limitations', 'Limites / commentaire', required: false);

        $severityRank = array_flip(array_map(fn (IssueSeverity $severity): string => $severity->value, IssueSeverity::cases()));
        $levelRank = ['error' => 0, 'warning' => 1, 'note' => 2];

        $ordered = $findings->where('status', '!=', FindingStatus::FalsePositive)->sortBy(fn (DiagnosticFinding $finding): array => [
            $finding->status === FindingStatus::Retained ? 0 : 1,
            $finding->issue !== null ? $severityRank[$finding->issue->severity->value] : 9,
            $levelRank[$finding->level->value],
            $finding->path ?? '',
            $finding->line_start ?? 0,
        ]);

        foreach ($ordered as $finding) {
            $table->row([
                ...$this->findingValues($campaign, $finding),
                'severity' => $finding->issue !== null
                    ? $finding->issue->severity->label()
                    : 'Niveau outil : '.mb_strtolower($finding->level->label()),
                'issue_id' => $finding->issue?->displayCode(),
                'limitations' => $finding->status === FindingStatus::ToVerify ? 'Non vérifié : ne constitue pas un constat' : $finding->triage_note,
            ]);
        }

        return $table;
    }

    /**
     * @param  EloquentCollection<int, DiagnosticFinding>  $findings
     */
    private function falsePositivesTable(DiagnosticCampaign $campaign, EloquentCollection $findings): TableSection
    {
        $table = $this->findingColumns(new TableSection('Faux positifs écartés'), required: false)
            ->column('reason', 'Motif du rejet', required: false)
            ->column('decided', 'Décidé par', required: false);

        foreach ($findings->where('status', FindingStatus::FalsePositive) as $finding) {
            $table->row([
                ...$this->findingValues($campaign, $finding),
                'severity' => 'Niveau outil : '.mb_strtolower($finding->level->label()),
                'reason' => $finding->triage_note,
                'decided' => $finding->triager !== null ? $finding->triager->name.', '.$finding->triaged_at?->format('d/m/Y') : null,
            ]);
        }

        return $table;
    }

    private function findingColumns(TableSection $table, bool $required = true): TableSection
    {
        return $table
            ->column('finding_id', 'Identifiant', $required)
            // Same on every row or for every result of a tool, and shown in the control block or the
            // methodology: in the XLSX annex only.
            ->column('diagnostic_id', 'Campagne', $required, inDocument: false)
            ->column('repository_id', 'Dépôt', $required, inDocument: false)
            ->column('commit', 'Commit', $required, inDocument: false)
            ->column('tool_name', 'Outil', $required)
            ->column('tool_version', 'Version de l’outil', $required, inDocument: false)
            ->column('scan_date', 'Date d’analyse', $required, inDocument: false)
            ->column('rule', 'Règle', required: false)
            ->column('location', 'Emplacement', required: false)
            ->column('message', 'Message', $required)
            ->column('severity', 'Gravité', $required)
            ->column('evidence_url', 'Preuve', $required)
            ->column('verification_status', 'Vérification', $required);
    }

    /**
     * @return array<string, string|null>
     */
    private function findingValues(DiagnosticCampaign $campaign, DiagnosticFinding $finding): array
    {
        return [
            'finding_id' => $finding->displayCode(),
            'diagnostic_id' => $campaign->displayCode(),
            'repository_id' => $campaign->repository->displayCode().' · '.$campaign->repository->path,
            'commit' => substr($campaign->commit_sha, 0, 12),
            'tool_name' => $finding->toolRun->tool_name,
            'tool_version' => $finding->toolRun->tool_version,
            'scan_date' => $finding->toolRun->scanned_on->format('d/m/Y'),
            'rule' => $finding->rule_id,
            'location' => $finding->location(),
            'message' => Str::limit($finding->message, self::TEXT_LIMIT, '…'),
            'evidence_url' => $campaign->fileUrl($finding->path, $finding->line_start, $finding->line_end),
            'verification_status' => $finding->status === FindingStatus::ToVerify
                ? $finding->status->verificationStatus()->label()
                : $finding->status->verificationStatus()->label().' — '.mb_strtolower($finding->status->label()),
        ];
    }

    private function tests(DiagnosticCampaign $campaign): TextSection
    {
        $section = new TextSection;

        if ($campaign->testRuns->isEmpty()) {
            $section->paragraph('Aucune exécution de tests enregistrée : les tests et la couverture ne sont pas mesurés pour ce commit.');
        } else {
            $section->paragraph('Exécutions de tests :')->bullets($campaign->testRuns->map(fn (TestRun $run): string => sprintf(
                '%s — exécuté le %s — %s — couverture des lignes : %s%s',
                $run->toolLabel(), $run->executed_on->format('d/m/Y'),
                $run->tests_total !== null
                    ? sprintf('%d tests : %s réussis, %s en échec, %s ignorés', $run->tests_total, $run->tests_passed ?? '?', $run->tests_failed ?? '?', $run->tests_skipped ?? '?')
                    : 'nombre de tests non mesuré',
                $run->line_coverage !== null ? number_format((float) $run->line_coverage, 1, ',', ' ').' %' : 'non mesurée',
                $run->scope !== null ? ' — périmètre : '.$this->plainText($run->scope, self::TEXT_LIMIT) : '',
            ))->all());
        }

        return $section->paragraph('Limites de la campagne : '.($campaign->limitations !== null ? $this->plainText($campaign->limitations, 2000) : 'À confirmer.'));
    }

    /**
     * @param  EloquentCollection<int, DiagnosticFinding>  $findings
     */
    private function recommendations(DiagnosticCampaign $campaign, EloquentCollection $findings): TextSection
    {
        $section = new TextSection;

        /** @var Collection<int, Issue> $issues */
        $issues = $findings->where('status', FindingStatus::Retained)->pluck('issue')->filter()->unique('id')
            ->filter(fn (Issue $issue): bool => $issue->archived_at === null)
            ->sortBy(fn (Issue $issue): array => [array_search($issue->severity, IssueSeverity::cases(), true), $issue->id]);
        $open = $issues->filter(fn (Issue $issue): bool => $issue->status->isOpen());

        if ($open->isEmpty()) {
            $section->paragraph('Aucun problème à traiter issu de cette campagne.');
        } else {
            $section->paragraph('Problèmes à traiter issus de cette campagne (détail dans le registre L5) :')->bullets($open->map(fn (Issue $issue): string => sprintf(
                '%s · %s — %s, %s%s%s',
                $issue->displayCode(), $issue->title, mb_strtolower($issue->severity->label()), mb_strtolower($issue->status->label()),
                $issue->effortLabel() !== null ? ', effort '.$issue->effortLabel() : ', effort à estimer',
                $issue->proposed_solution !== null ? ' — '.$this->plainText($issue->proposed_solution, 200) : '',
            ))->values()->all());
        }

        $next = [];
        if (($toVerify = $findings->where('status', FindingStatus::ToVerify)->count()) > 0) {
            $next[] = sprintf('Vérifier les %d résultat%s restant%s.', $toVerify, $this->plural($toVerify), $this->plural($toVerify));
        }
        if (($unversioned = $campaign->toolRuns->whereNull('tool_version')->pluck('tool_name')->unique())->isNotEmpty()) {
            $next[] = 'Confirmer la version de : '.$unversioned->implode(', ').'.';
        }
        if ($campaign->testRuns->isEmpty()) {
            $next[] = 'Exécuter les tests sur ce commit et enregistrer leur résultat et la couverture.';
        }
        if ($campaign->repository->web_url === null) {
            $next[] = 'Renseigner l’adresse GitLab du dépôt pour rendre les preuves consultables.';
        }
        if ($open->contains(fn (Issue $issue): bool => $issue->owner_id === null)) {
            $next[] = 'Désigner un responsable pour chaque problème à traiter.';
        }

        if ($next !== []) {
            $section->paragraph('Prochaines actions :')->bullets($next);
        }

        return $section;
    }

    private function evidence(DiagnosticCampaign $campaign): TextSection
    {
        $webUrl = $campaign->repository->web_url;
        $section = (new TextSection)->paragraph($webUrl !== null
            ? 'Commit analysé : '.rtrim($webUrl, '/').'/-/commit/'.$campaign->commit_sha
            : 'Commit analysé : '.$campaign->commit_sha.' (adresse GitLab du dépôt à confirmer).');

        if ($campaign->toolRuns->isNotEmpty()) {
            $section->paragraph('Fichiers de résultats importés, avec leur empreinte SHA-256 :')
                ->bullets($campaign->toolRuns->map(fn (ToolRun $run): string => sprintf('%s (%s) : %s', $run->file_name, $run->toolLabel(), $run->file_sha256))->all());
        }

        $reports = $campaign->testRuns->whereNotNull('source_sha256');
        if ($reports->isNotEmpty()) {
            $section->paragraph('Rapports de tests :')
                ->bullets($reports->map(fn (TestRun $run): string => sprintf('%s (%s) : %s', $run->source_file_name, $run->toolLabel(), $run->source_sha256))->values()->all());
        }

        return $section->paragraph('Chaque constat renvoie au fichier et aux lignes concernés, au commit analysé. L’annexe XLSX contient la liste complète des résultats, y compris les faux positifs écartés avec leur motif.');
    }

    private function plainText(string $markdown, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::markdown($markdown, ['html_input' => 'strip'])), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, $limit, '…');
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
