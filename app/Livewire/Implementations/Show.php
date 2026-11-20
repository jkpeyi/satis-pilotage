<?php

namespace App\Livewire\Implementations;

use App\Actions\BusinessRules\SaveBusinessRule;
use App\Actions\Implementations\UpdateImplementationAssessment;
use App\Actions\Issues\SaveIssue;
use App\Enums\FeatureOperation;
use App\Enums\FeaturePresence;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Enums\VerificationStatus;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Concerns\IssueFieldOptions;
use App\Livewire\Forms\AssessmentForm;
use App\Livewire\Forms\BusinessRuleForm;
use App\Livewire\Forms\IssueForm;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\User;
use App\Queries\CatalogTree;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Fiche rattachement" (EF-205): how a version implements one catalog item.
 */
class Show extends Component
{
    use EnumOptions;

    public Implementation $implementation;

    public AssessmentForm $assessment;

    public BusinessRuleForm $ruleForm;

    public IssueForm $issueForm;

    public bool $showRuleForm = false;

    public bool $showIssueForm = false;

    public string $issueCode = '';

    public function mount(): void
    {
        $this->assessment->setImplementation($this->implementation);
    }

    public function saveAssessment(UpdateImplementationAssessment $updateAssessment): void
    {
        $updateAssessment->handle($this->implementation, $this->assessment->data(), $this->user());
        $this->implementation->refresh();
        $this->dispatch('notify', message: 'Évaluation enregistrée.');
    }

    public function createRule(): void
    {
        $this->ruleForm->reset();
        $this->ruleForm->resetValidation();
        $this->showRuleForm = true;
    }

    public function saveRule(SaveBusinessRule $saveBusinessRule): void
    {
        $rule = $saveBusinessRule->handle($this->ruleForm->data($this->implementation->id));
        $this->showRuleForm = false;
        $this->dispatch('notify', message: "Règle {$rule->displayCode()} créée.");
    }

    public function createIssue(): void
    {
        $this->issueForm->reset();
        $this->issueForm->resetValidation();
        $this->showIssueForm = true;
    }

    public function saveIssue(SaveIssue $saveIssue): void
    {
        try {
            $issue = $saveIssue->handle($this->issueForm->data(), null, [$this->implementation->id]);
        } catch (DomainException $exception) {
            $this->addError('issueForm.repository_id', $exception->getMessage());

            return;
        }

        $this->showIssueForm = false;
        $this->dispatch('notify', message: "Problème {$issue->displayCode()} créé.");
    }

    /**
     * Links an existing issue, given its code (PB-0042) or its number.
     */
    public function linkIssueByCode(): void
    {
        $id = (int) preg_replace('/\D/', '', $this->issueCode);
        $issue = $id > 0 ? Issue::query()->whereNull('archived_at')->find($id) : null;

        if ($issue === null) {
            $this->addError('issueCode', 'Aucun problème actif avec ce code.');

            return;
        }

        $this->linkIssue($issue->id);
        $this->issueCode = '';
    }

    public function linkIssue(int $issueId): void
    {
        Issue::query()->findOrFail($issueId)->implementations()->syncWithoutDetaching([$this->implementation->id]);
        $this->dispatch('notify', message: 'Problème lié à cet élément.');
    }

    public function updatedAssessmentPresence(): void
    {
        $this->assessment->syncOperation();
    }

    public function render(): View
    {
        $this->implementation->load(['version.product', 'version.customer', 'module', 'feature', 'assessor', 'verifier', 'statusChanges.user']);
        $item = $this->implementation->catalogItem();
        $tree = CatalogTree::load(withArchived: true);
        $ancestorIds = $this->implementation->module_id !== null
            ? $tree->ancestorModuleIds($this->implementation->module_id)
            : $tree->featureAncestorModuleIds((int) $this->implementation->feature_id);

        return view('livewire.implementations.show', [
            'item' => $item,
            'path' => array_map(fn (int $id): string => $tree->modules[$id]['name'] ?? '?', array_reverse($ancestorIds)),
            'rules' => $this->implementation->businessRules()->whereNull('archived_at')->withCount(['codeSnippets' => fn ($query) => $query->whereNull('archived_at')])->orderBy('id')->get(),
            'issues' => $this->implementation->issues()->whereNull('issues.archived_at')->orderBy('issues.id')->get(),
            'knownIssues' => $this->knownIssuesElsewhere(),
            'presences' => $this->enumOptions(FeaturePresence::class),
            'operations' => $this->enumOptions(FeatureOperation::class),
            'verificationStatuses' => $this->enumOptions(VerificationStatus::class),
            'sources' => $this->enumOptions(RuleSource::class),
            'ruleStatuses' => $this->enumOptions(RuleStatus::class),
            'issueTypes' => $this->enumOptions(IssueType::class),
            'severities' => $this->enumOptions(IssueSeverity::class),
            'issueStatuses' => $this->enumOptions(IssueStatus::class),
            ...IssueFieldOptions::for([$this->implementation->version_id]),
        ])->title($item->name.' · '.$this->implementation->version->name);
    }

    /**
     * Open issues on the same catalog item in other versions: the same bug often exists elsewhere.
     *
     * @return Collection<int, Issue>
     */
    private function knownIssuesElsewhere(): Collection
    {
        $column = $this->implementation->module_id !== null ? 'module_id' : 'feature_id';
        $itemId = $this->implementation->module_id ?? $this->implementation->feature_id;

        return Issue::query()
            ->whereNull('archived_at')
            ->whereIn('status', IssueStatus::openValues())
            ->whereHas('implementations', fn ($query) => $query->where($column, $itemId)->where('implementations.id', '!=', $this->implementation->id))
            ->whereDoesntHave('implementations', fn ($query) => $query->where('implementations.id', $this->implementation->id))
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
