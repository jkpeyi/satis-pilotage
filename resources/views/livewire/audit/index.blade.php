<div>
    <x-ui.page-header title="Suivi de l’audit" description="Effort d’audit par période : heures prévues et réalisées, temps perdu en interruptions de support. Repris dans le tableau de bord DTI (L6).">
        <x-slot:actions>
            <x-ui.button wire:click="create">Nouvelle période</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" class="py-2" />
    </div>

    @php
        $hours = fn (float $value): string => rtrim(rtrim(number_format($value, 1, ',', ' '), '0'), ',').' h';
    @endphp

    @if ($periods->isEmpty())
        <x-ui.empty>Aucune période. Saisissez par exemple chaque semaine les heures prévues, puis les heures réalisées une fois la semaine terminée.</x-ui.empty>
    @else
        <dl class="mb-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <dt class="text-xs text-slate-500">Réalisé / prévu (périodes renseignées)</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $hours($totals['actual']) }} / {{ $hours($totals['planned_with_actual']) }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <dt class="text-xs text-slate-500">Prévu, toutes périodes</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $hours($totals['planned']) }}</dd>
                @if ($totals['without_actual'] > 0) <dd class="text-xs text-amber-700">{{ $totals['without_actual'] }} période{{ $totals['without_actual'] > 1 ? 's' : '' }} sans heures réalisées</dd> @endif
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <dt class="text-xs text-slate-500">Interruptions de support</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $hours($totals['support']) }}</dd>
            </div>
        </dl>

        <x-ui.table>
            <x-slot:head>
                <th>Période</th>
                <th>Produit</th>
                <th class="text-right">Prévu</th>
                <th class="text-right">Réalisé</th>
                <th class="text-right">Support</th>
                <th class="text-right">Interruptions</th>
                <th>Notes</th>
                <th></th>
            </x-slot:head>
            @foreach ($periods as $period)
                <tr wire:key="period-{{ $period->id }}" @class(['opacity-60' => $period->isArchived()])>
                    <td class="whitespace-nowrap">{{ $period->starts_on->format('d/m/Y') }} → {{ $period->ends_on->format('d/m/Y') }}</td>
                    <td>{{ $period->product->name }}</td>
                    <td class="text-right tabular-nums">{{ $hours((float) $period->hours_planned) }}</td>
                    <td class="text-right tabular-nums">{!! $period->hours_actual !== null ? e($hours((float) $period->hours_actual)) : '<span class="text-amber-700">À saisir</span>' !!}</td>
                    <td class="text-right tabular-nums">{{ $period->support_hours !== null ? $hours((float) $period->support_hours) : '—' }}</td>
                    <td class="text-right tabular-nums">{{ $period->support_interruptions ?? '—' }}</td>
                    <td class="max-w-xs text-xs text-slate-600">{{ $period->notes ?? '' }}</td>
                    <td class="whitespace-nowrap text-right">
                        @unless ($period->isArchived())
                            <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $period->id }})">Modifier</x-ui.button>
                        @endunless
                        <x-ui.button size="sm" variant="ghost" wire:click="toggleArchive({{ $period->id }})">{{ $period->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.modal wire:model="showForm" :title="$form->period ? 'Modifier la période' : 'Nouvelle période'">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Produit" wire:model="form.product_id" :options="$products" placeholder="Choisir…" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Début" type="date" wire:model="form.starts_on" required />
                <x-ui.input label="Fin" type="date" wire:model="form.ends_on" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Heures prévues" wire:model="form.hours_planned" inputmode="decimal" required />
                <x-ui.input label="Heures réalisées" wire:model="form.hours_actual" inputmode="decimal" hint="À saisir une fois la période terminée." />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Heures perdues en support" wire:model="form.support_hours" inputmode="decimal" hint="Temps d’audit pris par des demandes de support." />
                <x-ui.input label="Nombre d’interruptions" wire:model="form.support_interruptions" inputmode="numeric" />
            </div>
            <x-ui.textarea label="Notes" wire:model="form.notes" rows="2" hint="Ex. incident de production, congés." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
