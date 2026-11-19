<div>
    <x-ui.page-header title="Périmètre fonctionnel" :description="$version->product->name.' · '.$version->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('versions.index') }}" wire:navigate class="hover:text-slate-900">Versions</a>
            / <a href="{{ route('versions.show', $version) }}" wire:navigate class="hover:text-slate-900">{{ $version->name }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="$set('showInitialize', true)">Initialiser depuis une version</x-ui.button>
            <x-ui.button variant="secondary" wire:click="$set('showCreate', true)">Créer un élément ici</x-ui.button>
            <x-ui.button wire:click="openAttach">Rattacher des éléments</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Status summary, doubles as filter --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <button type="button" wire:click="$set('statusFilter', '')"
            @class(['rounded-full border px-3 py-1', 'border-brand-600 bg-brand-50 text-brand-700' => $statusFilter === '', 'border-slate-200 bg-white text-slate-600' => $statusFilter !== ''])>
            Tous <span class="tabular-nums">{{ $total }}</span>
        </button>
        @foreach (\App\Enums\ImplementationStatus::cases() as $status)
            <button type="button" wire:click="$set('statusFilter', '{{ $status->value }}')"
                @class(['rounded-full border px-3 py-1', 'border-brand-600 bg-brand-50 text-brand-700' => $statusFilter === $status->value, 'border-slate-200 bg-white text-slate-600' => $statusFilter !== $status->value])>
                {{ $status->label() }} <span class="tabular-nums">{{ $counts[$status->value] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Bulk actions --}}
    <div class="sticky top-0 z-10 mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div class="text-sm text-slate-600">
            <span class="font-medium tabular-nums">{{ count($selectedIds) }}</span> sélectionné(s)
            <button type="button" wire:click="selectAll" class="ml-2 text-brand-700 hover:underline">Tout sélectionner</button>
            @if ($selectedIds)
                <button type="button" wire:click="$set('selectedIds', [])" class="ml-2 text-slate-500 hover:underline">Vider</button>
            @endif
        </div>
        <div class="flex flex-1 flex-wrap items-end justify-end gap-2">
            <select wire:model="bulkStatus" aria-label="Nouvel état" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">
                <option value="">Nouvel état…</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" wire:model="bulkNote" placeholder="Commentaire (facultatif)" aria-label="Commentaire" class="w-56 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
            <x-ui.button size="sm" wire:click="applyBulkStatus" :disabled="! $selectedIds">Appliquer</x-ui.button>
            <x-ui.button size="sm" variant="secondary" wire:click="detachSelected" :disabled="! $selectedIds"
                wire:confirm="Détacher les éléments sélectionnés et leurs sous-éléments de cette version ?">Détacher</x-ui.button>
        </div>
        @error('selectedIds') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        @error('bulkStatus') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        @if ($detachWarning)
            <div class="w-full rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p>{{ $detachWarning }}</p>
                <div class="mt-2 flex gap-2">
                    <x-ui.button size="sm" variant="danger" wire:click="confirmDetach">Confirmer le détachement</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="$set('detachWarning', null)">Annuler</x-ui.button>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
        @if ($total === 0)
            <x-ui.empty class="border-0">
                Aucun élément rattaché. Cliquez sur « Rattacher des éléments » pour choisir dans le catalogue, ou « Créer un élément ici ».
            </x-ui.empty>
        @elseif ($filtered !== null)
            <ul>
                @forelse ($filtered as $implementation)
                    <li wire:key="filtered-{{ $implementation->id }}">
                        @include('livewire.versions.partials.scope-row', [
                            'name' => $implementation->catalogItem()->name,
                            'isModule' => $implementation->module_id !== null,
                            'depth' => 0,
                            'toggle' => new \Illuminate\Support\HtmlString('<span class="w-4"></span>'),
                        ])
                    </li>
                @empty
                    <li class="px-2 py-6 text-center text-sm text-slate-500">Aucun élément dans cet état.</li>
                @endforelse
            </ul>
        @else
            <ul>
                @foreach ($tree->rootModuleIds() as $moduleId)
                    @include('livewire.versions.partials.scope-module', ['moduleId' => $moduleId, 'depth' => 0])
                @endforeach
            </ul>
        @endif
    </div>

    <x-ui.modal wire:model="showAttach" title="Rattacher des éléments du catalogue">
        <form wire:submit="attach" class="space-y-4">
            <p class="text-sm text-slate-500">Les modules parents des éléments cochés sont rattachés automatiquement.</p>
            <div class="max-h-96 overflow-y-auto rounded border border-slate-200 p-2">
                @if ($tree->rootModuleIds() === [])
                    <p class="p-4 text-sm text-slate-500">Le catalogue est vide. <a href="{{ route('catalog.index') }}" wire:navigate class="text-brand-700 underline">Ouvrir le catalogue</a></p>
                @else
                    <ul>
                        @foreach ($tree->rootModuleIds() as $moduleId)
                            @include('livewire.versions.partials.attach-module', ['moduleId' => $moduleId, 'depth' => 0])
                        @endforeach
                    </ul>
                @endif
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="attachWithDescendants" class="rounded border-slate-300 text-brand-700">
                Rattacher aussi les sous-modules et fonctionnalités des modules cochés
            </label>
            <div class="flex gap-2">
                <x-ui.button type="submit">Rattacher</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showCreate" title="Créer un élément dans le catalogue">
        <form wire:submit="createItem" class="space-y-4">
            <p class="text-sm text-slate-500">L'élément est ajouté au catalogue commun et rattaché à cette version.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Type" wire:model.live="newKind" :options="['feature' => 'Fonctionnalité', 'module' => 'Module ou sous-module']" />
                <x-ui.select label="Dans le module" wire:model="newParentId" :options="$attachedModuleOptions"
                    :placeholder="$newKind === 'module' ? 'Aucun (module racine)' : 'Choisir…'" />
            </div>
            <x-ui.input label="Nom" wire:model.live.debounce.250ms="newName" required hint="Les noms sont uniques dans tout le catalogue : rattachez l'élément existant s'il apparaît ci-dessous." />
            @if ($newSuggestions !== [])
                <div class="rounded-md border border-slate-200 text-sm">
                    <p class="border-b border-slate-100 px-3 py-1.5 text-xs text-slate-500">Déjà dans le catalogue :</p>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($newSuggestions as $suggestion)
                            <li class="flex items-center gap-2 px-3 py-1.5" wire:key="new-suggestion-{{ $suggestion['key'] }}">
                                <span class="min-w-0 flex-1">
                                    <span @class(['font-semibold text-slate-900' => $suggestion['exact'], 'text-slate-700' => ! $suggestion['exact']])>{{ $suggestion['name'] }}</span>
                                    <span class="block truncate text-xs text-slate-500" title="{{ $suggestion['path'] }}">{{ $suggestion['type'] === 'feature' ? 'Fonctionnalité' : 'Module' }} · {{ $suggestion['path'] }}</span>
                                </span>
                                @if ($suggestion['archived'])
                                    <x-ui.badge>Archivé dans le catalogue</x-ui.badge>
                                @elseif ($suggestion['attached'])
                                    <x-ui.badge color="green">Déjà rattaché</x-ui.badge>
                                @else
                                    <x-ui.button size="sm" variant="secondary" wire:click="attachExisting('{{ $suggestion['key'] }}')">Rattacher</x-ui.button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="flex gap-2">
                <x-ui.button type="submit">Créer et rattacher</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Fermer</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showInitialize" title="Initialiser depuis une autre version">
        <form wire:submit="initialize" class="space-y-4">
            <p class="text-sm text-slate-500">
                Reprend les éléments rattachés à la version source. Les éléments déjà présents ici ne sont pas modifiés.
            </p>
            <x-ui.select label="Version source" wire:model="initSourceId" :options="$sourceVersions" placeholder="Choisir…" required />
            <fieldset class="space-y-2 text-sm text-slate-700">
                <legend class="text-sm font-medium text-slate-700">Reprendre aussi</legend>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="initStatuses" class="rounded border-slate-300 text-brand-700"> Les états (tracés dans l'historique)</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="initDeviations" class="rounded border-slate-300 text-brand-700"> Les écarts</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="initRules" class="rounded border-slate-300 text-brand-700"> Les règles métier, avec un lien vers leur origine</label>
            </fieldset>
            <div class="flex gap-2">
                <x-ui.button type="submit">Initialiser</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showHistory" title="Historique des états">
        @if ($history)
            <p class="mb-3 text-sm font-medium text-slate-800">{{ $history->catalogItem()->name }}</p>
            @if ($history->statusChanges->isEmpty())
                <p class="text-sm text-slate-500">Aucun changement d'état : l'élément est « {{ $history->status->label() }} » depuis son rattachement.</p>
            @else
                <ol class="space-y-3 text-sm">
                    @foreach ($history->statusChanges->reverse() as $change)
                        <li class="border-l-2 border-slate-200 pl-3">
                            <div class="flex flex-wrap items-center gap-1">
                                @if ($change->from_status)
                                    <x-ui.badge :color="$change->from_status->color()">{{ $change->from_status->label() }}</x-ui.badge> →
                                @endif
                                <x-ui.badge :color="$change->to_status->color()">{{ $change->to_status->label() }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $change->created_at?->format('d/m/Y à H:i') }} · {{ $change->user->name ?? 'Système' }}</p>
                            @if ($change->note) <p class="mt-1 text-slate-700">{{ $change->note }}</p> @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        @endif
    </x-ui.modal>
</div>
