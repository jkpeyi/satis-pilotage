<div>
    <x-ui.page-header title="Workflows" description="Processus métier d'un produit, étape par étape, avec les fonctionnalités et les règles métier qui les réalisent.">
        <x-slot:actions>
            <x-ui.button wire:click="create">Nouveau workflow</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" class="py-2" />
    </div>

    @if ($workflows->isEmpty())
        <x-ui.empty>Aucun workflow. Créez par exemple « Traitement des réclamations » et décrivez ses étapes.</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Code</th>
                <th>Workflow</th>
                <th>Produit</th>
                <th class="text-right">Étapes</th>
            </x-slot:head>
            @foreach ($workflows as $workflow)
                <tr wire:key="workflow-{{ $workflow->id }}" @class(['opacity-60' => $workflow->isArchived()])>
                    <td class="font-mono text-xs text-slate-500">{{ $workflow->displayCode() }}</td>
                    <td>
                        <a href="{{ route('workflows.show', $workflow) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $workflow->name }}</a>
                        @if ($workflow->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                    </td>
                    <td>{{ $workflow->product->name }}</td>
                    <td class="text-right tabular-nums">{{ $workflow->steps_count }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.modal wire:model="showForm" title="Nouveau workflow">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Produit" wire:model="form.product_id" :options="$products" placeholder="Choisir…" required />
            <x-ui.input label="Nom" wire:model="form.name" required hint="Ex. Traitement des réclamations" />
            <x-ui.textarea label="Description" wire:model="form.description" rows="4" hint="Markdown accepté." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Créer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
