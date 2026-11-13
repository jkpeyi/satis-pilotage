<div>
    <x-ui.page-header title="Comparer deux versions" description="Ce que l'une a et que l'autre n'a pas, et ce qu'elles ont en commun mais de façon différente.">
        @if ($diff)
            <x-slot:actions>
                <x-ui.button variant="secondary" wire:click="export">Exporter en Excel</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="mb-6 grid items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
        <x-ui.select label="Version A" wire:model.live="a" :options="$versionOptions" placeholder="Choisir…" />
        <x-ui.button variant="ghost" wire:click="swap" title="Inverser A et B" aria-label="Inverser A et B" class="mb-0.5">⇄</x-ui.button>
        <x-ui.select label="Version B" wire:model.live="b" :options="$versionOptions" placeholder="Choisir…" />
    </div>

    @if ($sameVersion)
        <x-ui.empty>Choisissez deux versions différentes.</x-ui.empty>
    @elseif (! $diff)
        <x-ui.empty>Choisissez les deux versions à comparer.</x-ui.empty>
    @else
        @if ($versionA->product_id !== $versionB->product_id)
            <p class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Ces versions appartiennent à deux produits différents ({{ $versionA->product->name }} et {{ $versionB->product->name }}) : la comparaison porte sur le catalogue commun.
            </p>
        @endif

        <dl class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Uniquement dans A', $diff['counts']['onlyInA'], 'text-brand-700'],
                ['Uniquement dans B', $diff['counts']['onlyInB'], 'text-brand-700'],
                ['Présents mais différents', $diff['counts']['different'], 'text-amber-700'],
                ['Identiques', $diff['counts']['same'], 'text-emerald-700'],
            ] as [$label, $count, $color])
                <div class="rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <dt class="text-sm text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ $count }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mb-6 -mt-3 text-xs text-slate-500">
            Un élément compte comme présent s'il est rattaché à la version et n'est pas marqué « Absent ». Les nombres comptent tous les éléments, sous-éléments compris ; les listes regroupent un module entier sur une seule ligne.
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card :title="'Uniquement dans A · '.$versionA->name">
                @include('livewire.versions.partials.only-in', ['entries' => $diff['onlyInA'], 'owner' => $versionA, 'other' => $versionB, 'side' => 'a'])
            </x-ui.card>
            <x-ui.card :title="'Uniquement dans B · '.$versionB->name">
                @include('livewire.versions.partials.only-in', ['entries' => $diff['onlyInB'], 'owner' => $versionB, 'other' => $versionA, 'side' => 'b'])
            </x-ui.card>
        </div>

        <x-ui.card title="Présents dans les deux, mais différents" class="mt-6">
            @if ($diff['different'] === [])
                <p class="text-sm text-slate-500">Aucune différence d'état, d'écart ou de règles métier sur les éléments communs.</p>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Élément</th>
                        <th>Différences</th>
                        <th>{{ $versionA->name }}</th>
                        <th>{{ $versionB->name }}</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($diff['different'] as $entry)
                        <tr wire:key="different-{{ $entry['key'] }}">
                            <td>
                                <span @class(['font-medium text-slate-900' => $entry['type'] === 'module', 'text-slate-800' => $entry['type'] !== 'module'])>{{ $entry['name'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $entry['typeLabel'] }}@if ($entry['path']) · {{ $entry['path'] }}@endif</span>
                            </td>
                            <td>
                                <span class="flex flex-wrap gap-1">
                                    @foreach ($entry['reasons'] as $reason) <x-ui.badge color="amber">{{ $reason }}</x-ui.badge> @endforeach
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('implementations.show', $entry['a']) }}" wire:navigate>
                                    <x-ui.badge :color="$entry['a']->status->color()">{{ $entry['a']->status->label() }}</x-ui.badge>
                                </a>
                                @if ($entry['a']->deviation) <span class="ml-1 text-xs text-amber-700" title="Écart décrit">≠</span> @endif
                            </td>
                            <td>
                                <a href="{{ route('implementations.show', $entry['b']) }}" wire:navigate>
                                    <x-ui.badge :color="$entry['b']->status->color()">{{ $entry['b']->status->label() }}</x-ui.badge>
                                </a>
                                @if ($entry['b']->deviation) <span class="ml-1 text-xs text-amber-700" title="Écart décrit">≠</span> @endif
                            </td>
                            <td class="text-right">
                                <x-ui.button size="sm" variant="ghost" class="whitespace-nowrap" wire:click="compareRow('{{ $entry['key'] }}')">Voir le détail</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        @include('livewire.partials.row-comparison', ['pivotId' => $versionA->id])
    @endif
</div>
