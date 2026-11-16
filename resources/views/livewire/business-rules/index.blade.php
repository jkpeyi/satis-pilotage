<div>
    <x-ui.page-header title="Règles métier" description="Toutes les règles de gestion relevées, par version et par élément." />

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Intitulé, texte ou RM-0042…" />
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <select wire:model.live="version" aria-label="Version" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Toutes les versions</option>
            @foreach ($versions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <select wire:model.live="source" aria-label="Source" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Toutes les sources</option>
            @foreach ($sources as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="status" aria-label="Statut" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
    </div>

    @if ($rules->isEmpty())
        <x-ui.empty>Aucune règle métier. Elles se créent depuis la fiche d'un élément dans une version.</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Code</th>
                <th>Règle</th>
                <th>Élément</th>
                <th>Version</th>
                <th>Statut</th>
            </x-slot:head>
            @foreach ($rules as $rule)
                <tr wire:key="rule-{{ $rule->id }}">
                    <td class="font-mono text-xs text-slate-500">{{ $rule->displayCode() }}</td>
                    <td><a href="{{ route('business-rules.show', $rule) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $rule->title }}</a></td>
                    <td>{{ $rule->implementation->catalogItem()->name }}</td>
                    <td>{{ $rule->implementation->version->name }}</td>
                    <td><x-ui.badge :color="$rule->status === \App\Enums\RuleStatus::Validated ? 'green' : 'amber'">{{ $rule->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-4">{{ $rules->links() }}</div>
    @endif
</div>
