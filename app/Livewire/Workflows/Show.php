<?php

namespace App\Livewire\Workflows;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Workflows\ArchiveWorkflowStep;
use App\Actions\Workflows\MoveWorkflowStep;
use App\Actions\Workflows\SaveWorkflow;
use App\Actions\Workflows\SaveWorkflowStep;
use App\Livewire\Forms\WorkflowForm;
use App\Livewire\Forms\WorkflowStepForm;
use App\Models\BusinessRule;
use App\Models\Product;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Queries\ProductCatalog;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * "Fiche workflow": ordered steps, the feature that realizes each one and the rules that apply to it.
 */
class Show extends Component
{
    public Workflow $workflow;

    public WorkflowForm $form;

    public WorkflowStepForm $stepForm;

    public bool $showForm = false;

    public bool $showStepForm = false;

    public bool $showArchivedSteps = false;

    public function edit(): void
    {
        $this->form->setWorkflow($this->workflow);
        $this->showForm = true;
    }

    public function save(SaveWorkflow $saveWorkflow): void
    {
        $this->workflow = $saveWorkflow->handle($this->form->data(), $this->workflow);
        $this->showForm = false;
        $this->dispatch('notify', message: 'Workflow enregistré.');
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $archiveRecord->handle($this->workflow, ! $this->workflow->isArchived());
        session()->flash('status', $this->workflow->isArchived() ? 'Workflow archivé.' : 'Workflow restauré.');
        $this->redirectRoute('workflows.show', $this->workflow, navigate: true);
    }

    public function createStep(): void
    {
        $this->stepForm->forWorkflow($this->workflow);
        $this->showStepForm = true;
    }

    public function editStep(int $stepId): void
    {
        $this->stepForm->setStep($this->findStep($stepId));
        $this->showStepForm = true;
    }

    public function updatedStepFormFeatureId(): void
    {
        // Rules are offered for the chosen feature: keep only those still offered.
        $offered = $this->candidateRules()->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $this->stepForm->business_rule_ids = array_values(array_intersect($this->stepForm->business_rule_ids, $offered));
    }

    public function saveStep(SaveWorkflowStep $saveStep): void
    {
        try {
            $saveStep->handle($this->stepForm->data(), $this->stepForm->step);
        } catch (DomainException $exception) {
            $this->addError('stepForm.business_rule_ids', $exception->getMessage());

            return;
        }

        $this->showStepForm = false;
        $this->dispatch('notify', message: 'Étape enregistrée.');
    }

    public function moveStep(int $stepId, int $direction, MoveWorkflowStep $moveStep): void
    {
        $moveStep->handle($this->findStep($stepId), $direction);
    }

    public function toggleStepArchive(int $stepId, ArchiveWorkflowStep $archiveStep): void
    {
        $step = $this->findStep($stepId);
        $archiveStep->handle($step, ! $step->isArchived());
    }

    public function render(): View
    {
        $this->workflow->load(['product', 'creator', 'editor']);
        $catalog = ProductCatalog::load();

        $steps = $this->workflow->steps()
            ->with(['feature', 'businessRules' => fn ($query) => $query->whereNull('business_rules.archived_at')->with('implementation.version')])
            ->when(! $this->showArchivedSteps, fn ($query) => $query->whereNull('archived_at'))
            ->get();

        return view('livewire.workflows.show', [
            'steps' => $steps,
            'featureLabels' => $steps->pluck('feature_id')->filter()->unique()
                ->mapWithKeys(fn (int $featureId): array => [$featureId => $catalog->featureLabel($featureId)]),
            'featureOptions' => collect($catalog->featureIds($this->workflow->product_id))
                ->mapWithKeys(fn (int $featureId): array => [$featureId => $catalog->featureLabel($featureId)]),
            'candidateRules' => $this->showStepForm ? $this->candidateRules($catalog) : collect(),
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
        ])->title($this->workflow->name);
    }

    /**
     * Rules of the product's versions on the chosen feature or on one of its parent modules.
     *
     * @return Collection<int, BusinessRule>
     */
    private function candidateRules(?ProductCatalog $catalog = null): Collection
    {
        if ($this->stepForm->feature_id === '') {
            return collect();
        }

        $featureId = (int) $this->stepForm->feature_id;
        $moduleIds = ($catalog ?? ProductCatalog::load())->tree->featureAncestorModuleIds($featureId);

        return BusinessRule::query()
            ->whereNull('business_rules.archived_at')
            ->whereHas('implementation', fn ($query) => $query
                ->where(fn ($query) => $query->where('feature_id', $featureId)->orWhereIn('module_id', $moduleIds))
                ->whereHas('version', fn ($query) => $query->where('product_id', $this->workflow->product_id)))
            ->with('implementation.version')
            ->orderBy('id')
            ->get();
    }

    private function findStep(int $stepId): WorkflowStep
    {
        return $this->workflow->steps()->findOrFail($stepId);
    }
}
