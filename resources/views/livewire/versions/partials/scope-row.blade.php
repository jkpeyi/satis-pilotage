{{-- One implementation row: checkbox, name, status select, counters, history. --}}
<div class="group flex items-center gap-2 py-1.5 pr-2 text-sm hover:bg-slate-50" style="padding-left: {{ $depth * 1.25 }}rem">
    <input type="checkbox" wire:model.live="selectedIds" value="{{ $implementation->id }}" class="rounded border-slate-300 text-brand-700" aria-label="Sélectionner {{ $name }}">
    {{ $toggle ?? '' }}
    <span @class(['min-w-0 flex-1 truncate', 'font-medium text-slate-800' => $isModule, 'text-slate-700' => ! $isModule])>
        {{ $name }}
        @if ($implementation->deviation) <span class="ml-1 text-xs text-amber-700" title="Écart renseigné">≠ écart</span> @endif
    </span>
    @if ($implementation->business_rules_count) <span class="text-xs text-slate-500" title="Règles métier">{{ $implementation->business_rules_count }} RM</span> @endif
    @if ($implementation->issues_count) <span class="text-xs text-red-700" title="Problèmes">{{ $implementation->issues_count }} PB</span> @endif
    <select wire:change="setStatus({{ $implementation->id }}, $event.target.value)" aria-label="État de {{ $name }}"
        @class([
            'rounded-full border-0 py-0.5 pl-2 pr-7 text-xs font-medium focus:ring-2 focus:ring-brand-600/30',
            'bg-slate-100 text-slate-700' => $implementation->status->color() === 'gray',
            'bg-emerald-50 text-emerald-700' => $implementation->status->color() === 'green',
            'bg-amber-50 text-amber-800' => $implementation->status->color() === 'amber',
            'bg-red-50 text-red-700' => $implementation->status->color() === 'red',
            'bg-violet-50 text-violet-700' => $implementation->status->color() === 'violet',
        ])>
        @foreach (\App\Enums\ImplementationStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected($implementation->status === $status)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button type="button" wire:click="showHistoryOf({{ $implementation->id }})" class="text-xs text-slate-400 hover:text-slate-900">Historique</button>
</div>
