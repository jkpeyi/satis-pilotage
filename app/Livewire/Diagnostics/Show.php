<?php

namespace App\Livewire\Diagnostics;

use App\Actions\Diagnostics\ImportToolResults;
use App\Actions\Diagnostics\RejectFindings;
use App\Actions\Diagnostics\ReopenFindings;
use App\Actions\Diagnostics\RetainFindingsInIssue;
use App\Actions\Diagnostics\RetainFindingsInNewIssue;
use App\Actions\Diagnostics\SaveDiagnosticCampaign;
use App\Actions\Diagnostics\SaveTestRun;
use App\Actions\Referential\ArchiveRecord;
use App\Diagnostics\TestReport;
use App\Enums\FindingLevel;
use App\Enums\FindingStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ToolResultFormat;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Concerns\IssueFieldOptions;
use App\Livewire\Forms\DiagnosticCampaignForm;
use App\Livewire\Forms\IssueForm;
use App\Livewire\Forms\TestRunForm;
use App\Livewire\Forms\ToolRunImportForm;
use App\Models\DiagnosticCampaign;
use App\Models\DiagnosticFinding;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\TestRun;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * A diagnostic campaign (L4): imports of tool results, their human triage (RG-18), test runs.
 */
class Show extends Component
{
    use EnumOptions, WithFileUploads, WithPagination;

    private const PER_PAGE = 50;

    public DiagnosticCampaign $campaign;

    public DiagnosticCampaignForm $form;

    public ToolRunImportForm $importForm;

    public TestRunForm $testForm;

    public IssueForm $issueForm;

    public bool $showForm = false;

    public bool $showImport = false;

    public bool $showTestForm = false;

    public bool $showReject = false;

    public bool $showCreateIssue = false;

    public bool $showAttach = false;

    #[Url(except: 'to_verify')]
    public string $status = 'to_verify';

    #[Url(except: '')]
    public string $level = '';

    #[Url(except: '')]
    public string $toolRun = '';

    #[Url(except: '')]
    public string $search = '';

    /** @var list<string> ids of the selected results */
    public array $selected = [];

    public string $rejectReason = '';

    public string $issueCode = '';

    public string $attachNote = '';

    /** @var list<string> */
    public array $implementationIds = [];

    public function edit(): void
    {
        $this->form->setCampaign($this->campaign);
        $this->showForm = true;
    }

    public function save(SaveDiagnosticCampaign $saveCampaign): void
    {
        try {
            $saveCampaign->handle($this->form->data(), $this->campaign);
        } catch (DomainException $exception) {
            $this->addError('form.commit_sha', $exception->getMessage());

            return;
        }

        $this->campaign->refresh();
        $this->showForm = false;
        $this->dispatch('notify', message: 'Campagne enregistrée.');
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $archiveRecord->handle($this->campaign, ! $this->campaign->isArchived());
        $this->dispatch('notify', message: $this->campaign->isArchived() ? 'Campagne archivée.' : 'Campagne restaurée.');
    }

    public function openImport(): void
    {
        $this->importForm->reset();
        $this->importForm->resetValidation();
        $this->importForm->scanned_on = ($this->campaign->analyzed_on ?? now())->toDateString();
        $this->showImport = true;
    }

    public function import(ImportToolResults $importToolResults): void
    {
        $data = $this->importForm->data();

        try {
            $run = $importToolResults->handle($this->campaign, $data['format'], $data['contents'], $data['file_name'], $data['options']);
        } catch (DomainException $exception) {
            $this->addError('importForm.file', $exception->getMessage());

            return;
        }

        $this->showImport = false;
        $this->importForm->reset();
        $this->status = FindingStatus::ToVerify->value;
        $this->resetPage();
        $this->dispatch('notify', message: sprintf(
            '%s : %d résultat%s lu%s, %d nouveau%s à vérifier, %d déjà connu%s.',
            $run->toolLabel(), $run->results_count, $run->results_count > 1 ? 's' : '', $run->results_count > 1 ? 's' : '',
            $run->new_count, $run->new_count > 1 ? 'x' : '', $run->duplicate_count, $run->duplicate_count > 1 ? 's' : '',
        ));
    }

    public function createTestRun(): void
    {
        $this->testForm->reset();
        $this->testForm->resetValidation();
        $this->testForm->executed_on = ($this->campaign->analyzed_on ?? now())->toDateString();
        $this->showTestForm = true;
    }

    public function editTestRun(int $testRunId): void
    {
        $this->testForm->setTestRun($this->testRun($testRunId));
        $this->showTestForm = true;
    }

    /**
     * Reads the uploaded JUnit or coverage report and fills the fields, which stay editable.
     */
    public function updated(string $property): void
    {
        if ($property === 'testForm.junit_file' && $this->testForm->junit_file instanceof TemporaryUploadedFile) {
            $this->readTestReport($this->testForm->junit_file, 'testForm.junit_file', function (string $contents, string $name): void {
                $counts = TestReport::junit($contents);
                $this->testForm->tests_total = (string) $counts['tests_total'];
                $this->testForm->tests_passed = (string) $counts['tests_passed'];
                $this->testForm->tests_failed = (string) $counts['tests_failed'];
                $this->testForm->tests_skipped = (string) $counts['tests_skipped'];
                $this->testForm->source_file_name = $name;
                $this->testForm->source_sha256 = hash('sha256', $contents);
            });
        }

        if ($property === 'testForm.coverage_file' && $this->testForm->coverage_file instanceof TemporaryUploadedFile) {
            $this->readTestReport($this->testForm->coverage_file, 'testForm.coverage_file', function (string $contents): void {
                $this->testForm->line_coverage = (string) TestReport::coverage($contents);
            });
        }

        if (in_array($property, ['status', 'level', 'toolRun', 'search'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function saveTestRun(SaveTestRun $saveTestRun): void
    {
        try {
            $saveTestRun->handle($this->campaign, $this->testForm->data(), $this->testForm->testRun);
        } catch (DomainException $exception) {
            $this->addError('testForm.tests_total', $exception->getMessage());

            return;
        }

        $this->showTestForm = false;
        $this->dispatch('notify', message: 'Exécution de tests enregistrée.');
    }

    public function toggleTestRunArchive(int $testRunId, ArchiveRecord $archiveRecord): void
    {
        $testRun = $this->testRun($testRunId);
        $archiveRecord->handle($testRun, ! $testRun->isArchived());
    }

    /**
     * @param  list<int>  $ids
     */
    public function selectPage(array $ids): void
    {
        $this->selected = array_values(array_unique([...$this->selected, ...array_map('strval', $ids)]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function openReject(): void
    {
        $this->rejectReason = '';
        $this->resetValidation();
        $this->showReject = true;
    }

    public function reject(RejectFindings $rejectFindings): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'max:2000']], [], ['rejectReason' => 'motif']);
        $this->triage(fn (): int => $rejectFindings->handle($this->campaign, $this->selectedIds(), $this->rejectReason),
            fn (int $count): string => $count > 1 ? "{$count} résultats rejetés comme faux positifs." : '1 résultat rejeté comme faux positif.', 'rejectReason');
        $this->showReject = $this->getErrorBag()->isNotEmpty();
    }

    /**
     * Opens the problem form, prefilled from the selected results.
     */
    public function openCreateIssue(): void
    {
        $findings = DiagnosticFinding::query()->where('diagnostic_campaign_id', $this->campaign->id)->whereKey($this->selectedIds())->with('toolRun')->orderBy('id')->get();
        if ($findings->isEmpty()) {
            $this->dispatch('notify', message: 'Sélectionnez au moins un résultat.');

            return;
        }

        $this->issueForm->reset();
        $this->issueForm->resetValidation();
        $this->implementationIds = [];
        $first = $findings->first();
        $this->issueForm->title = Str::limit($first->rule_id !== null ? $first->rule_id.' : '.$first->message : $first->message, 200, '…');
        $this->issueForm->type = $this->guessType($findings);
        $this->issueForm->severity = $findings->contains(fn (DiagnosticFinding $finding): bool => $finding->level === FindingLevel::Error) ? IssueSeverity::Major->value : IssueSeverity::Minor->value;
        $this->issueForm->description = $this->describe($findings);
        $this->showCreateIssue = true;
    }

    public function createIssue(RetainFindingsInNewIssue $retainInNewIssue): void
    {
        $data = $this->issueForm->data();
        $this->validate(['implementationIds' => ['required', 'array', 'min:1']], ['implementationIds.required' => 'Choisissez au moins un élément concerné.'], ['implementationIds' => 'éléments']);

        try {
            $issue = $retainInNewIssue->handle($this->campaign, $this->selectedIds(), $data, array_map('intval', $this->implementationIds));
        } catch (DomainException $exception) {
            $this->addError('implementationIds', $exception->getMessage());

            return;
        }

        $this->showCreateIssue = false;
        $this->selected = [];
        $this->dispatch('notify', message: "Problème {$issue->displayCode()} créé ; les résultats sélectionnés y sont retenus.");
    }

    public function openAttach(): void
    {
        $this->issueCode = '';
        $this->attachNote = '';
        $this->resetValidation();
        $this->showAttach = true;
    }

    public function attach(RetainFindingsInIssue $retainInIssue): void
    {
        $id = (int) preg_replace('/\D/', '', $this->issueCode);
        $issue = $id > 0 ? Issue::query()->find($id) : null;
        if ($issue === null) {
            $this->addError('issueCode', 'Aucun problème avec ce code.');

            return;
        }

        $this->triage(fn (): int => $retainInIssue->handle($this->campaign, $this->selectedIds(), $issue, $this->attachNote),
            fn (int $count): string => ($count > 1 ? "{$count} résultats retenus" : '1 résultat retenu').' dans '.$issue->displayCode().'.', 'issueCode');
        $this->showAttach = $this->getErrorBag()->isNotEmpty();
    }

    public function reopen(ReopenFindings $reopenFindings): void
    {
        $this->triage(fn (): int => $reopenFindings->handle($this->campaign, $this->selectedIds()),
            fn (int $count): string => $count > 1 ? "{$count} résultats remis à vérifier." : '1 résultat remis à vérifier.', null);
    }

    /**
     * @param  callable(): int  $action
     * @param  callable(int): string  $message
     */
    private function triage(callable $action, callable $message, ?string $errorField): void
    {
        try {
            $count = $action();
        } catch (DomainException $exception) {
            $errorField !== null ? $this->addError($errorField, $exception->getMessage()) : $this->dispatch('notify', message: $exception->getMessage());

            return;
        }

        $this->selected = [];
        $this->dispatch('notify', message: $message($count));
    }

    /**
     * @return list<int>
     */
    private function selectedIds(): array
    {
        return array_map('intval', $this->selected);
    }

    private function testRun(int $testRunId): TestRun
    {
        return TestRun::query()->where('diagnostic_campaign_id', $this->campaign->id)->findOrFail($testRunId);
    }

    /**
     * @param  callable(string, string): void  $read
     */
    private function readTestReport(TemporaryUploadedFile $file, string $field, callable $read): void
    {
        $this->resetValidation($field);

        try {
            $read((string) $file->get(), $file->getClientOriginalName());
        } catch (DomainException $exception) {
            $this->addError($field, $exception->getMessage());
        }
    }

    /**
     * @param  Collection<int, DiagnosticFinding>  $findings
     */
    private function guessType(Collection $findings): string
    {
        $text = mb_strtolower($findings->map(fn (DiagnosticFinding $finding): string => $finding->rule_id.' '.$finding->toolRun->tool_name)->implode(' '));

        return preg_match('/secur|sécur|cwe|owasp|xss|sql|inject|secret|gitleaks|trivy|cve|semgrep/u', $text) === 1 ? IssueType::Security->value : IssueType::TechDebt->value;
    }

    /**
     * @param  Collection<int, DiagnosticFinding>  $findings
     */
    private function describe(Collection $findings): string
    {
        $lines = $findings->take(20)->map(fn (DiagnosticFinding $finding): string => sprintf(
            '- %s%s%s — %s',
            $finding->displayCode(),
            $finding->rule_id !== null ? ' `'.str_replace('`', '', $finding->rule_id).'`' : '',
            $finding->location() !== null ? ' `'.str_replace('`', '', $finding->location()).'`' : '',
            $finding->message,
        ));

        return sprintf(
            "Résultats d’outils vérifiés dans la campagne %s (%s, commit %s) :\n\n%s%s",
            $this->campaign->displayCode(), $this->campaign->repository->path, $this->campaign->shortCommit(),
            $lines->implode("\n"),
            $findings->count() > 20 ? sprintf("\n- … et %d autres résultats.", $findings->count() - 20) : '',
        );
    }

    public function render(): View
    {
        $this->campaign->load('repository.version.product', 'repository.version.customer');
        $versionId = $this->campaign->repository->version_id;

        $findings = DiagnosticFinding::query()
            ->where('diagnostic_campaign_id', $this->campaign->id)
            ->with(['toolRun', 'issue', 'triager'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->level !== '', fn ($query) => $query->where('level', $this->level))
            ->when($this->toolRun !== '', fn ($query) => $query->where('tool_run_id', $this->toolRun))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('message', 'like', '%'.trim($this->search).'%')
                ->orWhere('rule_id', 'like', '%'.trim($this->search).'%')
                ->orWhere('path', 'like', '%'.trim($this->search).'%')))
            ->orderByRaw("FIELD(level, 'error', 'warning', 'note')")
            ->orderBy('path')
            ->orderBy('line_start')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        $counts = DiagnosticFinding::query()->where('diagnostic_campaign_id', $this->campaign->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.diagnostics.show', [
            'findings' => $findings,
            'counts' => $counts,
            'toolRuns' => $this->campaign->toolRuns()->with('creator')->get(),
            'testRuns' => $this->campaign->testRuns()->get(),
            'repositories' => Index::repositoryOptions(),
            'formats' => $this->enumOptions(ToolResultFormat::class),
            'findingStatuses' => $this->enumOptions(FindingStatus::class),
            'levels' => $this->enumOptions(FindingLevel::class),
            'types' => $this->enumOptions(IssueType::class),
            'severities' => $this->enumOptions(IssueSeverity::class),
            'statuses' => $this->enumOptions(IssueStatus::class),
            'implementations' => $this->showCreateIssue ? Implementation::query()
                ->where('version_id', $versionId)->whereNull('archived_at')->with('module', 'feature')->get()
                ->mapWithKeys(fn (Implementation $implementation): array => [
                    $implementation->id => $implementation->catalogItem()->displayCode().' · '.$implementation->catalogItem()->name,
                ])->sort() : collect(),
            ...($this->showCreateIssue ? IssueFieldOptions::for([$versionId]) : ['users' => [], 'issueRepositories' => [], 'issueComponents' => [], 'confidences' => []]),
        ]);
    }
}
