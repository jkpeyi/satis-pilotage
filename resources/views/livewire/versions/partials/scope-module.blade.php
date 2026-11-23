@php($implementation = $byModule->get($moduleId))
@if ($implementation)
    <li x-data="{ open: false }" x-on:scope-tree.window="open = $event.detail"
        x-on:scope-open-module.window="if ($event.detail.id === {{ $moduleId }}) open = true"
        data-sortable-item data-id="{{ $moduleId }}" wire:key="scope-module-{{ $moduleId }}">
        @include('livewire.versions.partials.scope-row', [
            'name' => $tree->modules[$moduleId]['name'],
            'isModule' => true,
            'draggable' => true,
            'addUnder' => $moduleId,
            'toggle' => new \Illuminate\Support\HtmlString('<button type="button" x-on:click="open = !open" class="w-4 text-xs text-slate-500" x-text="open ? \'▾\' : \'▸\'" aria-label="Déplier ou replier"></button>'),
        ])
        <div x-show="open">
            <ul x-sortable="{ group: 'modules', parent: {{ $moduleId }} }" data-parent="{{ $moduleId }}" class="min-h-1">
                @foreach ($tree->childModuleIds($moduleId) as $childId)
                    @include('livewire.versions.partials.scope-module', ['moduleId' => $childId, 'depth' => $depth + 1])
                @endforeach
            </ul>
            <ul x-sortable="{ group: 'features', parent: {{ $moduleId }} }" data-parent="{{ $moduleId }}" class="min-h-1">
                @foreach ($tree->featureIds($moduleId) as $featureId)
                    @if ($featureImplementation = $byFeature->get($featureId))
                        <li data-sortable-item data-id="{{ $featureId }}" wire:key="scope-feature-{{ $featureId }}">
                            @include('livewire.versions.partials.scope-row', [
                                'implementation' => $featureImplementation,
                                'name' => $tree->features[$featureId]['name'],
                                'isModule' => false,
                                'draggable' => true,
                                'addUnder' => null,
                                'depth' => $depth + 1,
                                'toggle' => new \Illuminate\Support\HtmlString('<span class="w-4 text-center text-xs text-slate-400">•</span>'),
                            ])
                        </li>
                    @endif
                @endforeach
            </ul>
            @if ($tree->sharedFeatureIds($moduleId) !== [])
                {{-- Features whose main module is elsewhere (RG-16): one implementation, shown here too. --}}
                <ul>
                    @foreach ($tree->sharedFeatureIds($moduleId) as $featureId)
                        @if ($featureImplementation = $byFeature->get($featureId))
                            <li wire:key="scope-shared-{{ $moduleId }}-{{ $featureId }}">
                                @include('livewire.versions.partials.scope-row', [
                                    'implementation' => $featureImplementation,
                                    'name' => $tree->features[$featureId]['name'],
                                    'isModule' => false,
                                    'draggable' => false,
                                    'addUnder' => null,
                                    'shared' => true,
                                    'depth' => $depth + 1,
                                    'toggle' => new \Illuminate\Support\HtmlString('<span class="w-4 text-center text-xs text-violet-500" title="Fonctionnalité partagée : son module principal est ailleurs">↗</span>'),
                                ])
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
            @if ($draft !== null && $draft['parent_id'] === $moduleId)
                <ul>@include('livewire.versions.partials.scope-draft', ['depth' => $depth + 1])</ul>
            @endif
        </div>
    </li>
@endif
