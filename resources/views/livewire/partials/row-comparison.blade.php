{{-- Detailed comparison of one item (EF-306). Needs $comparison (RowComparison) and $pivotId, and a showComparison property. --}}
<div x-data="{ open: $wire.entangle('showComparison') }" x-show="open" x-cloak x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 px-4 py-10">
    <div x-on:click.outside="open = false" class="mx-auto w-full max-w-6xl rounded-lg bg-white shadow-xl">
        @if ($comparison)
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <div>
                    <p class="text-xs text-slate-500">{{ $comparison['item']->typeLabel() }}</p>
                    <h2 class="font-semibold text-slate-900">{{ $comparison['item']->name }}</h2>
                </div>
                <button type="button" x-on:click="open = false" class="text-slate-400 hover:text-slate-700" aria-label="Fermer">×</button>
            </header>
            <div class="overflow-x-auto p-5">
                <table class="min-w-full table-fixed border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th class="w-40"></th>
                            @foreach ($comparison['versions'] as $version)
                                <th @class(['min-w-56 border-b border-slate-200 px-3 py-2 text-left font-medium', 'bg-brand-50 text-brand-700' => (string) $version->id === (string) $pivotId])>
                                    {{ $version->name }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">État</th>
                            @foreach ($comparison['versions'] as $version)
                                @php($implementation = $comparison['implementations'][$version->id])
                                <td class="border-b border-slate-100 px-3 py-3">
                                    @if ($implementation)
                                        <a href="{{ route('implementations.show', $implementation) }}" wire:navigate>
                                            <x-ui.badge :color="$implementation->status->color()">{{ $implementation->status->label() }}</x-ui.badge>
                                        </a>
                                    @else
                                        <span class="text-slate-400">Non rattaché</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Écart</th>
                            @foreach ($comparison['versions'] as $version)
                                @php($implementation = $comparison['implementations'][$version->id])
                                <td class="border-b border-slate-100 px-3 py-3">
                                    @if ($implementation?->deviation)
                                        <x-ui.markdown :text="$implementation->deviation" />
                                    @elseif ($implementation)
                                        <span class="text-slate-400">Conforme</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @foreach ($comparison['ruleFamilies'] as $family)
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-normal text-slate-600">
                                    <span class="block font-semibold uppercase tracking-wide text-slate-400">Règle</span>
                                    {{ $family['title'] }}
                                    @if ($family['divergent']) <x-ui.badge color="amber" class="mt-1">Divergente</x-ui.badge> @endif
                                </th>
                                @foreach ($comparison['versions'] as $version)
                                    @php($rule = $family['rules'][$version->id])
                                    <td class="border-b border-slate-100 px-3 py-3">
                                        @if ($rule)
                                            <a href="{{ route('business-rules.show', $rule) }}" wire:navigate class="mb-1 block font-mono text-xs text-slate-500 hover:text-brand-700">{{ $rule->displayCode() }}</a>
                                            <x-ui.markdown :text="$rule->description" />
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Problèmes</th>
                            @foreach ($comparison['versions'] as $version)
                                @php($implementation = $comparison['implementations'][$version->id])
                                <td class="px-3 py-3">
                                    <ul class="space-y-1">
                                        @foreach ($implementation?->issues ?? [] as $issue)
                                            <li><a href="{{ route('issues.show', $issue) }}" wire:navigate class="hover:text-brand-700"><span class="font-mono text-xs text-slate-500">{{ $issue->displayCode() }}</span> {{ $issue->title }}</a></li>
                                        @endforeach
                                    </ul>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
