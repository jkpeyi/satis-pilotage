<div>
    <x-ui.page-header title="Catalogue" description="Modules, sous-modules et fonctionnalités, communs à toutes les versions.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('catalog.duplicates')">Doublons</x-ui.button>
            <x-ui.button wire:click="startDraft(null, 'module')">Nouveau module</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        {{-- Tree --}}
        <section class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-4 py-3">
                <div class="min-w-48 flex-1"><x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un élément…" width="max-w-none" /></div>
                <div class="flex items-center gap-2 text-xs">
                    <button type="button" wire:click="expandAll" class="text-slate-500 hover:text-slate-900">Tout déplier</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" wire:click="collapseAll" class="text-slate-500 hover:text-slate-900">Tout replier</button>
                </div>
                <x-ui.archived-toggle wire:model.live="showArchived" class="text-xs" />
            </div>

            <div class="max-h-[70vh] overflow-y-auto p-2">
                @if ($searchResults !== null)
                    <ul class="divide-y divide-slate-100">
                        @forelse ($searchResults as $result)
                            <li wire:key="result-{{ $result['key'] }}">
                                <button type="button" wire:click="select('{{ $result['key'] }}')" class="w-full px-2 py-2 text-left hover:bg-slate-50">
                                    <span class="block text-sm font-medium text-slate-800">{{ $result['name'] }}</span>
                                    <span class="block text-xs text-slate-500">{{ $result['type'] }}@if ($result['path']) · {{ $result['path'] }}@endif</span>
                                </button>
                            </li>
                        @empty
                            <li class="px-2 py-6 text-center text-sm text-slate-500">Aucun élément ne correspond.</li>
                        @endforelse
                    </ul>
                @elseif ($tree->rootModuleIds() === [] && $draft === null)
                    <x-ui.empty>
                        Le catalogue est vide. Cliquez sur « Nouveau module » puis saisissez les éléments au clavier :
                        <strong>Entrée</strong> pour enchaîner, <strong>Tab</strong> pour descendre d'un niveau, <strong>Maj+Tab</strong> pour remonter.
                    </x-ui.empty>
                @else
                    <ul x-sortable="{ group: 'modules', parent: null }" data-parent="">
                        @foreach ($tree->rootModuleIds() as $moduleId)
                            @include('livewire.catalog.partials.module', ['moduleId' => $moduleId, 'depth' => 0])
                        @endforeach
                    </ul>
                    @if ($draft !== null && $draft['parent_id'] === null)
                        <ul>@include('livewire.catalog.partials.draft', ['depth' => 0])</ul>
                    @endif
                @endif
            </div>
            <p class="border-t border-slate-200 px-4 py-2 text-xs text-slate-500">
                Saisie rapide : Entrée enchaîne, Tab descend d'un niveau, Maj+Tab remonte, Alt+M / Alt+F change le type, Échap ferme. Glissez ⋮⋮ pour déplacer.
            </p>
        </section>

        {{-- Selected item --}}
        <section class="min-w-0">
            @if ($item === null)
                <x-ui.empty>Sélectionnez un élément pour voir sa fiche et les versions qui l'utilisent.</x-ui.empty>
            @else
                <div class="space-y-6" wire:key="item-{{ $selected }}">
                    <x-ui.card>
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">
                                    {{ $item->typeLabel() }}@if ($path) · {{ implode(' › ', $path) }}@endif
                                </p>
                                <h2 class="text-lg font-semibold text-slate-900">{{ $item->name }}</h2>
                                @if ($item->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($item instanceof \App\Models\Feature)
                                    <x-ui.button size="sm" variant="secondary" wire:click="convert"
                                        wire:confirm="Convertir cette fonctionnalité en sous-module ? Ses rattachements, règles, problèmes et historiques sont conservés.">
                                        Convertir en sous-module
                                    </x-ui.button>
                                @elseif ($item->isSubmodule())
                                    <x-ui.button size="sm" variant="secondary" wire:click="convert"
                                        wire:confirm="Convertir ce sous-module en fonctionnalité ? Ses rattachements, règles, problèmes et historiques sont conservés.">
                                        Convertir en fonctionnalité
                                    </x-ui.button>
                                @endif
                                @unless ($item->isArchived())
                                    <x-ui.button size="sm" variant="secondary" wire:click="startMerge">Fusionner dans…</x-ui.button>
                                @endunless
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleArchive"
                                    wire:confirm="{{ $item->isArchived() ? 'Restaurer cet élément ?' : 'Archiver cet élément et tous ses sous-éléments ?' }}">
                                    {{ $item->isArchived() ? 'Restaurer' : 'Archiver' }}
                                </x-ui.button>
                            </div>
                        </div>
                        @error('itemAction') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

                        <form wire:submit="save" class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-ui.input label="Nom" wire:model="form.name" required class="sm:col-span-2" />
                                <x-ui.input label="Code" wire:model="form.code" class="font-mono uppercase" />
                            </div>
                            <x-ui.textarea label="Description standard" wire:model="form.standard_description" rows="6"
                                hint="Comportement attendu, en Markdown. Les écarts de chaque version sont décrits par rapport à ce texte." />
                            @if ($products->isNotEmpty())
                                <fieldset>
                                    <legend class="text-sm font-medium text-slate-700">Produits concernés</legend>
                                    <div class="mt-1 flex flex-wrap gap-4">
                                        @foreach ($products as $productId => $productName)
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" wire:model="form.product_ids" value="{{ $productId }}" class="rounded border-slate-300 text-brand-700">
                                                {{ $productName }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                            <x-ui.button type="submit">Enregistrer</x-ui.button>
                        </form>
                        <x-ui.audit :record="$item" class="mt-4" />
                    </x-ui.card>

                    @if ($moduleLinks)
                        <x-ui.card title="Modules">
                            <p class="mb-3 text-sm text-slate-500">Une fonctionnalité n'existe qu'une fois ; elle peut apparaître dans plusieurs modules. Son module principal donne son chemin dans les rapports et est rattaché avec elle à une version.</p>
                            <ul class="divide-y divide-slate-100 text-sm">
                                <li class="flex items-center justify-between gap-3 py-2">
                                    <span class="text-slate-800">{{ $moduleLinks['main'] }}</span>
                                    <x-ui.badge color="blue">Principal</x-ui.badge>
                                </li>
                                @foreach ($moduleLinks['others'] as $moduleId => $label)
                                    <li class="flex items-center justify-between gap-3 py-2" wire:key="link-{{ $moduleId }}">
                                        <span class="text-slate-700">{{ $label }}</span>
                                        <span class="whitespace-nowrap">
                                            <x-ui.button size="sm" variant="ghost" wire:click="makeMainModule({{ $moduleId }})">Définir comme principal</x-ui.button>
                                            <x-ui.button size="sm" variant="ghost" wire:click="unlinkModule({{ $moduleId }})"
                                                wire:confirm="Retirer la fonctionnalité de ce module ? Elle reste dans son module principal, avec tous ses rattachements.">Retirer</x-ui.button>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            @unless ($item->isArchived())
                                <form wire:submit="linkModule" class="mt-3 flex items-end gap-2">
                                    <x-ui.select label="Ajouter à un autre module" wire:model="linkModuleId" :options="$moduleLinks['options']" placeholder="Choisir un module…" class="min-w-0" />
                                    <x-ui.button type="submit" variant="secondary">Ajouter</x-ui.button>
                                </form>
                                @error('linkModuleId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @endunless
                        </x-ui.card>
                    @endif

                    <x-ui.card title="Versions qui l'utilisent">
                        @if ($detachWarning)
                            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                <p>{{ $detachWarning }}</p>
                                <div class="mt-2 flex gap-2">
                                    <x-ui.button size="sm" variant="danger" wire:click="confirmDetach">Confirmer le détachement</x-ui.button>
                                    <x-ui.button size="sm" variant="secondary" wire:click="$set('detachWarning', null)">Annuler</x-ui.button>
                                </div>
                            </div>
                        @endif

                        @if ($implementations->isEmpty())
                            <p class="text-sm text-slate-500">Aucune version n'utilise cet élément.</p>
                        @else
                            <ul class="divide-y divide-slate-100 text-sm">
                                @foreach ($implementations as $implementation)
                                    <li class="flex items-center justify-between gap-3 py-2" wire:key="impl-{{ $implementation->id }}">
                                        <a href="{{ route('implementations.show', $implementation) }}" wire:navigate class="min-w-0 truncate hover:text-brand-700">
                                            <span class="text-slate-500">{{ $implementation->version->product->name }} ·</span>
                                            {{ $implementation->version->name }}
                                        </a>
                                        <span class="flex shrink-0 items-center gap-2">
                                            <x-ui.badge :color="$implementation->status->color()">{{ $implementation->status->label() }}</x-ui.badge>
                                            <button type="button" wire:click="detachFromVersion({{ $implementation->version_id }})" class="text-xs text-slate-500 hover:text-red-700">Détacher</button>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($availableVersions->isNotEmpty() && ! $item->isArchived())
                            <form wire:submit="attachToVersions" class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                                <x-ui.field label="Rattacher à des versions" error="attachVersionIds" hint="Ctrl ou Cmd pour sélectionner plusieurs versions. Les modules parents sont rattachés automatiquement.">
                                    <select multiple size="5" wire:model="attachVersionIds" class="block w-full rounded-md border border-slate-300 bg-white px-2 py-1 text-sm">
                                        @foreach ($availableVersions as $version)
                                            <option value="{{ $version->id }}">{{ $version->product->name }} · {{ $version->name }}</option>
                                        @endforeach
                                    </select>
                                </x-ui.field>
                                @if ($item instanceof \App\Models\Module)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" wire:model="attachWithDescendants" class="rounded border-slate-300 text-brand-700">
                                        Rattacher aussi ses sous-modules et fonctionnalités
                                    </label>
                                @endif
                                <x-ui.button type="submit" size="sm">Rattacher</x-ui.button>
                            </form>
                        @endif
                    </x-ui.card>
                </div>
            @endif
        </section>
    </div>

    <x-ui.modal wire:model="showMerge" title="Fusionner dans un autre élément">
        @if ($item)
            <div class="space-y-4">
                <p class="text-sm text-slate-600">
                    « {{ $item->name }} » sera <strong>supprimé</strong> après avoir transmis à l'élément choisi tout ce qu'il porte :
                    rattachements, règles métier, problèmes, historiques, sous-éléments et étapes de workflow. La fusion est tracée.
                </p>
                <x-ui.input label="Élément à conserver" wire:model.live.debounce.250ms="mergeSearch" placeholder="Rechercher par nom…" />
                @if ($mergeCandidates === [])
                    <p class="text-sm text-slate-500">Aucun {{ $item instanceof \App\Models\Module ? 'module' : 'élément de type fonctionnalité' }} actif ne correspond.</p>
                @else
                    <ul class="max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-md border border-slate-200 text-sm">
                        @foreach ($mergeCandidates as $candidate)
                            <li wire:key="merge-candidate-{{ $candidate['key'] }}">
                                <label class="flex cursor-pointer items-start gap-2 px-3 py-2 hover:bg-slate-50">
                                    <input type="radio" value="{{ $candidate['key'] }}" wire:model.live="mergeTarget" class="mt-0.5 border-slate-300 text-brand-700">
                                    <span>
                                        <span class="font-medium text-slate-900">{{ $candidate['name'] }}</span>
                                        <span class="block text-xs text-slate-500">{{ $candidate['path'] }}</span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($mergePreview)
                    <div class="rounded-md bg-slate-50 p-3 text-sm text-slate-700">
                        <p>{{ $mergePreview['moving'] }} rattachement(s) passeront sur « {{ $mergePreview['target']->name }} ».</p>
                        @if ($mergePreview['common'] > 0)
                            <p>{{ $mergePreview['common'] }} version(s) ont déjà les deux éléments : leurs rattachements seront réunis (règles, problèmes, historiques, écarts mis bout à bout).</p>
                        @endif
                        @if ($mergePreview['children'] > 0)
                            <p>{{ $mergePreview['children'] }} sous-élément(s) passeront sous « {{ $mergePreview['target']->name }} ».</p>
                        @endif
                    </div>
                @endif
                @error('mergeTarget') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex gap-2 pt-2">
                    <x-ui.button variant="danger" wire:click="merge" :disabled="$mergeTarget === ''"
                        wire:confirm="Fusionner et supprimer « {{ $item->name }} » ? Cette opération ne peut pas être annulée.">Fusionner</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>
</div>
