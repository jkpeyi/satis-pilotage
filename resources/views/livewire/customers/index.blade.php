<div>
    <x-ui.page-header title="Clients" description="Banques, assurances et institutions de microfinance utilisant les solutions Satis.">
        <x-slot:actions>
            <x-ui.button :href="route('customers.create')">Nouveau client</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom du client…" />
        <select wire:model.live="type" aria-label="Type de client" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les types</option>
            @foreach ($types as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" />
    </div>

    @if ($customers->isEmpty())
        <x-ui.empty>Aucun client.</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Nom</th>
                <th>Type</th>
                <th>Pays</th>
                <th class="text-right">Versions</th>
            </x-slot:head>
            @foreach ($customers as $customer)
                <tr wire:key="customer-{{ $customer->id }}" @class(['opacity-60' => $customer->isArchived()])>
                    <td>
                        <a href="{{ route('customers.edit', $customer) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $customer->name }}</a>
                        @if ($customer->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                    </td>
                    <td>{{ $customer->type?->label() ?? '—' }}</td>
                    <td>{{ $customer->country ?? '—' }}</td>
                    <td class="text-right tabular-nums">
                        <a href="{{ route('versions.index', ['customer' => $customer->id]) }}" wire:navigate class="hover:text-brand-700">{{ $customer->versions_count }}</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-4">{{ $customers->links() }}</div>
    @endif
</div>
