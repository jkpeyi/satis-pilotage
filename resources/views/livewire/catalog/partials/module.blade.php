@php
    $module = $tree->modules[$moduleId];
    $isOpen = in_array($moduleId, $expanded, true);
    $hasChildren = $tree->hasChildren($moduleId);
    $key = 'module:'.$moduleId;
@endphp

<li data-sortable-item data-id="{{ $moduleId }}" wire:key="module-{{ $moduleId }}">
    <div @class([
            'group flex items-center gap-1 rounded py-1 pr-2 text-sm',
            'bg-brand-50' => $selected === $key,
            'hover:bg-slate-100' => $selected !== $key,
            'opacity-50' => $module['archived'],
        ]) style="padding-left: {{ $depth * 1.25 }}rem">
        <span data-drag-handle class="cursor-grab px-1 text-slate-300 group-hover:text-slate-500" title="Glisser pour déplacer" aria-hidden="true">⋮⋮</span>
        <button type="button" wire:click="toggle({{ $moduleId }})" class="w-4 text-xs text-slate-500" aria-label="{{ $isOpen ? 'Replier' : 'Déplier' }}" @disabled(! $hasChildren && ! $isOpen)>
            {{ $hasChildren || $isOpen ? ($isOpen ? '▾' : '▸') : '' }}
        </button>
        <button type="button" wire:click="select('{{ $key }}')" class="min-w-0 flex-1 truncate text-left font-medium text-slate-800">
            {{ $module['name'] }}
            @if ($module['code']) <span class="ml-1 font-mono text-xs font-normal text-slate-400">{{ $module['code'] }}</span> @endif
        </button>
        <span class="hidden shrink-0 gap-1 group-hover:flex">
            <button type="button" wire:click="startDraft({{ $moduleId }}, 'module')" class="rounded px-1.5 text-xs text-slate-500 hover:bg-white hover:text-brand-700" title="Ajouter un sous-module">+ sous-module</button>
            <button type="button" wire:click="startDraft({{ $moduleId }}, 'feature')" class="rounded px-1.5 text-xs text-slate-500 hover:bg-white hover:text-brand-700" title="Ajouter une fonctionnalité">+ fonctionnalité</button>
        </span>
    </div>

    @if ($isOpen)
        <ul x-sortable="{ group: 'modules', parent: {{ $moduleId }} }" data-parent="{{ $moduleId }}" class="min-h-1">
            @foreach ($tree->childModuleIds($moduleId) as $childId)
                @include('livewire.catalog.partials.module', ['moduleId' => $childId, 'depth' => $depth + 1])
            @endforeach
        </ul>
        <ul x-sortable="{ group: 'features', parent: {{ $moduleId }} }" data-parent="{{ $moduleId }}" class="min-h-1">
            @foreach ($tree->featureIds($moduleId) as $featureId)
                @php($feature = $tree->features[$featureId])
                <li data-sortable-item data-id="{{ $featureId }}" wire:key="feature-{{ $featureId }}">
                    <div @class([
                            'group flex items-center gap-1 rounded py-1 pr-2 text-sm',
                            'bg-brand-50' => $selected === 'feature:'.$featureId,
                            'hover:bg-slate-100' => $selected !== 'feature:'.$featureId,
                            'opacity-50' => $feature['archived'],
                        ]) style="padding-left: {{ ($depth + 1) * 1.25 }}rem">
                        <span data-drag-handle class="cursor-grab px-1 text-slate-300 group-hover:text-slate-500" aria-hidden="true">⋮⋮</span>
                        <span class="w-4 text-center text-xs text-slate-400" aria-hidden="true">•</span>
                        <button type="button" wire:click="select('feature:{{ $featureId }}')" class="min-w-0 flex-1 truncate text-left text-slate-700">
                            {{ $feature['name'] }}
                            @if ($feature['code']) <span class="ml-1 font-mono text-xs text-slate-400">{{ $feature['code'] }}</span> @endif
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($draft !== null && $draft['parent_id'] === $moduleId)
            <ul>@include('livewire.catalog.partials.draft', ['depth' => $depth + 1])</ul>
        @endif
    @endif
</li>
