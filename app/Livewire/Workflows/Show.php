<?php

namespace App\Livewire\Workflows;

use App\Actions\Catalog\CreateProductFeature;
use App\Actions\Referential\ArchiveRecord;
use App\Actions\Workflows\ArchiveWorkflowStep;
use App\Actions\Workflows\MoveWorkflowStep;
use App\Actions\Workflows\SaveWorkflow;
use App\Actions\Workflows\SaveWorkflowStep;
use App\Enums\WorkflowStepKind;
use App\Exceptions\DuplicateCatalogItem;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\WorkflowForm;
use App\Livewire\Forms\WorkflowStepForm;
use App\Models\BusinessRule;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Product;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Queries\CatalogSearch;
use App\Queries\ProductCatalog;
use App\Queries\WorkflowCoverage;
use App\Queries\WorkflowGraph;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Fiche workflow": ordered steps, the feature that realizes each one and the rules that apply to it.
 */
class Show extends Component
{
    use EnumOptions;

    public Workflow $workflow;

    public WorkflowForm $form;

    public WorkflowStepForm $stepForm;

    public bool $showForm = false;

    public bool $showStepForm = false;

    public bool $showArchivedSteps = false;

    /** Version whose coverage colors the diagram; empty for the plain diagram. */
    #[Url(as: 'version', except: '')]
    public string $coverageVersion = '';

    /** Feature search in the step form: the catalog suggests existing features (RG-15). */
    public string $featureSearch = '';

    public string $newFeatureModuleId = '';

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
        $this->resetFeatureSearch();
        $this->stepForm->forWorkflow($this->workflow);
        $this->showStepForm = true;
    }

    public function editStep(int $stepId): void
    {
        $this->resetFeatureSearch();
        $this->stepForm->setStep($this->findStep($stepId));
        $this->showStepForm = true;
    }

    /**
     * Picks a feature of the catalog for the step.
     */
    public function chooseFeature(int $featureId): void
    {
        abort_unless(Feature::query()->whereKey($featureId)->whereNull('archived_at')->exists(), 404);

        $this->stepForm->feature_id = (string) $featureId;
        $this->resetFeatureSearch();
        $this->updatedStepFormFeatureId();
    }

    public function clearFeature(): void
    {
        $this->stepForm->feature_id = '';
        $this->updatedStepFormFeatureId();
    }

    /**
     * The typed feature does not exist: creates it in the catalog, under the chosen module, and
     * picks it for the step.
     */
    public function createFeature(CreateProductFeature $createFeature): void
    {
        $this->validate([
            'featureSearch' => ['required', 'string', 'max:255'],
            'newFeatureModuleId' => ['required', 'integer', Rule::exists('modules', 'id')->whereNull('archived_at')],
        ], [], ['featureSearch' => 'fonctionnalité', 'newFeatureModuleId' => 'module']);

        try {
            $feature = $createFeature->handle($this->workflow->product, $this->featureSearch, (int) $this->newFeatureModuleId);
        } catch (DuplicateCatalogItem $exception) {
            $this->addError('featureSearch', $exception->getMessage());

            return;
        }

        $this->chooseFeature($feature->id);
        $this->dispatch('notify', message: "Fonctionnalité « {$feature->name} » créée dans le catalogue.");
    }

    private function resetFeatureSearch(): void
    {
        $this->featureSearch = '';
        $this->newFeatureModuleId = '';
        $this->resetValidation(['featureSearch', 'newFeatureModuleId']);
    }

    public function updatedStepFormFeatureId(): void
    {
        // Rules are offered for the chosen feature: keep only those still offered.
        $offered = $this->candidateRules()->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $this->stepForm->business_rule_ids = array_values(array_intersect($this->stepForm->business_rule_ids, $offered));
    }

    public function updatedStepFormKind(): void
    {
        $this->stepForm->normalizeTransitions();

        if ($this->stepForm->kind === 'decision' && count($this->stepForm->transitions) < 2) {
            while (count($this->stepForm->transitions) < 2) {
                $this->stepForm->addTransition();
            }
            foreach (['Oui', 'Non'] as $index => $default) {
                if (($this->stepForm->transitions[$index]['label'] ?? '') === '') {
                    $this->stepForm->transitions[$index]['label'] = $default;
                }
            }
        }
    }

    public function addTransition(): void
    {
        $this->stepForm->addTransition();
    }

    public function removeTransition(int $index): void
    {
        $this->stepForm->removeTransition($index);
    }

    public function saveStep(SaveWorkflowStep $saveStep): void
    {
        try {
            $saveStep->handle($this->stepForm->data(), $this->stepForm->step);
        } catch (DomainException $exception) {
            $this->addError('stepForm.save', $exception->getMessage());

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

        $graph = WorkflowGraph::for($this->workflow);
        $coverage = WorkflowCoverage::for($this->workflow);
        $colorVersion = $coverage->versions->firstWhere('id', (int) $this->coverageVersion);
        $productFeatureIds = $catalog->featureIds($this->workflow->product_id);

        return view('livewire.workflows.show', [
            'steps' => $steps,
            'graph' => $graph,
            'diagram' => $graph->layout(),
            'coverage' => $coverage,
            'nodeColors' => $colorVersion !== null ? $coverage->nodeColors($colorVersion) : [],
            'featureSuggestions' => $this->showStepForm ? $this->featureSuggestions($productFeatureIds) : [],
            'chosenFeature' => $this->showStepForm && $this->stepForm->feature_id !== ''
                ? ['label' => $catalog->featureLabel((int) $this->stepForm->feature_id), 'versions' => $this->featureInVersions((int) $this->stepForm->feature_id, $coverage)]
                : null,
            'moduleOptions' => $this->showStepForm && $this->featureSearch !== '' ? $this->moduleOptions($catalog) : [],
            'kinds' => $this->enumOptions(WorkflowStepKind::class),
            'targetOptions' => $this->workflow->steps()->active()
                ->when($this->stepForm->step !== null && $this->stepForm->kind === 'action', fn ($query) => $query->whereKeyNot($this->stepForm->step?->id))
                ->get()->mapWithKeys(fn (WorkflowStep $step): array => [$step->id => $step->position.'. '.$step->name]),
            'featureLabels' => $steps->pluck('feature_id')->filter()->unique()
                ->mapWithKeys(fn (int $featureId): array => [$featureId => $catalog->featureLabel($featureId)]),
            'candidateRules' => $this->showStepForm ? $this->candidateRules($catalog) : collect(),
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
        ])->title($this->workflow->name);
    }

    /**
     * Active features of the catalog matching the search, those of the product first.
     *
     * @param  list<int>  $productFeatureIds
     * @return list<array{id: int, name: string, path: string, exact: bool, in_product: bool}>
     */
    private function featureSuggestions(array $productFeatureIds): array
    {
        $found = array_values(array_filter(
            app(CatalogSearch::class)->search($this->featureSearch, 12, withArchived: false),
            fn (array $suggestion): bool => $suggestion['type'] === 'feature',
        ));
        $suggestions = array_map(fn (array $suggestion): array => [
            'id' => $suggestion['id'], 'name' => $suggestion['name'], 'path' => $suggestion['path'],
            'exact' => $suggestion['exact'], 'in_product' => in_array($suggestion['id'], $productFeatureIds, true),
        ], $found);
        usort($suggestions, fn (array $a, array $b): int => [$b['exact'], $b['in_product']] <=> [$a['exact'], $a['in_product']]);

        return array_slice($suggestions, 0, 8);
    }

    /**
     * Status of a feature in each version of the product, for the step form.
     *
     * @return list<string>
     */
    private function featureInVersions(int $featureId, WorkflowCoverage $coverage): array
    {
        $statuses = Implementation::query()->active()->where('feature_id', $featureId)
            ->whereIn('version_id', $coverage->versions->modelKeys())->pluck('status', 'version_id');

        return $coverage->versions->map(fn ($version): string => $version->name.' : '.($statuses->has($version->id)
            ? mb_strtolower($statuses[$version->id]->label())
            : 'non rattachée'))->values()->all();
    }

    /**
     * Modules where a new feature can go, by catalog path.
     *
     * @return array<int, string>
     */
    private function moduleOptions(ProductCatalog $catalog): array
    {
        $tree = $catalog->tree;
        $options = [];
        foreach ($tree->modules as $moduleId => $module) {
            $path = array_map(fn (int $id): string => $tree->modules[$id]['name'], array_reverse($tree->ancestorModuleIds($moduleId)));
            $options[$moduleId] = implode(' › ', [...$path, $module['name']]);
        }
        asort($options);

        return $options;
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
