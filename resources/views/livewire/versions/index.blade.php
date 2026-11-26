<div>
    <x-ui.page-header title="Versions" description="Déploiements d'un produit chez un client, et versions socles.">
        <x-slot:actions>
            <x-ui.button :href="route('versions.create', array_filter(['product' => $product, 'customer' => $customer !== 'none' ? $customer : null]))">Nouvelle version</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom de la version…" />
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <select wire:model.live="customer" aria-label="Client" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les clients</option>
            <option value="none">Socles (sans client)</option>
            @foreach ($customers as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" aria-label="Cycle de vie" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les cycles de vie</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" class="py-2" />
    </div>

    @if ($versions->isEmpty())
        <x-ui.empty>Aucune version ne correspond à ces critères.</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Application</th>
                <th>Client</th>
                <th>Produit</th>
                <th>Cycle de vie</th>
                <th>Backend</th>
                <th>Frontend</th>
                <th>Base de données</th>
                <th>Dépôts</th>
                <th>Responsable technique</th>
                <th>Suppléant</th>
            </x-slot:head>
            @foreach ($versions as $version)
                <tr wire:key="version-{{ $version->id }}" @class(['opacity-60' => $version->isArchived()])>
                    <td>
                        <a href="{{ route('versions.show', $version) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $version->name }}</a>
                        @if ($version->product->reference_version_id === $version->id) <x-ui.badge color="blue">Référence</x-ui.badge> @endif
                        @if ($version->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
                    </td>
                    <td>{{ $version->customer?->name ?? 'Socle' }}</td>
                    <td>{{ $version->product->name }}</td>
                    <td class="whitespace-nowrap">{{ $version->status->label() }}</td>
                    <td class="text-xs">{{ \App\Support\VersionInventory::stack($version, \App\Enums\RepositoryRole::Backend) ?: '—' }}</td>
                    <td class="text-xs">{{ \App\Support\VersionInventory::stack($version, \App\Enums\RepositoryRole::Frontend) ?: '—' }}</td>
                    <td class="text-xs">{{ \App\Support\VersionInventory::databases($version) ?: '—' }}</td>
                    <td class="text-xs">
                        @forelse (\App\Support\VersionInventory::repositoryUrls($version) as $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="block break-all text-brand-700 hover:underline">{{ \App\Models\Repository::pathFromUrl($url) ?? $url }}</a>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td class="text-sm">{{ $version->technicalOwner?->name ?? '—' }}</td>
                    <td class="text-sm">{{ $version->deputy?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-4">{{ $versions->links() }}</div>
    @endif
</div>
