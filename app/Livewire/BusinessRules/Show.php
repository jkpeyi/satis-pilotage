<?php

namespace App\Livewire\BusinessRules;

use App\Actions\BusinessRules\CopyBusinessRuleToVersions;
use App\Actions\BusinessRules\SaveBusinessRule;
use App\Actions\Referential\ArchiveRecord;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Enums\SnippetKind;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Concerns\ManagesCodeSnippets;
use App\Livewire\Forms\BusinessRuleForm;
use App\Models\BusinessRule;
use App\Models\Version;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Business rule page (EF-207): content, code snippets, copies in other versions (RG-11).
 */
class Show extends Component
{
    use EnumOptions, ManagesCodeSnippets;

    public BusinessRule $rule;

    public BusinessRuleForm $form;

    /** @var list<string> */
    public array $copyVersionIds = [];

    public bool $copyAttachMissing = true;

    public function mount(): void
    {
        $this->form->setRule($this->rule);
    }

    protected function snippetOwner(): BusinessRule
    {
        return $this->rule;
    }

    public function save(SaveBusinessRule $saveBusinessRule): void
    {
        $saveBusinessRule->handle($this->form->data($this->rule->implementation_id), $this->rule);
        $this->dispatch('notify', message: 'Règle enregistrée.');
    }

    public function copy(CopyBusinessRuleToVersions $copyRule): void
    {
        $this->validate(['copyVersionIds' => ['required', 'array', 'min:1']], [], ['copyVersionIds' => 'versions']);

        $count = $copyRule->handle($this->rule, array_map('intval', $this->copyVersionIds), $this->copyAttachMissing);

        $this->copyVersionIds = [];
        $this->dispatch('notify', message: "{$count} copie(s) créée(s).");
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $archiveRecord->handle($this->rule, ! $this->rule->isArchived());
        $this->dispatch('notify', message: $this->rule->isArchived() ? 'Règle archivée.' : 'Règle restaurée.');
    }

    public function render(): View
    {
        $this->rule->load(['implementation.version.product', 'implementation.module', 'implementation.feature', 'originRule.implementation.version', 'creator', 'editor']);
        $originId = $this->rule->origin_rule_id ?? $this->rule->id;

        $family = BusinessRule::query()
            ->where(fn ($query) => $query->where('id', $originId)->orWhere('origin_rule_id', $originId))
            ->whereKeyNot($this->rule->id)
            ->whereNull('archived_at')
            ->with(['implementation.version.product'])
            ->get();

        $versionsWithCopy = [$this->rule->implementation->version_id, ...$family->pluck('implementation.version_id')->all()];

        return view('livewire.business-rules.show', [
            'family' => $family,
            'copyCandidates' => Version::query()->whereNull('archived_at')->whereNotIn('id', $versionsWithCopy)
                ->with('product')->get()
                ->sortBy(fn (Version $version): string => $version->product->name.' '.$version->name)->values(),
            'snippets' => $this->snippets(),
            'repositoryOptions' => $this->snippetRepositoryOptions(),
            'sources' => $this->enumOptions(RuleSource::class),
            'statuses' => $this->enumOptions(RuleStatus::class),
            'snippetKinds' => $this->enumOptions(SnippetKind::class),
        ])->title($this->rule->displayCode().' · '.$this->rule->title);
    }
}
