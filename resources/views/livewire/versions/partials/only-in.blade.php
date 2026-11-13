{{-- Items one version has and the other lacks. $entries, $owner (version that has them), $other. --}}
@if ($entries === [])
    <p class="text-sm text-slate-500">Rien : tout ce que {{ $owner->name }} possède existe aussi dans {{ $other->name }}.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($entries as $entry)
            <li class="py-2" wire:key="{{ $side }}-{{ $entry['key'] }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('implementations.show', $entry['implementation']) }}" wire:navigate @class(['hover:text-brand-700', 'font-medium text-slate-900' => $entry['type'] === 'module', 'text-slate-800' => $entry['type'] !== 'module'])>{{ $entry['name'] }}</a>
                        <span class="text-xs text-slate-500">{{ $entry['typeLabel'] }}@if ($entry['path']) · {{ $entry['path'] }}@endif</span>
                    </div>
                    <span class="flex shrink-0 items-center gap-2 text-xs">
                        <x-ui.badge :color="$entry['implementation']->status->color()">{{ $entry['implementation']->status->label() }}</x-ui.badge>
                        @if ($entry['other'])
                            <span class="text-violet-700" title="Rattaché mais marqué Absent dans {{ $other->name }}">« Absent » dans l'autre</span>
                        @endif
                    </span>
                </div>
                @if ($entry['children'])
                    <details class="mt-1 text-xs text-slate-600">
                        <summary class="cursor-pointer text-slate-500">Avec {{ count($entry['children']) }} sous-élément(s)</summary>
                        <p class="mt-1">{{ implode(', ', $entry['children']) }}</p>
                    </details>
                @endif
            </li>
        @endforeach
    </ul>
@endif
