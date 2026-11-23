<?php

namespace App\Reports\Builders;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Enums\IssueSeverity;
use App\Models\Issue;
use App\Models\RemediationAction;
use App\Models\Version;
use App\Models\WorkPackage;
use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\ReportScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * L8 — Feuille de route de remédiation: the action plan held in the application (work packages
 * with their milestone, actions treating the problems of the debt register). With a customer, only
 * the actions treating a problem of that customer's versions are kept. The capacity of the team
 * that will carry the work is not held: it stays to confirm.
 */
final class L8Builder implements ReportBuilder
{
    private const TEXT_LIMIT = 300;

    public function build(ReportScope $scope): ReportContent
    {
        $product = $scope->product();
        $customer = $scope->customer();

        $versionIds = Version::query()->active()->excludingFuture()->where('product_id', $product->id)
            ->when($customer !== null, fn ($query) => $query->where('customer_id', $customer?->id))
            ->pluck('id');

        /** @var EloquentCollection<int, RemediationAction> $actions */
        $actions = RemediationAction::query()
            ->where('product_id', $product->id)
            ->whereNull('archived_at')
            ->when($customer !== null, fn ($query) => $query->whereHas('issue.implementations', fn ($query) => $query->whereIn('version_id', $versionIds)))
            ->with(['issue', 'owner', 'workPackage', 'dependencies'])
            ->get()
            ->sortBy(fn (RemediationAction $action): array => [
                $action->workPackage?->due_on?->toDateString() ?? '9999',
                $action->priority->value,
                $action->due_on?->toDateString() ?? '9999',
                $action->id,
            ])->values();
        $active = $actions->where('status', '!=', ActionStatus::Cancelled);

        $packages = WorkPackage::query()->where('product_id', $product->id)->whereNull('archived_at')
            ->when($customer !== null, fn ($query) => $query->whereIn('id', $actions->pluck('work_package_id')->filter()->unique()))
            ->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('position')->get();

        $openIssues = Issue::query()->active()
            ->whereHas('implementations', fn ($query) => $query->whereIn('version_id', $versionIds))
            ->whereIn('status', ['identified', 'qualified', 'planned'])
            ->get();

        $content = new ReportContent(
            product: sprintf('%s (%s)', $product->name, $product->code),
            client: $customer !== null ? sprintf('%s · %s', $customer->displayCode(), $customer->name) : 'Tous les clients',
        );

        return $content
            ->set('roadmap.objectives', $this->objectives($active, $openIssues))
            ->set('work_packages.table', $this->packagesTable($packages, $active))
            ->set('roadmap.dependencies', $this->dependencies($active))
            ->set('roadmap.capacity', $this->capacity($active, $packages))
            ->set('roadmap.acceptance', $this->acceptance($active))
            ->set('roadmap.risks', $this->risks($active, $openIssues))
            ->set('actions.table', $this->actionsTable($actions));
    }

    /**
     * @param  Collection<int, RemediationAction>  $active
     * @param  EloquentCollection<int, Issue>  $openIssues
     */
    private function objectives(Collection $active, EloquentCollection $openIssues): TextSection
    {
        $open = $active->filter(fn (RemediationAction $action): bool => $action->status->isOpen());
        $section = (new TextSection)->paragraph(sprintf(
            '%d action%s au plan, dont %d à mener et %d terminée%s.',
            $active->count(), $this->plural($active->count()), $open->count(),
            $done = $active->where('status', ActionStatus::Done)->count(), $this->plural($done),
        ));

        if ($open->isNotEmpty()) {
            $section->paragraph('Actions à mener par priorité :')->bullets(array_map(
                fn (ActionPriority $priority): string => sprintf('%s : %d', $priority->label(), $open->where('priority', $priority)->count()),
                ActionPriority::cases(),
            ));
        }

        $covered = $openIssues->filter(fn (Issue $issue): bool => $active->contains('issue_id', $issue->id));
        $section->paragraph(sprintf(
            'Problèmes à traiter couverts par au moins une action : %s.',
            $this->ratio($covered->count(), $openIssues->count()),
        ));

        $blockingUncovered = $openIssues->where('severity', IssueSeverity::Blocking)->reject(fn (Issue $issue): bool => $active->contains('issue_id', $issue->id));
        if ($blockingUncovered->isNotEmpty()) {
            $section->paragraph('Problèmes bloquants sans action :')->bullets($blockingUncovered->map(fn (Issue $issue): string => $issue->displayCode().' · '.$issue->title)->values()->all());
        }

        return $section;
    }

    /**
     * @param  EloquentCollection<int, WorkPackage>  $packages
     * @param  Collection<int, RemediationAction>  $active
     */
    private function packagesTable(EloquentCollection $packages, Collection $active): TableSection
    {
        $table = (new TableSection('Lots et jalons'))
            ->column('package_id', 'Lot')
            ->column('name', 'Intitulé')
            ->column('objective', 'Objectif', required: false)
            ->column('start_date', 'Début', required: false)
            ->column('milestone', 'Jalon')
            ->column('status', 'Statut')
            ->column('progress', 'Actions terminées', required: false)
            ->column('effort', 'Effort (j/h)', required: false);

        foreach ($packages as $package) {
            $items = $active->where('work_package_id', $package->id);
            $table->row([
                'package_id' => $package->displayCode(),
                'name' => $package->name,
                'objective' => $package->objective !== null ? Str::limit($package->objective, self::TEXT_LIMIT, '…') : null,
                'start_date' => $package->starts_on?->format('d/m/Y'),
                'milestone' => $package->due_on?->format('d/m/Y'),
                'status' => $package->status->label(),
                'progress' => sprintf('%d / %d', $items->where('status', ActionStatus::Done)->count(), $items->count()),
                'effort' => $this->number($items->sum(fn (RemediationAction $action): float => (float) $action->effort_days)),
            ]);
        }

        return $table;
    }

    /**
     * @param  Collection<int, RemediationAction>  $active
     */
    private function dependencies(Collection $active): TextSection
    {
        $section = new TextSection;
        $withDependencies = $active->filter(fn (RemediationAction $action): bool => $action->dependencies->isNotEmpty());

        if ($withDependencies->isEmpty()) {
            $section->paragraph('Aucune dépendance déclarée entre les actions.');
        } else {
            $section->paragraph('Dépendances :')->bullets($withDependencies->map(fn (RemediationAction $action): string => sprintf(
                '%s dépend de %s',
                $action->displayCode(),
                $action->dependencies->map(fn (RemediationAction $dependency): string => $dependency->displayCode().' ('.mb_strtolower($dependency->status->label()).')')->implode(', '),
            ))->values()->all());

            $conflicts = $this->dependencyConflicts($active);
            if ($conflicts !== []) {
                $section->paragraph('Points d’attention :')->bullets($conflicts);
            }
        }

        $owners = $active->filter(fn (RemediationAction $action): bool => $action->status->isOpen())
            ->groupBy(fn (RemediationAction $action): string => $action->owner->name ?? 'Responsable à désigner')
            ->map(fn (Collection $items, string $owner): string => sprintf('%s : %d action%s, %s', $owner, $items->count(), $this->plural($items->count()), $this->days($items)))
            ->sort()->values()->all();

        return $owners === [] ? $section : $section->paragraph('Responsabilités (actions à mener) :')->bullets($owners);
    }

    /**
     * An action started or done while a dependency is not done, or due before a dependency.
     *
     * @param  Collection<int, RemediationAction>  $active
     * @return list<string>
     */
    private function dependencyConflicts(Collection $active): array
    {
        $conflicts = [];
        foreach ($active as $action) {
            foreach ($action->dependencies as $dependency) {
                if (in_array($action->status, [ActionStatus::InProgress, ActionStatus::Done], true) && $dependency->status !== ActionStatus::Done) {
                    $conflicts[] = sprintf('%s est %s alors que %s n’est pas terminée.', $action->displayCode(), mb_strtolower($action->status->label()), $dependency->displayCode());
                } elseif ($action->due_on !== null && $dependency->due_on !== null && $dependency->due_on->greaterThan($action->due_on)) {
                    $conflicts[] = sprintf('%s est due le %s, avant %s dont elle dépend (due le %s).', $action->displayCode(), $action->due_on->format('d/m/Y'), $dependency->displayCode(), $dependency->due_on->format('d/m/Y'));
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  Collection<int, RemediationAction>  $active
     * @param  EloquentCollection<int, WorkPackage>  $packages
     */
    private function capacity(Collection $active, EloquentCollection $packages): TextSection
    {
        $open = $active->filter(fn (RemediationAction $action): bool => $action->status->isOpen());
        $estimated = $open->whereNotNull('effort_days');

        $section = (new TextSection)->paragraph(sprintf(
            'Charge restante : %s sur %d action%s estimée%s ; %d sans estimation.',
            $this->days($estimated), $estimated->count(), $this->plural($estimated->count()), $this->plural($estimated->count()), $open->count() - $estimated->count(),
        ));

        $byMonth = $open->whereNotNull('due_on')->groupBy(fn (RemediationAction $action): string => $action->due_on?->format('Y-m') ?? '')->sortKeys();
        if ($byMonth->isNotEmpty()) {
            $section->paragraph('Charge par mois d’échéance :')->bullets($byMonth->map(fn (Collection $items, string $month): string => sprintf(
                '%s : %s (%d action%s)', Carbon::createFromFormat('Y-m-d', $month.'-01')?->locale('fr')->translatedFormat('F Y'), $this->days($items), $items->count(), $this->plural($items->count()),
            ))->values()->all());
        }

        if (($undated = $open->whereNull('due_on')->count()) > 0) {
            $section->paragraph(sprintf('%d action%s sans échéance.', $undated, $this->plural($undated)));
        }

        $milestones = $packages->filter(fn (WorkPackage $package): bool => $package->due_on !== null && ! in_array($package->status->value, ['done', 'cancelled'], true));
        if ($milestones->isNotEmpty()) {
            $section->paragraph('Prochains jalons :')->bullets($milestones->take(5)->map(fn (WorkPackage $package): string => sprintf(
                '%s · %s — %s', $package->displayCode(), $package->name, $package->due_on?->format('d/m/Y'),
            ))->values()->all());
        }

        return $section->paragraph('La capacité de l’équipe qui réalisera ces actions n’est pas tenue dans l’application : à confirmer avant d’engager le calendrier.');
    }

    /**
     * @param  Collection<int, RemediationAction>  $active
     */
    private function acceptance(Collection $active): TextSection
    {
        $section = (new TextSection)->paragraph('Une action n’est terminée qu’avec ses critères de réception et la preuve de test qui les vérifie.');

        $missing = $active->filter(fn (RemediationAction $action): bool => $action->status->isOpen() && blank($action->acceptance_criteria));
        $section->paragraph($missing->isEmpty()
            ? 'Toutes les actions à mener ont des critères de réception.'
            : sprintf('%d action%s à mener sans critères de réception : %s.', $missing->count(), $this->plural($missing->count()), $this->codes($missing)));

        $done = $active->where('status', ActionStatus::Done);
        if ($done->isNotEmpty()) {
            $section->paragraph('Actions terminées et leur preuve :')->bullets($done->map(fn (RemediationAction $action): string => sprintf(
                '%s · %s — terminée le %s — %s', $action->displayCode(), $action->title, $action->completed_on?->format('d/m/Y'), Str::limit((string) $action->test_evidence, 200, '…'),
            ))->values()->all());
        }

        return $section;
    }

    /**
     * @param  Collection<int, RemediationAction>  $active
     * @param  EloquentCollection<int, Issue>  $openIssues
     */
    private function risks(Collection $active, EloquentCollection $openIssues): TextSection
    {
        $late = $active->filter(fn (RemediationAction $action): bool => $action->isLate());
        $blocked = $active->where('status', ActionStatus::Blocked);
        $p1WithoutOwner = $active->filter(fn (RemediationAction $action): bool => $action->status->isOpen() && $action->priority === ActionPriority::P1 && $action->owner_id === null);
        $blockingUncovered = $openIssues->where('severity', IssueSeverity::Blocking)->reject(fn (Issue $issue): bool => $active->contains('issue_id', $issue->id));

        $points = array_values(array_filter([
            $late->isNotEmpty() ? sprintf('%d action%s en retard : %s', $late->count(), $this->plural($late->count()), $this->codes($late)) : null,
            $blocked->isNotEmpty() ? sprintf('%d action%s bloquée%s : %s', $blocked->count(), $this->plural($blocked->count()), $this->plural($blocked->count()), $this->codes($blocked)) : null,
            $p1WithoutOwner->isNotEmpty() ? sprintf('%d action%s critique%s sans responsable : %s', $p1WithoutOwner->count(), $this->plural($p1WithoutOwner->count()), $this->plural($p1WithoutOwner->count()), $this->codes($p1WithoutOwner)) : null,
            $blockingUncovered->isNotEmpty() ? sprintf('%d problème%s bloquant%s sans action planifiée', $blockingUncovered->count(), $this->plural($blockingUncovered->count()), $this->plural($blockingUncovered->count())) : null,
            ($conflicts = count($this->dependencyConflicts($active))) > 0 ? sprintf('%d incohérence%s de dépendances (voir « Dépendances et responsabilités »)', $conflicts, $this->plural($conflicts)) : null,
        ]));

        $section = new TextSection;

        return $points === [] ? $section->paragraph('Aucun risque relevé dans les données du plan.') : $section->paragraph('Risques relevés dans les données du plan :')->bullets($points)
            ->paragraph('Les arbitrages correspondants relèvent de la stratégie de refactoring (L7).');
    }

    /**
     * @param  EloquentCollection<int, RemediationAction>  $actions
     */
    private function actionsTable(EloquentCollection $actions): TableSection
    {
        $table = (new TableSection('Suivi des actions'))
            ->column('action_id', 'Identifiant')
            ->column('title', 'Action')
            ->column('debt_id', 'Problème traité')
            ->column('work_package', 'Lot', required: false)
            ->column('priority', 'Priorité')
            ->column('owner', 'Responsable')
            ->column('start_date', 'Début')
            ->column('due_date', 'Échéance')
            ->column('effort_days', 'Effort (j/h)')
            ->column('dependencies', 'Dépend de', required: false)
            ->column('status', 'Statut')
            ->column('acceptance_criteria', 'Critères de réception')
            ->column('test_evidence', 'Preuve de test', required: false)
            ->column('gitlab_issue_url', 'Ticket GitLab', required: false);

        foreach ($actions as $action) {
            $table->row([
                'action_id' => $action->displayCode(),
                'title' => $action->title,
                'debt_id' => $action->issue?->displayCode(),
                'work_package' => $action->workPackage?->displayCode(),
                'priority' => $action->priority->label(),
                'owner' => $action->owner?->name,
                'start_date' => $action->starts_on?->format('d/m/Y'),
                'due_date' => $action->due_on?->format('d/m/Y').($action->isLate() ? ' (en retard)' : ''),
                'effort_days' => $action->effort_days !== null ? $this->number((float) $action->effort_days) : null,
                'dependencies' => $action->dependencies->map(fn (RemediationAction $dependency): string => $dependency->displayCode())->implode(', '),
                'status' => $action->status->label(),
                'acceptance_criteria' => $action->acceptance_criteria !== null ? Str::limit($action->acceptance_criteria, self::TEXT_LIMIT, '…') : null,
                'test_evidence' => $action->test_evidence !== null ? Str::limit($action->test_evidence, self::TEXT_LIMIT, '…') : null,
                'gitlab_issue_url' => $action->gitlab_issue_url,
            ]);
        }

        return $table;
    }

    /**
     * @param  Collection<int, RemediationAction>  $actions
     */
    private function days(Collection $actions): string
    {
        return $this->number($actions->sum(fn (RemediationAction $action): float => (float) $action->effort_days)).' j/h';
    }

    /**
     * @param  Collection<int, RemediationAction>  $actions
     */
    private function codes(Collection $actions): string
    {
        return $actions->take(10)->map(fn (RemediationAction $action): string => $action->displayCode())->implode(', ').($actions->count() > 10 ? '…' : '');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', ' '), '0'), ',');
    }

    private function ratio(int $part, int $total): string
    {
        return $total === 0 ? '0 / 0' : sprintf('%d / %d (%s %%)', $part, $total, number_format($part * 100 / $total, 0, ',', ' '));
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
