<div>
    <x-ui.page-header title="Produits" description="Solutions commercialisées par Satisworld.">
        <x-slot:actions>
            <x-ui.button :href="route('products.create')">Nouveau produit</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou code…" />
        <x-ui.archived-toggle wire:model.live="showArchived" />
    </div>

    @if ($products->isEmpty())
        <x-ui.empty>
            Aucun produit. Commencez par créer Satis Macro, Satis Pro ou Satis Assur.
        </x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Code</th>
                <th>Nom</th>
                <th>Version de référence</th>
                <th class="text-right">Versions</th>
            </x-slot:head>
            @foreach ($products as $product)
                <tr wire:key="product-{{ $product->id }}" @class(['opacity-60' => $product->isArchived()])>
                    <td class="font-mono text-xs text-slate-500">{{ $product->code }}</td>
                    <td>
                        <a href="{{ route('products.show', $product) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $product->name }}</a>
                        @if ($product->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                    </td>
                    <td>{{ $product->referenceVersion?->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $product->versions_count }}</td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</div>
