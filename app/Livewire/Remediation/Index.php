<?php

namespace App\Livewire\Remediation;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Remediation\SaveRemediationAction;
use App\Actions\Remediation\SaveWorkPackage;
use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Enums\WorkPackageStatus;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\RemediationActionForm;
use App\Livewire\Forms\WorkPackageForm;
use App\Models\Issue;
use App\Models\Product;
use App\Models\RemediationAction;
use App\Models\User;
use App\Models\WorkPackage;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Remediation roadmap of a product (L8): work packages with their milestone, and actions treating
 * the problems of the debt register.
 */
#[Title('Plan d’actions')]
class Index extends Component
{
    use EnumOptions;

    public RemediationActionForm $form;

    public WorkPackageForm $packageForm;

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $package = '';

    #[Url(except: '')]
    public string $owner = '';

    #[Url(except: false)]
    public bool $lateOnly = false;

    /** Issue to plan, from the problem page. */
    #[Url(except: '')]
    public string $issue = '';

    public bool $showForm = false;

    public bool $showPackageForm = false;

    public function mount(): void
    {
        if ($this->product === '') {
            $this->product = (string) Product::query()->whereNull('archived_at')->orderBy('name')->value('id');
        }

        if ($this->issue !== '' && ($issue = Issue::query()->find((int) $this->issue)) !== null) {
            $this->create();
            $this->form->issue_id = (string) $issue->id;
            $this->form->title = 'Traiter '.$issue->displayCode().' · '.$issue->title;
            $this->form->gitlab_issue_url = (string) $issue->gitlab_issue_url;
            $this->form->priority = match ($issue->severity->value) {
                'blocking' => 'p1',
                'major' => 'p2',
                default => 'p3',
            };
            $this->issue = '';
        }
    }

    public function create(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->form->product_id = $this->product;
        $this->form->work_package_id = $this->package;
        $this->showForm = true;
    }

    public function edit(int $actionId): void
    {
        $this->form->setAction(RemediationAction::query()->findOrFail($actionId));
        $this->showForm = true;
    }

    public function save(SaveRemediationAction $saveAction): void
    {
        $data = $this->form->data();

        try {
            $action = $saveAction->handle($data['data'], $this->form->action, $data['dependency_ids']);
        } catch (DomainException $exception) {
            $this->addError('form.save', $exception->getMessage());

            return;
        }

        $this->showForm = false;
        $this->dispatch('notify', message: "Action {$action->displayCode()} enregistrée.");
    }

    public function toggleArchive(int $actionId, ArchiveRecord $archiveRecord): void
    {
        $action = RemediationAction::query()->findOrFail($actionId);
        $archiveRecord->handle($action, ! $action->isArchived());
    }

    public function createPackage(): void
    {
        $this->packageForm->reset();
        $this->packageForm->resetValidation();
        $this->packageForm->product_id = $this->product;
        $this->showPackageForm = true;
    }

    public function editPackage(int $packageId): void
    {
        $this->packageForm->setWorkPackage(WorkPackage::query()->findOrFail($packageId));
        $this->showPackageForm = true;
    }

    public function savePackage(SaveWorkPackage $savePackage): void
    {
        try {
            $package = $savePackage->handle($this->packageForm->data(), $this->packageForm->workPackage);
        } catch (DomainException $exception) {
            $this->addError('packageForm.name', $exception->getMessage());

            return;
        }

        $this->showPackageForm = false;
        $this->dispatch('notify', message: "Lot {$package->displayCode()} enregistré.");
    }

    public function updatedProduct(): void
    {
        $this->package = '';
    }

    public function render(): View
    {
        $productId = (int) $this->product;
        $formProductId = (int) ($this->form->product_id !== '' ? $this->form->product_id : $this->product);

        $actions = RemediationAction::query()
            ->where('product_id', $productId)
            ->whereNull('archived_at')
            ->with(['issue', 'owner', 'workPackage', 'dependencies'])
            ->when($this->status === 'open', fn ($query) => $query->whereIn('status', ['todo', 'in_progress', 'blocked']))
            ->when(! in_array($this->status, ['', 'open'], true), fn ($query) => $query->where('status', $this->status))
            ->when($this->package === 'none', fn ($query) => $query->whereNull('work_package_id'))
            ->when(! in_array($this->package, ['', 'none'], true), fn ($query) => $query->where('work_package_id', $this->package))
            ->when($this->owner === 'none', fn ($query) => $query->whereNull('owner_id'))
            ->when(! in_array($this->owner, ['', 'none'], true), fn ($query) => $query->where('owner_id', $this->owner))
            ->when($this->lateOnly, fn ($query) => $query->whereIn('status', ['todo', 'in_progress', 'blocked'])->where('due_on', '<', today()))
            ->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('priority')->orderBy('id')
            ->get();

        $packages = WorkPackage::query()->where('product_id', $productId)->whereNull('archived_at')
            ->withCount([
                'actions as actions_total' => fn ($query) => $query->whereNull('archived_at')->where('status', '!=', 'cancelled'),
                'actions as actions_done' => fn ($query) => $query->whereNull('archived_at')->where('status', 'done'),
            ])
            ->withSum(['actions as effort' => fn ($query) => $query->whereNull('archived_at')->where('status', '!=', 'cancelled')], 'effort_days')
            ->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('position')
            ->get();

        return view('livewire.remediation.index', [
            'actions' => $actions,
            'packages' => $packages,
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => $this->enumOptions(ActionStatus::class),
            'priorities' => $this->enumOptions(ActionPriority::class),
            'packageStatuses' => $this->enumOptions(WorkPackageStatus::class),
            'packageOptions' => WorkPackage::query()->where('product_id', $formProductId)->whereNull('archived_at')->orderBy('name')->get()
                ->mapWithKeys(fn (WorkPackage $package): array => [$package->id => $package->displayCode().' · '.$package->name]),
            'issueOptions' => Issue::query()->whereNull('archived_at')
                ->whereHas('implementations.version', fn ($query) => $query->where('product_id', $formProductId))
                ->orderBy('id')->get()
                ->mapWithKeys(fn (Issue $issue): array => [$issue->id => $issue->displayCode().' · '.$issue->title.' ('.mb_strtolower($issue->status->label()).')']),
            'dependencyOptions' => RemediationAction::query()->where('product_id', $formProductId)->whereNull('archived_at')
                ->when($this->form->action !== null, fn ($query) => $query->whereKeyNot($this->form->action?->id))
                ->orderBy('id')->get()
                ->mapWithKeys(fn (RemediationAction $action): array => [$action->id => $action->displayCode().' · '.$action->title]),
        ]);
    }
}
