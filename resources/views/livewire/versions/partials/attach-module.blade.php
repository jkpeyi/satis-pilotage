@php($attached = $byModule->has($moduleId))
<li x-data="{ open: false }" wire:key="attach-module-{{ $moduleId }}">
    <div class="flex items-center gap-2 py-1 text-sm" style="padding-left: {{ $depth * 1.25 }}rem">
        <button type="button" x-on:click="open = !open" class="w-4 text-xs text-slate-500" x-text="open ? '▾' : '▸'" aria-label="Déplier ou replier"></button>
        <label class="flex min-w-0 items-center gap-2">
            @if ($attached)
                <input type="checkbox" checked disabled class="rounded border-slate-300 text-slate-400">
                <span class="truncate text-slate-400">{{ $tree->modules[$moduleId]['name'] }} (déjà rattaché)</span>
            @else
                <input type="checkbox" wire:model="attachModuleIds" value="{{ $moduleId }}" class="rounded border-slate-300 text-brand-700">
                <span class="truncate font-medium text-slate-800">{{ $tree->modules[$moduleId]['name'] }}</span>
            @endif
        </label>
    </div>
    <ul x-show="open" x-cloak>
        @foreach ($tree->childModuleIds($moduleId) as $childId)
            @include('livewire.versions.partials.attach-module', ['moduleId' => $childId, 'depth' => $depth + 1])
        @endforeach
        @foreach ($tree->featureIds($moduleId) as $featureId)
            <li class="flex items-center gap-2 py-1 text-sm" style="padding-left: {{ ($depth + 1) * 1.25 + 1.5 }}rem" wire:key="attach-feature-{{ $featureId }}">
                <label class="flex min-w-0 items-center gap-2">
                    @if ($byFeature->has($featureId))
                        <input type="checkbox" checked disabled class="rounded border-slate-300 text-slate-400">
                        <span class="truncate text-slate-400">{{ $tree->features[$featureId]['name'] }}</span>
                    @else
                        <input type="checkbox" wire:model="attachFeatureIds" value="{{ $featureId }}" class="rounded border-slate-300 text-brand-700">
                        <span class="truncate text-slate-700">{{ $tree->features[$featureId]['name'] }}</span>
                    @endif
                </label>
            </li>
        @endforeach
    </ul>
</li>
