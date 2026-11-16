{{-- Quick entry line (EF-202). Enter: create and continue. Tab / Shift+Tab: one level down / up. Alt+M / Alt+F: kind. Esc: close. --}}
<li class="py-1" style="padding-left: {{ $depth * 1.25 }}rem" wire:key="draft-{{ $draft['parent_id'] ?? 'root' }}-{{ $draft['kind'] }}">
    <div class="flex items-center gap-2">
        <span class="inline-flex overflow-hidden rounded border border-slate-300 text-[11px] font-medium">
            <button type="button" wire:click="setDraftKind('module')"
                @class(['px-1.5 py-0.5', 'bg-brand-700 text-white' => $draft['kind'] === 'module', 'bg-white text-slate-500' => $draft['kind'] !== 'module'])>Module</button>
            @if ($draft['parent_id'] !== null)
                <button type="button" wire:click="setDraftKind('feature')"
                    @class(['px-1.5 py-0.5', 'bg-brand-700 text-white' => $draft['kind'] === 'feature', 'bg-white text-slate-500' => $draft['kind'] !== 'feature'])>Fonctionnalité</button>
            @endif
        </span>
        <input type="text" wire:model="draftName" x-init="$el.focus()"
            placeholder="{{ $draft['kind'] === 'feature' ? 'Nouvelle fonctionnalité…' : ($draft['parent_id'] ? 'Nouveau sous-module…' : 'Nouveau module…') }}"
            x-on:keydown.enter.prevent="$wire.saveDraft()"
            x-on:keydown.tab.prevent="$event.shiftKey ? $wire.outdentDraft() : $wire.indentDraft()"
            x-on:keydown.escape.prevent="$wire.cancelDraft()"
            x-on:keydown="if ($event.altKey && $event.code === 'KeyM') { $event.preventDefault(); $wire.setDraftKind('module') } else if ($event.altKey && $event.code === 'KeyF') { $event.preventDefault(); $wire.setDraftKind('feature') }"
            class="min-w-0 flex-1 rounded border border-brand-600 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600/30"
            aria-label="Nom du nouvel élément">
        <button type="button" wire:click="cancelDraft" class="text-xs text-slate-500 hover:text-slate-900">Fermer</button>
    </div>
    @error('draftName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</li>
