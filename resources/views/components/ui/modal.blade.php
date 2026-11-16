@props(['title'])

{{-- Bind with wire:model="showSomething" on a boolean Livewire property. --}}
<div x-data="{ open: $wire.entangle('{{ $attributes->wire('model')->value() }}') }"
     x-show="open" x-cloak x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 px-4 py-12">
    <div x-on:click.outside="open = false" class="w-full max-w-xl rounded-lg bg-white shadow-xl">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold text-slate-900">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" class="text-slate-400 hover:text-slate-700" aria-label="Fermer">×</button>
        </header>
        <div class="p-5">{{ $slot }}</div>
    </div>
</div>
