<div>
    <x-ui.page-header :title="$issue->title">
        <x-slot:breadcrumbs>
            <a href="{{ route('issues.index') }}" wire:navigate class="hover:text-slate-900">Problèmes</a>
            / <span class="font-mono">{{ $issue->displayCode() }}</span>
            @if ($issue->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button variant="ghost" wire:click="toggleArchive" wire:confirm="{{ $issue->isArchived() ? 'Restaurer ce problème ?' : 'Archiver ce problème ?' }}">
                {{ $issue->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
        <x-ui.severity-badge :severity="$issue->severity" />
        <x-ui.badge>{{ $issue->type->label() }}</x-ui.badge>
        <x-ui.badge color="blue">{{ $issue->status->label() }}</x-ui.badge>
        @if ($issue->effortLabel()) <span class="text-slate-600">Effort estimé : <strong class="tabular-nums">{{ $issue->effortLabel() }}</strong>@if ($issue->confidence) (confiance {{ mb_strtolower($issue->confidence->label()) }})@endif</span> @endif
        @if ($issue->owner) <span class="text-slate-600">· Responsable : {{ $issue->owner->name }}</span> @endif
        @if ($issue->repository) <span class="font-mono text-xs text-slate-600">· {{ $issue->repository->path }}</span> @endif
        @if ($issue->component) <span class="text-slate-600">· {{ $issue->component->displayCode() }} {{ $issue->component->name }}</span> @endif
        @if ($issue->gitlab_issue_url) <a href="{{ $issue->gitlab_issue_url }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">Ticket GitLab</a> @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card title="Constat">
                <x-ui.markdown :text="$issue->description" />
                @if ($issue->impact)
                    <h3 class="mb-2 mt-6 text-sm font-semibold text-slate-900">Impact</h3>
                    <x-ui.markdown :text="$issue->impact" />
                @endif
                @if ($issue->proposed_solution)
                    <h3 class="mb-2 mt-6 text-sm font-semibold text-slate-900">Solution proposée</h3>
                    <x-ui.markdown :text="$issue->proposed_solution" />
                @endif
                <details class="mt-6 border-t border-slate-200 pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Modifier le problème</summary>
                    <form wire:submit="save" class="mt-4 space-y-4">
                        @include('livewire.issues.partials.fields', ['prefix' => 'form'])
                        <x-ui.button type="submit">Enregistrer</x-ui.button>
                    </form>
                </details>
            </x-ui.card>

            @include('livewire.partials.snippets', ['withSolution' => true])
        </div>

        <aside class="min-w-0 space-y-6">
            <x-ui.card title="Versions concernées">
                @error('unlink') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
                <ul class="space-y-2 text-sm">
                    @foreach ($implementations as $implementation)
                        <li class="flex items-start justify-between gap-2" wire:key="linked-{{ $implementation->id }}">
                            <a href="{{ route('implementations.show', $implementation) }}" wire:navigate class="min-w-0 hover:text-brand-700">
                                <span class="block text-slate-800">{{ $implementation->version->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $implementation->catalogItem()->typeLabel() }} · {{ $implementation->catalogItem()->name }}</span>
                            </a>
                            <button type="button" wire:click="unlink({{ $implementation->id }})" class="shrink-0 text-xs text-slate-400 hover:text-red-700">Retirer</button>
                        </li>
                    @endforeach
                </ul>

                @if ($sameItemElsewhere->isNotEmpty())
                    <div class="mt-4 border-t border-slate-200 pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Même élément ailleurs</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($sameItemElsewhere as $other)
                                <li class="flex items-center justify-between gap-2" wire:key="other-{{ $other->id }}">
                                    <span class="min-w-0 truncate">{{ $other->version->name }}</span>
                                    <button type="button" wire:click="linkSameItem({{ $other->id }})" class="shrink-0 text-xs font-medium text-brand-700 hover:underline">Concerné aussi</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit="link" class="mt-4 space-y-2 border-t border-slate-200 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ajouter un élément</p>
                    <x-ui.select wire:model.live="linkVersionId" :options="$versions" placeholder="Version…" aria-label="Version" />
                    @if ($linkVersionId !== '')
                        <x-ui.select wire:model="linkImplementationId" :options="$versionItems" placeholder="Élément…" aria-label="Élément" />
                        <x-ui.button type="submit" size="sm">Ajouter</x-ui.button>
                    @endif
                </form>
            </x-ui.card>

            <x-ui.card title="Actions de remédiation">
                @if ($remediationActions->isEmpty())
                    <p class="text-sm text-slate-500">Aucune action planifiée pour ce problème.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($remediationActions as $remediationAction)
                            <li wire:key="remediation-{{ $remediationAction->id }}" class="flex items-start justify-between gap-2">
                                <span>
                                    <span class="font-mono text-xs text-slate-500">{{ $remediationAction->displayCode() }}</span> {{ $remediationAction->title }}
                                    <span class="block text-xs text-slate-500">{{ $remediationAction->owner->name ?? 'Responsable à désigner' }}{{ $remediationAction->due_on ? ' · échéance '.$remediationAction->due_on->format('d/m/Y') : '' }}</span>
                                </span>
                                <x-ui.badge :color="$remediationAction->status->color()">{{ $remediationAction->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($planProductId)
                    <a href="{{ route('actions.index', ['product' => $planProductId, 'issue' => $issue->id]) }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Planifier une action</a>
                @endif
            </x-ui.card>

            @if ($findings->isNotEmpty())
                <x-ui.card title="Résultats d’outils retenus">
                    <ul class="space-y-2 text-xs">
                        @foreach ($findings as $finding)
                            <li wire:key="finding-{{ $finding->id }}">
                                <a href="{{ route('diagnostics.show', $finding->campaign) }}" wire:navigate class="font-mono text-brand-700 hover:underline">{{ $finding->displayCode() }}</a>
                                <span class="text-slate-500">{{ $finding->toolRun->tool_name }} · {{ $finding->campaign->displayCode() }} @ {{ $finding->campaign->shortCommit() }}</span>
                                <div class="text-slate-700">{{ \Illuminate\Support\Str::limit($finding->message, 160) }}</div>
                                @if ($finding->location()) <div class="break-all font-mono text-slate-500">{{ $finding->location() }}</div> @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card>
                <x-ui.audit :record="$issue" />
            </x-ui.card>
        </aside>
    </div>
</div>
