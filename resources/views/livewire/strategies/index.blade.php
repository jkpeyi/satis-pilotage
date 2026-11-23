<div>
    <x-ui.page-header title="Stratégies de refactoring" description="Documents de décision (L7) : contexte, scénarios d’architecture, migration, risques et décisions, rédigés et validés par la DTI.">
        <x-slot:actions>
            <x-ui.button wire:click="create">Nouvelle stratégie</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" class="py-2" />
    </div>

    @if ($strategies->isEmpty())
        <x-ui.empty>Aucune stratégie. Créez-en une par produit, par exemple « Convergence de Satis Macro vers le socle ».</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Code</th>
                <th>Stratégie</th>
                <th>Produit</th>
                <th class="text-right">Scénarios</th>
                <th class="text-right">Décisions en attente</th>
                <th>Validation</th>
            </x-slot:head>
            @foreach ($strategies as $strategy)
                <tr wire:key="strategy-{{ $strategy->id }}" @class(['opacity-60' => $strategy->isArchived()])>
                    <td class="font-mono text-xs text-slate-500">{{ $strategy->displayCode() }}</td>
                    <td>
                        <a href="{{ route('strategies.show', $strategy) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $strategy->title }}</a>
                        @if ($strategy->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
                    </td>
                    <td>{{ $strategy->product->name }}</td>
                    <td class="text-right tabular-nums">{{ $strategy->options_count }}</td>
                    <td class="text-right tabular-nums">{{ $strategy->pending_decisions_count }}</td>
                    <td>
                        <x-ui.badge :color="$strategy->approval_status->color()">{{ $strategy->approval_status->label() }}</x-ui.badge>
                        @if ($strategy->approver) <span class="text-xs text-slate-500">{{ $strategy->approver->name }}, {{ $strategy->approved_at?->format('d/m/Y') }}</span> @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.modal wire:model="showForm" title="Nouvelle stratégie">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Produit" wire:model="form.product_id" :options="$products" placeholder="Choisir…" required />
            <x-ui.input label="Intitulé" wire:model="form.title" required hint="Ex. Convergence de Satis Macro vers le socle" />
            <x-ui.textarea label="Contexte et objectifs" wire:model="form.context" rows="4" hint="Markdown. Le reste se complète sur la fiche." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Créer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
