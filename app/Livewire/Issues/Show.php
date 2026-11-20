<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\SaveIssue;
use App\Actions\Issues\UnlinkIssueFromImplementation;
use App\Actions\Referential\ArchiveRecord;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\SnippetKind;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Concerns\IssueFieldOptions;
use App\Livewire\Concerns\ManagesCodeSnippets;
use App\Livewire\Forms\IssueForm;
use App\Models\Implementation;
use App\Models\Issue;
use App\Models\Version;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Issue page (EF-208): description, solution, effort, snippets, versions concerned (RG-09).
 */
class Show extends Component
{
    use EnumOptions, ManagesCodeSnippets;

    public Issue $issue;

    public IssueForm $form;

    public string $linkVersionId = '';

    public string $linkImplementationId = '';

    public function mount(): void
    {
        $this->form->setIssue($this->issue);
    }

    protected function snippetOwner(): Issue
    {
        return $this->issue;
    }

    public function save(SaveIssue $saveIssue): void
    {
        try {
            $saveIssue->handle($this->form->data(), $this->issue);
        } catch (DomainException $exception) {
            $this->addError('form.repository_id', $exception->getMessage());

            return;
        }

        $this->dispatch('notify', message: 'Problème enregistré.');
    }

    public function updatedLinkVersionId(): void
    {
        $this->linkImplementationId = '';
    }

    public function link(): void
    {
        $this->validate(['linkImplementationId' => ['required', 'integer', 'exists:implementations,id']], [], ['linkImplementationId' => 'élément']);

        $this->issue->implementations()->syncWithoutDetaching([(int) $this->linkImplementationId]);
        $this->reset('linkImplementationId');
        $this->dispatch('notify', message: 'Élément ajouté au problème.');
    }

    /**
     * Links the issue to the same catalog item in other versions in one click.
     */
    public function linkSameItem(int $implementationId): void
    {
        $this->issue->implementations()->syncWithoutDetaching([$implementationId]);
    }

    public function unlink(int $implementationId, UnlinkIssueFromImplementation $unlink): void
    {
        try {
            $unlink->handle($this->issue, $this->issue->implementations()->findOrFail($implementationId));
        } catch (DomainException $exception) {
            $this->addError('unlink', $exception->getMessage());
        }
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $archiveRecord->handle($this->issue, ! $this->issue->isArchived());
        $this->dispatch('notify', message: $this->issue->isArchived() ? 'Problème archivé.' : 'Problème restauré.');
    }

    public function render(): View
    {
        $this->issue->load(['creator', 'editor', 'owner', 'repository', 'component']);
        $implementations = $this->issue->implementations()->with(['version.product', 'module', 'feature'])->get();

        return view('livewire.issues.show', [
            'implementations' => $implementations,
            'sameItemElsewhere' => $this->sameItemElsewhere($implementations),
            'versions' => Version::query()->whereNull('archived_at')->with('product')->get()
                ->sortBy(fn (Version $version): string => $version->product->name.' '.$version->name)
                ->mapWithKeys(fn (Version $version): array => [$version->id => $version->product->name.' · '.$version->name]),
            'versionItems' => $this->linkVersionId !== '' ? $this->itemsOfVersion((int) $this->linkVersionId, $implementations) : [],
            'snippets' => $this->snippets(),
            'findings' => $this->issue->diagnosticFindings()->with('campaign', 'toolRun')->orderBy('id')->limit(50)->get(),
            'repositoryOptions' => $this->snippetRepositoryOptions(),
            'types' => $this->enumOptions(IssueType::class),
            'severities' => $this->enumOptions(IssueSeverity::class),
            'statuses' => $this->enumOptions(IssueStatus::class),
            ...IssueFieldOptions::for($implementations->pluck('version_id')->unique()->all()),
            'snippetKinds' => $this->enumOptions(SnippetKind::class),
        ])->title($this->issue->displayCode().' · '.$this->issue->title);
    }

    /**
     * Implementations of the same catalog items in versions not linked yet.
     *
     * @param  EloquentCollection<int, Implementation>  $linked
     * @return Collection<int, Implementation>
     */
    private function sameItemElsewhere(EloquentCollection $linked): Collection
    {
        if ($linked->isEmpty()) {
            return collect();
        }

        return Implementation::query()
            ->whereNull('archived_at')
            ->whereKeyNot($linked->modelKeys())
            ->where(fn ($query) => $query
                ->whereIn('module_id', $linked->pluck('module_id')->filter()->all())
                ->orWhereIn('feature_id', $linked->pluck('feature_id')->filter()->all()))
            ->with(['version.product', 'module', 'feature'])
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Implementation>  $linked
     * @return array<int, string>
     */
    private function itemsOfVersion(int $versionId, EloquentCollection $linked): array
    {
        return Implementation::query()
            ->where('version_id', $versionId)
            ->whereNull('archived_at')
            ->whereKeyNot($linked->modelKeys())
            ->with(['module', 'feature'])
            ->get()
            ->mapWithKeys(fn (Implementation $implementation): array => [
                $implementation->id => $implementation->catalogItem()->typeLabel().' · '.$implementation->catalogItem()->name,
            ])
            ->sort()
            ->all();
    }
}
