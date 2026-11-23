{{-- Inline entry line of the scope (same keys as the catalog, EF-202): Enter creates in the shared catalog and attaches to this version, Tab / Shift+Tab one level down / up, Alt+M / Alt+F kind, Esc closes. --}}
<li class="py-1 pr-2" style="padding-left: {{ $depth * 1.25 + 1.5 }}rem" wire:key="scope-draft-{{ $draft['parent_id'] ?? 'root' }}-{{ $draft['kind'] }}">
    <div class="flex items-center gap-2">
        <span class="inline-flex overflow-hidden rounded border border-slate-300 text-[11px] font-medium">
            <button type="button" wire:click="setDraftKind('module')"
                @class(['px-1.5 py-0.5', 'bg-brand-700 text-white' => $draft['kind'] === 'module', 'bg-white text-slate-500' => $draft['kind'] !== 'module'])>Module</button>
            @if ($draft['parent_id'] !== null)
                <button type="button" wire:click="setDraftKind('feature')"
                    @class(['px-1.5 py-0.5', 'bg-brand-700 text-white' => $draft['kind'] === 'feature', 'bg-white text-slate-500' => $draft['kind'] !== 'feature'])>Fonctionnalité</button>
            @endif
        </span>
        <input type="text" wire:model.live.debounce.250ms="draftName" x-init="$el.focus()"
            placeholder="{{ $draft['kind'] === 'feature' ? 'Nouvelle fonctionnalité…' : ($draft['parent_id'] ? 'Nouveau sous-module…' : 'Nouveau module…') }}"
            x-on:keydown.enter.prevent="$wire.saveDraft()"
            x-on:keydown.tab.prevent="$event.shiftKey ? $wire.outdentDraft() : $wire.indentDraft()"
            x-on:keydown.escape.prevent="$wire.cancelDraft()"
            x-on:keydown="if ($event.altKey && $event.code === 'KeyM') { $event.preventDefault(); $wire.setDraftKind('module') } else if ($event.altKey && $event.code === 'KeyF') { $event.preventDefault(); $wire.setDraftKind('feature') }"
            class="min-w-0 flex-1 rounded border border-brand-600 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600/30"
            aria-label="Nom du nouvel élément">
        <button type="button" wire:click="cancelDraft" class="text-xs text-slate-500 hover:text-slate-900">Fermer</button>
    </div>
    <p class="mt-1 text-[11px] text-slate-400">Entrée : créer et continuer · Tab / Maj+Tab : niveau inférieur / supérieur · Alt+M / Alt+F : module / fonctionnalité · Échap : fermer</p>
    @error('draftName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @if ($draftSuggestions !== [])
        {{-- Names are unique in the whole catalog (RG-15): reuse the existing item rather than create a duplicate. --}}
        <div class="mt-1 rounded-md border border-slate-200 bg-white text-xs shadow-sm">
            <p class="border-b border-slate-100 px-2 py-1 text-slate-500">Déjà dans le catalogue :</p>
            <ul class="divide-y divide-slate-100">
                @foreach ($draftSuggestions as $suggestion)
                    <li class="flex items-center gap-2 px-2 py-1" wire:key="scope-suggestion-{{ $suggestion['key'] }}">
                        <span class="min-w-0 flex-1 truncate" title="{{ $suggestion['path'] }}">
                            <span @class(['font-semibold text-slate-900' => $suggestion['exact'], 'text-slate-700' => ! $suggestion['exact']])>{{ $suggestion['name'] }}</span>
                            <span class="text-slate-400">· {{ $suggestion['type'] === 'feature' ? 'fonctionnalité' : 'module' }} · {{ $suggestion['path'] }}</span>
                        </span>
                        @if ($suggestion['exact']) <x-ui.badge color="amber">Même nom</x-ui.badge> @endif
                        @if ($suggestion['archived'])
                            <x-ui.badge>Archivé dans le catalogue</x-ui.badge>
                        @else
                            @if ($suggestion['attached'])
                                <x-ui.badge color="green">Déjà dans le périmètre</x-ui.badge>
                            @else
                                <button type="button" wire:click="reuseExisting('{{ $suggestion['key'] }}', 'attach')"
                                    title="Rattacher à cette version, à sa place actuelle dans le catalogue : {{ $suggestion['path'] }}"
                                    class="font-medium text-brand-700 hover:underline">Rattacher</button>
                            @endif
                            @if ($suggestion['type'] === 'feature' && $draft['parent_id'] !== null)
                                <button type="button" wire:click="reuseExisting('{{ $suggestion['key'] }}', 'link')"
                                    title="La même fonctionnalité apparaîtra aussi dans ce module, sans copie."
                                    class="font-medium text-brand-700 hover:underline">Ajouter ici</button>
                            @endif
                            @if ($suggestion['type'] === 'module' || $draft['parent_id'] !== null)
                                <button type="button" wire:click="reuseExisting('{{ $suggestion['key'] }}', 'move')"
                                    wire:confirm="Déplacer « {{ $suggestion['name'] }} » ici dans le catalogue ? Toutes les versions verront ce déplacement."
                                    class="font-medium text-brand-700 hover:underline">Déplacer ici</button>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</li>
