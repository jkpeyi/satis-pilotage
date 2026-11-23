<?php

namespace App\Livewire\Strategies;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Strategy\ArchiveStrategyItem;
use App\Actions\Strategy\ChangeStrategyApproval;
use App\Actions\Strategy\SaveRefactoringStrategy;
use App\Actions\Strategy\SaveStrategyDecision;
use App\Actions\Strategy\SaveStrategyItem;
use App\Enums\ApprovalStatus;
use App\Enums\DecisionStatus;
use App\Enums\RiskLevel;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\StrategyForm;
use App\Livewire\Forms\StrategyItemForm;
use App\Models\Issue;
use App\Models\Product;
use App\Models\RefactoringStrategy;
use App\Models\StrategyDecision;
use App\Models\StrategyOption;
use App\Models\StrategyPhase;
use App\Models\StrategyRisk;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * A refactoring strategy (L7): content, scenarios, migration phases, risks, decisions and the
 * approval circuit (RG-20).
 */
class Show extends Component
{
    use EnumOptions;

    public RefactoringStrategy $strategy;

    public StrategyForm $form;

    public StrategyItemForm $itemForm;

    public bool $showForm = false;

    public bool $showItemForm = false;

    public bool $showReject = false;

    public string $approvalNote = '';

    public function edit(): void
    {
        $this->form->setStrategy($this->strategy);
        $this->showForm = true;
    }

    public function save(SaveRefactoringStrategy $saveStrategy): void
    {
        $data = $this->form->data();

        try {
            $saveStrategy->handle($data['data'], $this->strategy, $data['issue_ids']);
        } catch (DomainException $exception) {
            $this->addError('form.issue_ids', $exception->getMessage());

            return;
        }

        $this->strategy->refresh();
        $this->showForm = false;
        $this->dispatch('notify', message: 'Stratégie enregistrée.');
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $archiveRecord->handle($this->strategy, ! $this->strategy->isArchived());
    }

    /**
     * $kind comes from the page: option, phase, risk or decision.
     */
    public function createItem(string $kind): void
    {
        $this->itemForm->start(match ($kind) {
            'phase' => 'phase',
            'risk' => 'risk',
            'decision' => 'decision',
            default => 'option',
        });
        $this->showItemForm = true;
    }

    public function editItem(string $kind, int $id): void
    {
        $this->itemForm->setItem($this->findItem($kind, $id));
        $this->showItemForm = true;
    }

    public function saveItem(SaveStrategyItem $saveItem, SaveStrategyDecision $saveDecision): void
    {
        $data = $this->itemForm->data();
        $kind = $this->itemForm->kind;

        try {
            if ($kind === 'decision') {
                $existing = $this->itemForm->itemId !== null ? $this->findItem('decision', $this->itemForm->itemId) : null;
                /** @var StrategyDecision|null $existing */
                $saveDecision->handle($this->strategy, [
                    'strategy_option_id' => $data['strategy_option_id'],
                    'title' => $data['title'],
                    'rationale' => $data['rationale'],
                    'status' => $data['status'],
                    'decided_on' => $data['decided_on'],
                ], $existing);
            } else {
                $item = $this->itemForm->itemId !== null ? $this->findItem($kind, $this->itemForm->itemId) : match ($kind) {
                    'phase' => new StrategyPhase,
                    'risk' => new StrategyRisk,
                    default => new StrategyOption,
                };
                /** @var StrategyOption|StrategyPhase|StrategyRisk $item */
                $saveItem->handle($this->strategy, $item, $data);
            }
        } catch (DomainException $exception) {
            $this->addError('itemForm.name', $exception->getMessage());

            return;
        }

        $this->strategy->refresh();
        $this->showItemForm = false;
        $this->dispatch('notify', message: 'Enregistré.');
    }

    public function archiveItem(string $kind, int $id, ArchiveStrategyItem $archiveItem): void
    {
        $item = $this->findItem($kind, $id);
        $archiveItem->handle($item, ! $item->isArchived());
        $this->strategy->refresh();
    }

    public function changeApproval(string $target, ChangeStrategyApproval $changeApproval): void
    {
        $status = ApprovalStatus::tryFrom($target);
        $user = Auth::user();
        if ($status === null || ! $user instanceof User) {
            return;
        }

        if ($status === ApprovalStatus::Rejected && ! $this->showReject) {
            $this->approvalNote = '';
            $this->showReject = true;

            return;
        }

        try {
            $changeApproval->handle($this->strategy, $status, $user, $this->approvalNote !== '' ? $this->approvalNote : null);
        } catch (DomainException $exception) {
            $this->showReject ? $this->addError('approvalNote', $exception->getMessage()) : $this->dispatch('notify', message: $exception->getMessage());

            return;
        }

        $this->showReject = false;
        $this->approvalNote = '';
        $this->strategy->refresh();
        $this->dispatch('notify', message: 'Stratégie : '.mb_strtolower($status->label()).'.');
    }

    private function findItem(string $kind, int $id): StrategyOption|StrategyPhase|StrategyRisk|StrategyDecision
    {
        $query = match ($kind) {
            'phase' => StrategyPhase::query(),
            'risk' => StrategyRisk::query(),
            'decision' => StrategyDecision::query(),
            default => StrategyOption::query(),
        };

        return $query->where('refactoring_strategy_id', $this->strategy->id)->findOrFail($id);
    }

    public function render(): View
    {
        $this->strategy->load(['product', 'approver', 'issues', 'options', 'phases', 'risks.owner', 'decisions.option', 'decisions.decider']);
        $productId = (int) ($this->form->product_id !== '' ? $this->form->product_id : $this->strategy->product_id);

        return view('livewire.strategies.show', [
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'issueOptions' => Issue::query()->whereNull('archived_at')
                ->whereHas('implementations.version', fn ($query) => $query->where('product_id', $productId))
                ->orderByRaw("FIELD(severity, 'blocking', 'major', 'minor')")->orderBy('id')->get()
                ->mapWithKeys(fn (Issue $issue): array => [$issue->id => $issue->displayCode().' · '.$issue->title.' ('.mb_strtolower($issue->severity->label()).')']),
            'optionOptions' => $this->strategy->options->whereNull('archived_at')
                ->mapWithKeys(fn (StrategyOption $option): array => [$option->id => $option->displayCode().' · '.$option->name]),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'levels' => $this->enumOptions(RiskLevel::class),
            'decisionStatuses' => $this->enumOptions(DecisionStatus::class),
        ])->title($this->strategy->displayCode().' · '.$this->strategy->title);
    }
}
