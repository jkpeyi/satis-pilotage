<div>
    <x-ui.page-header title="Cartographie des écarts" description="Éléments du catalogue × versions : état, écarts, règles et problèmes, comparés à une version pivot." />

    {{-- Selection (EF-301) --}}
    <div class="mb-4 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[14rem_minmax(0,1fr)_16rem]">
        <x-ui.select label="Produit" wire:model.live="product" :options="$products" placeholder="Choisir un produit…" />

        <div class="min-w-0">
            <p class="mb-1 text-sm font-medium text-slate-700">Versions comparées</p>
            @if ($versions->isEmpty())
                <p class="text-sm text-slate-500">{{ $product === '' ? 'Choisissez un produit.' : 'Ce produit n\'a pas de version.' }}</p>
            @else
                <div class="flex max-h-24 flex-wrap gap-x-4 gap-y-1 overflow-y-auto">
                    @foreach ($versions as $version)
                        <label class="flex items-center gap-1.5 text-sm text-slate-700" wire:key="pick-{{ $version->id }}">
                            <input type="checkbox" wire:model.live="versionIds" value="{{ $version->id }}" class="rounded border-slate-300 text-brand-700">
                            {{ $version->name }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <x-ui.select label="Branche du catalogue" wire:model.live="rootModuleId" :options="$branches" placeholder="Tout le catalogue" />
    </div>

    @if ($versionIds === [])
        <x-ui.empty>Choisissez un produit et au moins une version pour afficher la cartographie.</x-ui.empty>
    @else
        <div wire:ignore x-data="comparisonMatrix" x-on:matrix-reload.window="load()" class="min-w-0">
            {{-- Pivot and filters (EF-303, EF-304), applied in the browser --}}
            <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <label class="flex items-center gap-2">
                    <span class="font-medium text-slate-700">Pivot</span>
                    <select x-model.number="pivot" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm" aria-label="Version pivot">
                        <template x-for="version in (data?.versions ?? [])" :key="version.id">
                            <option :value="version.id" x-text="version.name + (version.reference ? ' (référence)' : '')" :selected="version.id === pivot"></option>
                        </template>
                    </select>
                </label>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="filters.diffOnly" class="rounded border-slate-300 text-brand-700"> Différences seulement</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="filters.withDeviation" class="rounded border-slate-300 text-brand-700"> Avec écart</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="filters.withIssue" class="rounded border-slate-300 text-brand-700"> Avec problème</label>
                <select x-model="filters.status" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm" aria-label="Filtrer par état">
                    <option value="">Tous les états</option>
                    @foreach (\App\Enums\ImplementationStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <span class="flex items-center gap-2 text-xs">
                    <button type="button" x-on:click="collapseAll(false)" class="text-slate-500 hover:text-slate-900">Tout déplier</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" x-on:click="collapseAll(true)" class="text-slate-500 hover:text-slate-900">Replier les modules</button>
                </span>
                <span class="ml-auto flex items-center gap-3">
                    <span class="text-xs text-slate-500" x-show="data" x-text="`${stats.rows} ligne(s), ${stats.differing} différente(s) du pivot`"></span>
                    <a :href="compareUrl()" class="text-sm font-medium text-brand-700 hover:underline" x-show="data && data.versions.length > 1">Comparer deux versions</a>
                    <x-ui.button size="sm" variant="secondary" x-on:click="exportExcel()">Exporter en Excel</x-ui.button>
                </span>
            </div>

            <div x-ref="scroller" class="relative h-[70vh] overflow-auto rounded-lg border border-slate-200 bg-white shadow-sm" x-on:click="onClick($event)" x-on:scroll.passive="onScroll()">
                <div x-show="loading" class="absolute inset-0 z-30 grid place-items-center bg-white/70 text-sm text-slate-500">Chargement…</div>
                <table class="mx border-separate border-spacing-0 text-sm" style="table-layout: fixed" :style="data ? `width: ${16 + data.versions.length * 9.5}rem` : ''">
                    <colgroup>
                        <col style="width: 16rem">
                        <template x-for="version in (data?.versions ?? [])" :key="'col-' + version.id"><col style="width: 9.5rem"></template>
                    </colgroup>
                    <thead class="sticky top-0 z-20 bg-slate-50 text-left text-xs">
                        <tr>
                            <th class="sticky left-0 z-30 border-b border-slate-200 bg-slate-50 px-2 py-2 font-semibold uppercase tracking-wide text-slate-500">Élément</th>
                            <template x-for="version in (data?.versions ?? [])" :key="version.id">
                                <th class="border-b border-l border-slate-200 px-2 py-2 align-bottom font-medium text-slate-700"
                                    :class="version.id === pivot ? 'bg-brand-50 text-brand-700' : ''">
                                    <span class="block truncate" x-text="version.name" :title="version.name"></span>
                                    <span class="block truncate font-normal text-slate-500" x-text="version.id === pivot ? 'Pivot' : (version.customer ?? 'Socle')"></span>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody x-ref="body"></tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                « — » : non rattaché (hors périmètre du client). ≠ : écart décrit. RM : règles métier. PB : problèmes.
                Cadre orange : différent du pivot (rattachement, état ou présence d'un écart). Cliquez une cellule pour le détail, un nom pour comparer l'élément entre les versions.
            </p>
        </div>
    @endif

    {{-- Cell panel (EF-305) --}}
    @if ($panel)
        <aside class="fixed inset-y-0 right-0 z-40 w-full max-w-md overflow-y-auto border-l border-slate-200 bg-white p-5 shadow-xl" wire:key="panel-{{ $panel->id }}"
               x-data x-on:keydown.escape.window="$wire.closePanel()">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-slate-500">{{ $panel->version->name }}</p>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $panel->catalogItem()->name }}</h2>
                </div>
                <button type="button" wire:click="closePanel" class="text-slate-400 hover:text-slate-700" aria-label="Fermer">×</button>
            </div>
            <x-ui.badge :color="$panel->status->color()">{{ $panel->status->label() }}</x-ui.badge>

            <h3 class="mb-1 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Écart</h3>
            @if ($panel->deviation)
                <x-ui.markdown :text="$panel->deviation" />
            @else
                <p class="text-sm text-slate-500">Conforme à la description standard.</p>
            @endif

            <h3 class="mb-1 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Règles métier</h3>
            <ul class="space-y-1 text-sm">
                @forelse ($panel->businessRules as $rule)
                    <li><a href="{{ route('business-rules.show', $rule) }}" wire:navigate class="hover:text-brand-700"><span class="font-mono text-xs text-slate-500">{{ $rule->displayCode() }}</span> {{ $rule->title }}</a></li>
                @empty
                    <li class="text-slate-500">Aucune.</li>
                @endforelse
            </ul>

            <h3 class="mb-1 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Problèmes</h3>
            <ul class="space-y-1 text-sm">
                @forelse ($panel->issues as $issue)
                    <li class="flex items-center justify-between gap-2">
                        <a href="{{ route('issues.show', $issue) }}" wire:navigate class="min-w-0 truncate hover:text-brand-700"><span class="font-mono text-xs text-slate-500">{{ $issue->displayCode() }}</span> {{ $issue->title }}</a>
                        <x-ui.severity-badge :severity="$issue->severity" />
                    </li>
                @empty
                    <li class="text-slate-500">Aucun.</li>
                @endforelse
            </ul>

            <x-ui.button class="mt-6 w-full" :href="route('implementations.show', $panel)">Ouvrir la fiche</x-ui.button>
        </aside>
    @endif

    {{-- Detailed comparison of one item (EF-306) --}}
    @include('livewire.partials.row-comparison', ['pivotId' => $pivot])
</div>
