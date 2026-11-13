<div>
    <x-ui.page-header :title="$product->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('products.index') }}" wire:navigate class="hover:text-slate-900">Produits</a>
            / <span class="font-mono">{{ $product->code }}</span>
            @if ($product->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('products.edit', $product)">Modifier</x-ui.button>
            <x-ui.button variant="ghost" wire:click="toggleArchive"
                wire:confirm="{{ $product->isArchived() ? 'Restaurer ce produit ?' : 'Archiver ce produit ? Il ne sera plus proposé dans les listes.' }}">
                {{ $product->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($product->description)
        <x-ui.markdown :text="$product->description" class="mb-6 max-w-3xl" />
    @endif

    @error('reference') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

    <x-ui.card title="Versions">
        <x-slot:actions>
            <x-ui.button size="sm" :href="route('versions.create', ['product' => $product->id])">Nouvelle version</x-ui.button>
        </x-slot:actions>

        <p class="mb-4 text-sm text-slate-500">
            La version de référence sert de pivot par défaut dans la cartographie des écarts. Elle peut être une version socle, sans client.
        </p>

        @if ($versions->isEmpty())
            <x-ui.empty>Aucune version pour ce produit.</x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>Version</th>
                    <th>Client</th>
                    <th>Statut</th>
                    <th class="text-right">Dépôts</th>
                    <th></th>
                </x-slot:head>
                @foreach ($versions as $version)
                    <tr wire:key="version-{{ $version->id }}" @class(['opacity-60' => $version->isArchived()])>
                        <td>
                            <a href="{{ route('versions.show', $version) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $version->name }}</a>
                            @if ($product->reference_version_id === $version->id) <x-ui.badge color="blue">Référence</x-ui.badge> @endif
                            @if ($version->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
                        </td>
                        <td>{{ $version->customer?->name ?? 'Socle (sans client)' }}</td>
                        <td>{{ $version->status->label() }}</td>
                        <td class="text-right tabular-nums">{{ $version->repositories_count }}</td>
                        <td class="text-right">
                            @if ($product->reference_version_id !== $version->id && ! $version->isArchived())
                                <x-ui.button size="sm" variant="ghost" wire:click="setReference({{ $version->id }})">Définir comme référence</x-ui.button>
                            @elseif ($product->reference_version_id === $version->id)
                                <x-ui.button size="sm" variant="ghost" wire:click="clearReference">Retirer la référence</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.audit :record="$product" class="mt-6" />
</div>
