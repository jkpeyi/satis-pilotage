<div>
    <x-ui.page-header title="Problèmes" description="Anomalies, dette et risques relevés dans les versions.">
        <x-slot:actions>
            <span class="text-sm text-slate-600">Effort cumulé de la sélection : <strong class="tabular-nums">{{ rtrim(rtrim(number_format((float) $totalEffort, 1, ',', ' '), '0'), ',') }} j/h</strong></span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Titre, texte ou PB-0042…" />
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les produits</option>
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <select wire:model.live="version" aria-label="Version" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Toutes les versions</option>
            @foreach ($versions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <select wire:model.live="severity" aria-label="Gravité" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Toutes gravités</option>
            @foreach ($severities as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="type" aria-label="Type" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les types</option>
            @foreach ($types as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="status" aria-label="Statut" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
        <x-ui.archived-toggle wire:model.live="showArchived" class="py-2" />
    </div>

    @if ($issues->isEmpty())
        <x-ui.empty>Aucun problème ne correspond. Les problèmes se créent depuis la fiche d'un élément dans une version.</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Code</th>
                <th>Problème</th>
                <th>Gravité</th>
                <th>Type</th>
                <th>Statut</th>
                <th>Versions</th>
                <th class="text-right">Effort</th>
            </x-slot:head>
            @foreach ($issues as $issue)
                <tr wire:key="issue-{{ $issue->id }}" @class(['opacity-60' => $issue->isArchived()])>
                    <td class="font-mono text-xs text-slate-500">{{ $issue->displayCode() }}</td>
                    <td><a href="{{ route('issues.show', $issue) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $issue->title }}</a></td>
                    <td><x-ui.severity-badge :severity="$issue->severity" /></td>
                    <td>{{ $issue->type->label() }}</td>
                    <td>{{ $issue->status->label() }}</td>
                    <td class="text-xs text-slate-600" title="{{ $issue->implementations->pluck('version.name')->unique()->implode(', ') }}">
                        {{ $issue->implementations->pluck('version_id')->unique()->count() }}
                    </td>
                    <td class="text-right tabular-nums">{{ $issue->effort_days !== null ? rtrim(rtrim((string) $issue->effort_days, '0'), '.') : '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-4">{{ $issues->links() }}</div>
    @endif
</div>
