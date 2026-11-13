<?php

namespace App\Livewire\Concerns;

use App\Actions\CodeSnippets\SaveCodeSnippet;
use App\Actions\Referential\ArchiveRecord;
use App\Livewire\Forms\CodeSnippetForm;
use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Issue;
use App\Models\Repository;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Snippet list and modal shared by the business rule and issue pages.
 *
 * @property CodeSnippetForm $snippetForm
 */
trait ManagesCodeSnippets
{
    public CodeSnippetForm $snippetForm;

    public bool $showSnippetForm = false;

    abstract protected function snippetOwner(): BusinessRule|Issue;

    public function createSnippet(string $kind = 'finding'): void
    {
        $this->snippetForm->reset();
        $this->snippetForm->resetValidation();
        $this->snippetForm->kind = $kind === 'solution' ? 'solution' : 'finding';
        $this->showSnippetForm = true;
    }

    public function editSnippet(int $snippetId): void
    {
        $this->snippetForm->setSnippet($this->snippetOwner()->codeSnippets()->findOrFail($snippetId));
        $this->showSnippetForm = true;
    }

    public function saveSnippet(SaveCodeSnippet $saveCodeSnippet): void
    {
        try {
            $saveCodeSnippet->handle($this->snippetOwner(), $this->snippetForm->data(), $this->snippetForm->snippet);
        } catch (DomainException $exception) {
            $this->addError('snippetForm.repository_id', $exception->getMessage());

            return;
        }

        $this->showSnippetForm = false;
        $this->dispatch('notify', message: 'Extrait de code enregistré.');
    }

    public function toggleSnippetArchive(int $snippetId, ArchiveRecord $archiveRecord): void
    {
        $snippet = $this->snippetOwner()->codeSnippets()->findOrFail($snippetId);
        $archiveRecord->handle($snippet, ! $snippet->isArchived());
    }

    /**
     * @return Collection<int, CodeSnippet>
     */
    protected function snippets(): Collection
    {
        return $this->snippetOwner()->codeSnippets()->whereNull('archived_at')->with('repository')->orderBy('kind')->orderBy('id')->get();
    }

    /**
     * Repositories of the versions the owner concerns, for the snippet anchor.
     *
     * @return Collection<int, string>
     */
    protected function snippetRepositoryOptions(): Collection
    {
        $owner = $this->snippetOwner();
        $versionIds = $owner instanceof BusinessRule
            ? [$owner->implementation->version_id]
            : $owner->implementations()->pluck('version_id')->all();

        return Repository::query()->whereIn('version_id', $versionIds)->whereNull('archived_at')->orderBy('path')->pluck('path', 'id');
    }
}
