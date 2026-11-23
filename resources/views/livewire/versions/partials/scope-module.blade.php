@php($implementation = $byModule->get($moduleId))
@if ($implementation)
    <li x-data="{ open: false }" x-on:scope-tree.window="open = $event.detail" wire:key="scope-module-{{ $moduleId }}">
        @include('livewire.versions.partials.scope-row', [
            'name' => $tree->modules[$moduleId]['name'],
            'isModule' => true,
            'toggle' => new \Illuminate\Support\HtmlString('<button type="button" x-on:click="open = !open" class="w-4 text-xs text-slate-500" x-text="open ? \'▾\' : \'▸\'" aria-label="Déplier ou replier"></button>'),
        ])
        <ul x-show="open">
            @foreach ($tree->childModuleIds($moduleId) as $childId)
                @include('livewire.versions.partials.scope-module', ['moduleId' => $childId, 'depth' => $depth + 1])
            @endforeach
            @foreach ($tree->featureIds($moduleId) as $featureId)
                @if ($featureImplementation = $byFeature->get($featureId))
                    <li wire:key="scope-feature-{{ $featureId }}">
                        @include('livewire.versions.partials.scope-row', [
                            'implementation' => $featureImplementation,
                            'name' => $tree->features[$featureId]['name'],
                            'isModule' => false,
                            'depth' => $depth + 1,
                            'toggle' => new \Illuminate\Support\HtmlString('<span class="w-4 text-center text-xs text-slate-400">•</span>'),
                        ])
                    </li>
                @endif
            @endforeach
        </ul>
    </li>
@endif
