@php
    $keyOf = fn ($item) => ($item instanceof \App\Models\Module ? 'module:' : 'feature:').$item->id;
@endphp
<div>
    <x-ui.page-header title="Doublons du catalogue" description="Un nom n'existe qu'une fois dans tout le catalogue (casse, accents et ponctuation ignorés). Fusionner transfère tout à l'élément conservé, puis supprime l'autre ; chaque fusion est tracée.">
        <x-slot:breadcrumbs>
            <a href="{{ route('catalog.index') }}" wire:navigate class="hover:text-slate-900">Catalogue</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <div class="space-y-6">
        <x-ui.card title="Même nom">
            @if ($groups === [])
                <p class="text-sm text-slate-500">Aucun élément actif ne partage son nom avec un autre élément du même type.</p>
            @else
                <ul class="space-y-4 text-sm">
                    @foreach ($groups as $group)
                        @php
                            $kept = $group['items'][0];
                        @endphp
                        <li wire:key="group-{{ $group['kind'] }}-{{ $group['name_key'] }}" class="rounded-md border border-slate-200 p-3">
                            <p class="font-medium text-slate-900">{{ $kept['item']->name }}</p>
                            <p class="text-xs text-slate-500">Conservé : {{ $kept['item']->displayCode() }} · {{ $kept['path'] }} · {{ $kept['implementations'] }} rattachement(s)</p>
                            <ul class="mt-2 space-y-1">
                                @foreach (array_slice($group['items'], 1) as $entry)
                                    <li class="flex items-center justify-between gap-3" wire:key="dup-{{ $keyOf($entry['item']) }}">
                                        <span class="text-slate-700">{{ $entry['item']->displayCode() }} · {{ $entry['path'] }} · {{ $entry['implementations'] }} rattachement(s)</span>
                                        <x-ui.button size="sm" variant="secondary" wire:click="merge('{{ $keyOf($entry['item']) }}', '{{ $keyOf($kept['item']) }}')"
                                            wire:confirm="Fusionner {{ $entry['item']->displayCode() }} dans {{ $kept['item']->displayCode() }} et le supprimer ?">Fusionner</x-ui.button>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Module et fonctionnalité de même nom">
            @if ($crossKind === [])
                <p class="text-sm text-slate-500">Aucun.</p>
            @else
                <p class="mb-3 text-sm text-slate-500">« Réunir » fait du sous-module la même fonctionnalité : il n'en reste qu'une, présente aussi dans le module parent du sous-module, avec tous les rattachements, règles, problèmes et historiques. Le sous-module ne doit plus rien contenir.</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($crossKind as $group)
                        <li wire:key="cross-{{ $group['name_key'] }}" class="rounded-md border border-slate-200 p-3">
                            @php
                                $modules = array_values(array_filter($group['items'], fn ($entry) => $entry['item'] instanceof \App\Models\Module));
                                $features = array_values(array_filter($group['items'], fn ($entry) => $entry['item'] instanceof \App\Models\Feature));
                            @endphp
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    @foreach ($group['items'] as $entry)
                                        <a href="{{ route('catalog.index', ['item' => $keyOf($entry['item'])]) }}" wire:navigate class="block text-slate-700 hover:text-brand-700">
                                            {{ $entry['item']->typeLabel() }} {{ $entry['item']->displayCode() }} · {{ $entry['path'] }}
                                        </a>
                                    @endforeach
                                </div>
                                @if (count($modules) === 1 && count($features) === 1)
                                    <x-ui.button size="sm" variant="secondary" wire:click="reunite({{ $modules[0]['item']->id }}, {{ $features[0]['item']->id }})"
                                        wire:confirm="Réunir en une seule fonctionnalité, présente dans les deux modules ? Le sous-module sera supprimé après transfert de ses rattachements.">Réunir</x-ui.button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Noms proches">
            @if ($similar === [])
                <p class="text-sm text-slate-500">Aucun nom proche détecté.</p>
            @else
                <p class="mb-3 text-sm text-slate-500">Fautes de frappe, pluriels ou mots dans un autre ordre. Vérifiez qu'il s'agit bien du même élément avant de fusionner.</p>
                <x-ui.table>
                    <x-slot:head>
                        <th>Élément A</th>
                        <th>Élément B</th>
                        <th class="text-right">Fusionner</th>
                    </x-slot:head>
                    @foreach ($similar as $pair)
                        <tr wire:key="similar-{{ $keyOf($pair['a']['item']) }}-{{ $keyOf($pair['b']['item']) }}">
                            <td>
                                <div class="font-medium text-slate-900">{{ $pair['a']['item']->name }}</div>
                                <div class="text-xs text-slate-500">{{ $pair['a']['path'] }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-slate-900">{{ $pair['b']['item']->name }}</div>
                                <div class="text-xs text-slate-500">{{ $pair['b']['path'] }}</div>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="ghost" wire:click="merge('{{ $keyOf($pair['a']['item']) }}', '{{ $keyOf($pair['b']['item']) }}')"
                                    wire:confirm="Fusionner « {{ $pair['a']['item']->name }} » dans « {{ $pair['b']['item']->name }} » et le supprimer ?">A → B</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="merge('{{ $keyOf($pair['b']['item']) }}', '{{ $keyOf($pair['a']['item']) }}')"
                                    wire:confirm="Fusionner « {{ $pair['b']['item']->name }} » dans « {{ $pair['a']['item']->name }} » et le supprimer ?">B → A</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</div>
