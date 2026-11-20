<div>
    <x-ui.page-header title="Diagnostics" description="Campagnes d’analyse d’un dépôt à un commit précis : résultats des outils importés, vérifiés un par un, et exécutions de tests. Les outils tournent hors de la plateforme.">
        <x-slot:actions>
            <x-ui.button wire:click="create">Nouvelle campagne</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" class="py-2" />
    </div>

    @if ($campaigns->isEmpty())
        <x-ui.empty>Aucune campagne. Créez-en une pour un dépôt et un commit, puis importez les fichiers produits par les outils (SARIF, PHPStan, ESLint).</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Code</th>
                <th>Campagne</th>
                <th>Dépôt</th>
                <th>Commit</th>
                <th>Date</th>
                <th class="text-right">Imports</th>
                <th class="text-right">À vérifier</th>
                <th class="text-right">Retenus</th>
                <th class="text-right">Faux positifs</th>
            </x-slot:head>
            @foreach ($campaigns as $campaign)
                <tr wire:key="campaign-{{ $campaign->id }}" @class(['opacity-60' => $campaign->isArchived()])>
                    <td class="font-mono text-xs text-slate-500">{{ $campaign->displayCode() }}</td>
                    <td>
                        <a href="{{ route('diagnostics.show', $campaign) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $campaign->title }}</a>
                        @if ($campaign->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                    </td>
                    <td class="text-xs">
                        <div class="font-mono text-slate-700">{{ $campaign->repository->path }}</div>
                        <div class="text-slate-500">{{ $campaign->repository->version->product->name }} · {{ $campaign->repository->version->name }}</div>
                    </td>
                    <td class="font-mono text-xs">{{ $campaign->shortCommit() }}</td>
                    <td class="whitespace-nowrap">{{ $campaign->analyzed_on?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $campaign->tool_runs_count }}</td>
                    <td class="text-right tabular-nums">{{ $campaign->to_verify_count }}</td>
                    <td class="text-right tabular-nums">{{ $campaign->retained_count }}</td>
                    <td class="text-right tabular-nums">{{ $campaign->false_positive_count }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.modal wire:model="showForm" title="Nouvelle campagne de diagnostic">
        <form wire:submit="save" class="space-y-4">
            @include('livewire.diagnostics.partials.campaign-fields')
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Créer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
