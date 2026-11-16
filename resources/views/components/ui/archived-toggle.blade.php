<label {{ $attributes->only('class')->class('flex items-center gap-2 text-sm text-slate-600') }}>
    <input type="checkbox" {{ $attributes->except('class') }} class="rounded border-slate-300 text-brand-700">
    {{ $slot->isEmpty() ? 'Afficher les archivés' : $slot }}
</label>
