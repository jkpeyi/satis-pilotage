{{-- Snippet list and modal; the component uses App\Livewire\Concerns\ManagesCodeSnippets. --}}
<x-ui.card title="Extraits de code">
    <x-slot:actions>
        <x-ui.button size="sm" variant="secondary" wire:click="createSnippet('finding')">Ajouter un constat</x-ui.button>
        @if ($withSolution ?? false)
            <x-ui.button size="sm" variant="secondary" wire:click="createSnippet('solution')">Ajouter une solution</x-ui.button>
        @endif
    </x-slot:actions>

    @if ($snippets->isEmpty())
        <p class="text-sm text-slate-500">Aucun extrait. Collez le code concerné pour garder une trace précise, même si le code change ensuite.</p>
    @else
        <div class="space-y-5">
            @foreach ($snippets as $snippet)
                <figure wire:key="snippet-{{ $snippet->id }}">
                    <figcaption class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :color="$snippet->kind === \App\Enums\SnippetKind::Solution ? 'green' : 'gray'">{{ $snippet->kind->label() }}</x-ui.badge>
                            <span class="font-mono">{{ $snippet->language }}</span>
                            @if ($snippet->repository) <span class="font-mono">{{ $snippet->repository->path }}</span> @endif
                            @if ($snippet->location()) <span class="font-mono text-slate-700">{{ $snippet->location() }}</span> @endif
                            @if ($snippet->isAnchored()) <span class="font-mono" title="Commit">@ {{ substr((string) $snippet->commit_sha, 0, 8) }}</span> @endif
                        </span>
                        <span>
                            <button type="button" wire:click="editSnippet({{ $snippet->id }})" class="hover:text-slate-900">Modifier</button>
                            <button type="button" wire:click="toggleSnippetArchive({{ $snippet->id }})" wire:confirm="Archiver cet extrait ?" class="ml-2 hover:text-slate-900">Archiver</button>
                        </span>
                    </figcaption>
                    <x-ui.code :code="$snippet->content" :start="$snippet->line_start ?? 1" />
                </figure>
            @endforeach
        </div>
    @endif
</x-ui.card>

<x-ui.modal wire:model="showSnippetForm" :title="$snippetForm->snippet ? 'Modifier l\'extrait' : 'Ajouter un extrait de code'">
    <form wire:submit="saveSnippet" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select label="Nature" wire:model="snippetForm.kind" :options="$snippetKinds" required />
            <x-ui.input label="Langage" wire:model="snippetForm.language" required list="snippet-languages" class="font-mono" />
            <datalist id="snippet-languages">
                @foreach (['php', 'javascript', 'typescript', 'jsx', 'sql', 'blade', 'json', 'yaml', 'bash'] as $language)
                    <option value="{{ $language }}"></option>
                @endforeach
            </datalist>
        </div>
        <x-ui.textarea label="Code" wire:model="snippetForm.content" rows="10" required class="font-mono text-xs" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select label="Dépôt" wire:model="snippetForm.repository_id" :options="$repositoryOptions" placeholder="—" />
            <x-ui.input label="Fichier" wire:model="snippetForm.path" placeholder="app/Http/Controllers/LoanController.php" class="font-mono" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input label="Ligne de début" type="number" min="1" wire:model="snippetForm.line_start" />
            <x-ui.input label="Ligne de fin" type="number" min="1" wire:model="snippetForm.line_end" />
        </div>
        <div class="flex gap-2">
            <x-ui.button type="submit">Enregistrer</x-ui.button>
            <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
        </div>
    </form>
</x-ui.modal>
